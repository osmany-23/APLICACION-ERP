<?php

namespace App\Services;

use App\Models\CashCount;
use App\Models\CashMovement;
use App\Models\CashMovementReason;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SalesReturn;
use App\Models\Terminal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Logica de negocio del modulo de Apertura de Caja: abrir/cerrar turnos,
 * registrar y auditar movimientos manuales de efectivo, calcular el
 * resumen financiero de una apertura y alimentar el monitor de cajas.
 *
 * Deliberadamente separado de los controladores para que las reglas
 * (no exceder el efectivo disponible, exigir motivo, exigir autorizacion
 * por monto, no cerrar con movimientos pendientes, etc.) vivan en un solo
 * lugar y sean faciles de cubrir con tests, igual que SalesService.
 */
class CashRegisterService
{
    /**
     * Abre una nueva sesion (turno) sobre una caja. Bloquea si la caja no
     * esta activa, si ya tiene otra apertura en curso, o si la terminal que
     * origina la solicitud esta bloqueada a otra caja distinta.
     */
    public function openSession(int $companyId, int $userId, array $payload, bool $canViewAll = false): CashSession
    {
        return DB::transaction(function () use ($companyId, $userId, $payload, $canViewAll) {
            $register = $this->resolveRegister($companyId, (int) ($payload['cash_register_id'] ?? 0));

            abort_if($register->status !== 'ACTIVA', 422, "La caja \"{$register->name}\" no esta activa.");
            abort_unless($this->userCanOperate($register, $userId, $canViewAll), 403, 'No estas autorizado para operar esta caja.');

            $alreadyOpen = $register->sessions()->where('status', 'ABIERTA')->exists();
            abort_if($alreadyOpen, 409, "La caja \"{$register->name}\" ya tiene una apertura en curso.");

            $terminal = null;
            $terminalCode = trim((string) ($payload['terminal_code'] ?? ''));

            if ($terminalCode !== '') {
                $terminal = $this->checkinTerminal($companyId, $register->branch_id, $userId, $payload);

                if ($terminal->cash_register_id && (int) $terminal->cash_register_id !== $register->id) {
                    $lockedTo = $terminal->cashRegister?->name ?? '#'.$terminal->cash_register_id;
                    abort(422, "Esta terminal esta asignada a la caja \"{$lockedTo}\" y no puede abrir \"{$register->name}\".");
                }
            }

            $openingAmount = round((float) ($payload['opening_amount'] ?? 0), 4);
            abort_if($openingAmount < 0, 422, 'El monto inicial no puede ser negativo.');

            $session = CashSession::create([
                'company_id' => $companyId,
                'branch_id' => $register->branch_id,
                'cash_register_id' => $register->id,
                'terminal_id' => $terminal?->id,
                'opened_by' => $userId,
                'status' => 'ABIERTA',
                'currency_id' => $payload['currency_id'] ?? $register->default_currency_id,
                'exchange_rate' => $payload['exchange_rate'] ?? 1,
                'opening_amount' => $openingAmount,
                'opening_breakdown' => $payload['opening_breakdown'] ?? null,
                'opening_note' => $this->nullableText($payload['opening_note'] ?? null),
                'opened_at' => now(),
                'opening_ip' => $payload['ip'] ?? null,
            ]);

            return $session->fresh(['cashRegister', 'branch', 'openedBy', 'terminal']);
        });
    }

    /**
     * Registra un ingreso o egreso manual de efectivo. Nunca se permite sin
     * motivo (catalogo o texto libre), y un egreso nunca puede dejar el
     * balance de la caja en negativo. Si la caja tiene un umbral de
     * autorizacion configurado y el monto lo supera, el movimiento queda
     * PENDIENTE_AUTORIZACION en vez de ACTIVO (no se suma/resta al balance
     * hasta que un supervisor lo autorice).
     */
    public function addMovement(CashSession $session, int $userId, string $type, array $payload, ?Terminal $terminal = null): CashMovement
    {
        return DB::transaction(function () use ($session, $userId, $type, $payload, $terminal) {
            abort_unless(in_array($type, ['INGRESO', 'EGRESO'], true), 422, 'Tipo de movimiento invalido.');
            abort_unless($session->status === 'ABIERTA', 409, 'La caja no esta abierta; no se pueden registrar movimientos.');

            $amount = round((float) ($payload['amount'] ?? 0), 4);
            abort_if($amount <= 0, 422, 'El monto debe ser mayor que cero.');

            $reason = null;
            if (! empty($payload['reason_id'])) {
                $reason = CashMovementReason::query()
                    ->forCompany((int) $session->company_id)
                    ->active()
                    ->type($type)
                    ->where('id', (int) $payload['reason_id'])
                    ->first();

                abort_unless($reason, 422, 'El motivo seleccionado no es valido.');
            }

            $reasonText = $this->nullableText($payload['reason_text'] ?? null);
            $needsFreeText = ! $reason || $reason->requires_note;
            abort_if($needsFreeText && ! $reasonText, 422, 'Debes indicar el motivo del movimiento.');

            if ($type === 'EGRESO') {
                $available = $this->computeSummary($session)['balance_actual'];
                abort_if($amount > $available + 0.0001, 422, sprintf(
                    'No hay suficiente efectivo disponible en la caja. Disponible: %s, retiro solicitado: %s.',
                    number_format($available, 2),
                    number_format($amount, 2),
                ));
            }

            $register = $session->cashRegister ?? $this->resolveRegister((int) $session->company_id, (int) $session->cash_register_id);
            $threshold = $register->authorization_threshold !== null ? (float) $register->authorization_threshold : null;
            $requiresAuthorization = $threshold !== null && $amount > $threshold;

            $movement = CashMovement::create([
                'cash_session_id' => $session->id,
                'cash_register_id' => $session->cash_register_id,
                'company_id' => $session->company_id,
                'branch_id' => $session->branch_id,
                'terminal_id' => $terminal?->id ?? $session->terminal_id,
                'user_id' => $userId,
                'type' => $type,
                'reason_id' => $reason?->id,
                'reason_text' => $reasonText,
                'amount' => $amount,
                'currency_id' => $session->currency_id,
                'exchange_rate' => $session->exchange_rate,
                'reference' => $this->nullableText($payload['reference'] ?? null),
                'observation' => $this->nullableText($payload['observation'] ?? null),
                'status' => $requiresAuthorization ? 'PENDIENTE_AUTORIZACION' : 'ACTIVO',
                'requires_authorization' => $requiresAuthorization,
                'created_by' => $userId,
            ]);

            return $movement->fresh(['reason', 'user']);
        });
    }

    /**
     * Movimiento de caja generado por el propio sistema (no una accion
     * discrecional de un cajero): el abono en efectivo de una venta
     * "Pendiente de pago" (INGRESO) o el reintegro en efectivo de una Nota
     * de Credito (EGRESO). A diferencia de addMovement(), NUNCA queda
     * PENDIENTE_AUTORIZACION — el umbral de autorizacion de la caja existe
     * para frenar retiros/ingresos manuales discrecionales, no dinero ya
     * 100% trazable a una venta o nota real. Un EGRESO igual respeta el
     * limite de efectivo disponible (no se puede dejar la caja en numeros
     * negativos aunque el movimiento sea automatico).
     */
    public function recordAutomaticMovement(
        CashSession $session,
        int $userId,
        string $type,
        float $amount,
        string $reasonText,
        ?string $reference = null,
    ): CashMovement {
        return DB::transaction(function () use ($session, $userId, $type, $amount, $reasonText, $reference) {
            abort_unless(in_array($type, ['INGRESO', 'EGRESO'], true), 422, 'Tipo de movimiento invalido.');
            abort_unless($session->status === 'ABIERTA', 409, 'La caja no esta abierta; no se pueden registrar movimientos.');

            $amount = round($amount, 4);
            abort_if($amount <= 0, 422, 'El monto debe ser mayor que cero.');

            if ($type === 'EGRESO') {
                $available = $this->computeSummary($session)['balance_actual'];
                abort_if($amount > $available + 0.0001, 422, sprintf(
                    'No hay suficiente efectivo disponible en la caja para este reintegro. Disponible: %s, requerido: %s.',
                    number_format($available, 2),
                    number_format($amount, 2),
                ));
            }

            $movement = CashMovement::create([
                'cash_session_id' => $session->id,
                'cash_register_id' => $session->cash_register_id,
                'company_id' => $session->company_id,
                'branch_id' => $session->branch_id,
                'terminal_id' => $session->terminal_id,
                'user_id' => $userId,
                'type' => $type,
                'reason_id' => null,
                'reason_text' => $reasonText,
                'amount' => $amount,
                'currency_id' => $session->currency_id,
                'exchange_rate' => $session->exchange_rate,
                'reference' => $this->nullableText($reference),
                'status' => 'ACTIVO',
                'requires_authorization' => false,
                'created_by' => $userId,
            ]);

            return $movement->fresh();
        });
    }

    public function authorizeMovement(CashMovement $movement, int $userId): CashMovement
    {
        return DB::transaction(function () use ($movement, $userId) {
            abort_unless($movement->status === 'PENDIENTE_AUTORIZACION', 409, 'Este movimiento no esta pendiente de autorizacion.');

            if ($movement->type === 'EGRESO') {
                $session = $movement->session()->lockForUpdate()->first();
                $available = $this->computeSummary($session)['balance_actual'];
                abort_if((float) $movement->amount > $available + 0.0001, 422, 'No hay suficiente efectivo disponible para autorizar este retiro.');
            }

            $movement->status = 'ACTIVO';
            $movement->authorized_by = $userId;
            $movement->authorized_at = now();
            $movement->save();

            return $movement->fresh();
        });
    }

    public function rejectMovement(CashMovement $movement, int $userId, ?string $reason = null): CashMovement
    {
        abort_unless($movement->status === 'PENDIENTE_AUTORIZACION', 409, 'Este movimiento no esta pendiente de autorizacion.');

        $movement->status = 'RECHAZADO';
        $movement->cancelled_by = $userId;
        $movement->cancelled_at = now();
        $movement->cancellation_reason = $this->nullableText($reason);
        $movement->save();

        return $movement->fresh();
    }

    /**
     * Anula un movimiento activo. Nunca se borra fisicamente: queda
     * registrado con quien lo anulo, cuando y por que, para auditoria.
     */
    public function cancelMovement(CashMovement $movement, int $userId, string $reason): CashMovement
    {
        return DB::transaction(function () use ($movement, $userId, $reason) {
            abort_unless(
                in_array($movement->status, ['ACTIVO', 'PENDIENTE_AUTORIZACION'], true),
                409,
                'Este movimiento ya fue anulado o rechazado.'
            );
            abort_unless($movement->session->status === 'ABIERTA', 409, 'No se puede anular un movimiento de una caja que ya esta cerrada.');
            $reason = trim($reason);
            abort_if($reason === '', 422, 'Debes indicar el motivo de la anulacion.');

            $movement->status = 'ANULADO';
            $movement->cancelled_by = $userId;
            $movement->cancelled_at = now();
            $movement->cancellation_reason = $reason;
            $movement->save();

            return $movement->fresh();
        });
    }

    /**
     * Arqueo puntual sin cerrar la caja: compara el efectivo esperado contra
     * lo contado en ese instante y deja registro, pero la caja sigue
     * ABIERTA y puede seguir operando.
     */
    public function recordCount(CashSession $session, int $userId, array $payload): CashCount
    {
        abort_unless($session->status === 'ABIERTA', 409, 'Solo se puede hacer arqueo sobre una caja abierta.');

        $summary = $this->computeSummary($session);
        $counted = round((float) ($payload['counted_amount'] ?? 0), 4);

        return CashCount::create([
            'cash_session_id' => $session->id,
            'type' => 'ARQUEO',
            'counted_by' => $userId,
            'expected_amount' => $summary['balance_actual'],
            'counted_amount' => $counted,
            'difference' => round($counted - $summary['balance_actual'], 4),
            'breakdown' => $payload['breakdown'] ?? null,
            'note' => $this->nullableText($payload['note'] ?? null),
        ]);
    }

    /**
     * Cierra la apertura: exige que no queden movimientos pendientes de
     * autorizacion, calcula el efectivo esperado con la misma formula que
     * el panel en vivo, guarda el conteo fisico declarado y la diferencia
     * (faltante o sobrante), y dispara la caja a CERRADA.
     */
    public function closeSession(CashSession $session, int $userId, array $payload): CashSession
    {
        return DB::transaction(function () use ($session, $userId, $payload) {
            abort_unless($session->status === 'ABIERTA', 409, 'Esta caja ya esta cerrada o cancelada.');

            $pendingCount = CashMovement::query()
                ->where('cash_session_id', $session->id)
                ->where('status', 'PENDIENTE_AUTORIZACION')
                ->count();
            abort_if($pendingCount > 0, 422, 'Hay movimientos pendientes de autorizacion; resuelvelos antes de cerrar la caja.');

            $summary = $this->computeSummary($session);
            $expected = $summary['balance_actual'];
            $counted = round((float) ($payload['counted_cash'] ?? 0), 4);
            $difference = round($counted - $expected, 4);
            $breakdown = $payload['closing_breakdown'] ?? null;
            $note = $this->nullableText($payload['closing_note'] ?? null);

            CashCount::create([
                'cash_session_id' => $session->id,
                'type' => 'CIERRE',
                'counted_by' => $userId,
                'expected_amount' => $expected,
                'counted_amount' => $counted,
                'difference' => $difference,
                'breakdown' => $breakdown,
                'note' => $note,
            ]);

            $session->status = 'CERRADA';
            $session->closed_by = $userId;
            $session->closed_at = now();
            $session->expected_cash = $expected;
            $session->counted_cash = $counted;
            $session->cash_difference = $difference;
            $session->closing_breakdown = $breakdown;
            $session->closing_note = $note;
            $session->save();

            return $session->fresh(['cashRegister', 'branch', 'openedBy', 'closedBy']);
        });
    }

    /**
     * Resumen financiero en vivo de una apertura. Separa ventas por medio de
     * pago (usando los flags cash/card/bank de payment_methods), movimientos
     * manuales, costo de mercancia vendida y balance de efectivo actual.
     *
     * balance_actual = monto_inicial + ventas_efectivo + ingresos_por_creditos
     *                   + ingresos_manuales - egresos_manuales - devoluciones_efectivo
     *
     * "ingresos_por_creditos" y "devoluciones" son movimientos de caja
     * generados automaticamente por el sistema (no manuales): un abono en
     * efectivo sobre una venta "Pendiente de pago"
     * (SalesService::registerPendingPayment()) crea un CashMovement INGRESO
     * ligado a `sale_payments`; el reintegro en efectivo de una Nota de
     * Credito (CreditNoteService) crea un CashMovement EGRESO ligado a
     * `sales_returns`. Ambos SI quedan incluidos en `manual_income`/
     * `manual_expense` a nivel de CashMovement (son movimientos reales de
     * caja), pero se separan aca en su propia linea para que el resumen no
     * mezcle "cobros de facturas pendientes"/"devoluciones" con ingresos o
     * egresos manuales discrecionales (fondo adicional, retiro, etc.) — por
     * eso a `manual_income`/`manual_expense` se les resta la parte que ya
     * quedo contabilizada en estas dos lineas, para no duplicarla al sumar
     * el desglose completo.
     */
    public function computeSummary(CashSession $session): array
    {
        $sales = Sale::query()
            ->where('cash_session_id', $session->id)
            ->where('status', 'COMPLETED')
            ->with('paymentMethod:id,cash,card,bank')
            ->get(['id', 'total', 'discount', 'tax', 'subtotal', 'payment_method_id']);

        $cashSales = 0.0;
        $cardSales = 0.0;
        $transferSales = 0.0;
        $otherSales = 0.0;
        $creditSales = 0.0;
        $discounts = 0.0;
        $taxes = 0.0;
        $netSales = 0.0;

        foreach ($sales as $sale) {
            $total = (float) $sale->total;
            $discounts += (float) $sale->discount;
            $taxes += (float) $sale->tax;
            $netSales += $total;
            $paymentMethod = $sale->paymentMethod;

            if (! $sale->payment_method_id || ! $paymentMethod) {
                $creditSales += $total;
            } elseif ($paymentMethod->cash) {
                $cashSales += $total;
            } elseif ($paymentMethod->card) {
                $cardSales += $total;
            } elseif ($paymentMethod->bank) {
                $transferSales += $total;
            } else {
                $otherSales += $total;
            }
        }

        $costOfGoods = (float) DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.cash_session_id', $session->id)
            ->where('sales.status', 'COMPLETED')
            ->selectRaw('COALESCE(SUM(sale_items.quantity * sale_items.unit_cost), 0) as total')
            ->value('total');

        $activeMovements = CashMovement::query()
            ->where('cash_session_id', $session->id)
            ->where('status', 'ACTIVO')
            ->get(['type', 'amount']);

        $totalIncome = (float) $activeMovements->where('type', 'INGRESO')->sum('amount');
        $totalExpense = (float) $activeMovements->where('type', 'EGRESO')->sum('amount');

        // Cobros en efectivo de facturas "Pendiente de pago" (abonos) —
        // ver SalePayment.cash_session_id, solo se llena cuando el abono
        // fue en efectivo y habia sesion abierta.
        $creditCollections = (float) SalePayment::query()
            ->where('cash_session_id', $session->id)
            ->sum('amount');

        // Reintegros en efectivo de Notas de Credito emitidas durante esta
        // sesion (ver CreditNoteService) — el total de la nota, no solo el
        // monto del CashMovement, para que cuadre 1:1 con "returns".
        $returns = (float) SalesReturn::query()
            ->whereNotNull('cash_movement_id')
            ->whereHas('cashMovement', fn ($query) => $query->where('cash_session_id', $session->id)->where('status', 'ACTIVO'))
            ->sum('total');

        $manualIncome = round($totalIncome - $creditCollections, 4);
        $manualExpense = round($totalExpense - $returns, 4);

        $pendingAuthorizationCount = CashMovement::query()
            ->where('cash_session_id', $session->id)
            ->where('status', 'PENDIENTE_AUTORIZACION')
            ->count();

        $openingAmount = (float) $session->opening_amount;

        $balanceActual = round($openingAmount + $cashSales + $creditCollections + $manualIncome - $manualExpense - $returns, 4);
        $grossProfit = round($netSales - $discounts - $costOfGoods, 4);
        // Se resta manual_expense Y returns por separado (antes era un solo
        // "manual_expense" que ya incluia las devoluciones) para no cambiar
        // el total restado ahora que se dividieron en dos lineas.
        $netProfit = round($grossProfit - $manualExpense - $returns, 4);

        return [
            'opening_amount' => round($openingAmount, 4),
            'cash_sales' => round($cashSales, 4),
            'card_sales' => round($cardSales, 4),
            'transfer_sales' => round($transferSales, 4),
            'other_sales' => round($otherSales, 4),
            'credit_sales' => round($creditSales, 4),
            'credit_collections' => round($creditCollections, 4),
            'net_sales' => round($netSales, 4),
            'discounts' => round($discounts, 4),
            'taxes' => round($taxes, 4),
            'returns' => round($returns, 4),
            'cost_of_goods' => round($costOfGoods, 4),
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'manual_income' => round($manualIncome, 4),
            'manual_expense' => round($manualExpense, 4),
            'pending_authorization_count' => (int) $pendingAuthorizationCount,
            'balance_actual' => $balanceActual,
            'sales_count' => $sales->count(),
        ];
    }

    /**
     * Panel para administradores: todas las cajas/aperturas de la empresa
     * (opcionalmente filtradas), con balance en vivo de cada una.
     */
    public function monitor(int $companyId, array $filters = []): array
    {
        $query = CashSession::query()
            ->where('company_id', $companyId)
            ->with(['cashRegister:id,name,code,branch_id', 'branch:id,name', 'openedBy:id,full_name', 'terminal:id,name']);

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->whereIn('status', ['ABIERTA', 'EN_ARQUEO']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('opened_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('opened_at', '<=', $filters['date_to']);
        }

        $sessions = $query->orderByDesc('opened_at')->get();

        $rows = $sessions->map(function (CashSession $session) {
            $isLive = in_array($session->status, ['ABIERTA', 'EN_ARQUEO'], true);
            $summary = $isLive ? $this->computeSummary($session) : null;

            return [
                'id' => $session->id,
                'status' => $session->status,
                'cash_register' => $session->cashRegister ? [
                    'id' => $session->cashRegister->id,
                    'name' => $session->cashRegister->name,
                    'code' => $session->cashRegister->code,
                ] : null,
                'branch' => $session->branch ? ['id' => $session->branch->id, 'name' => $session->branch->name] : null,
                'opened_by' => $session->openedBy ? ['id' => $session->openedBy->id, 'full_name' => $session->openedBy->full_name] : null,
                'terminal' => $session->terminal ? ['id' => $session->terminal->id, 'name' => $session->terminal->name] : null,
                'opened_at' => $session->opened_at?->toIso8601String(),
                'closed_at' => $session->closed_at?->toIso8601String(),
                'opening_amount' => (float) $session->opening_amount,
                'balance_actual' => $isLive ? $summary['balance_actual'] : (float) ($session->counted_cash ?? $session->expected_cash ?? 0),
                'net_sales' => $isLive ? $summary['net_sales'] : 0.0,
                'cash_difference' => $session->cash_difference !== null ? (float) $session->cash_difference : null,
            ];
        });

        return [
            'sessions' => $rows->values()->all(),
            'totals' => [
                'sessions_count' => $rows->count(),
                'open_count' => $sessions->where('status', 'ABIERTA')->count(),
                'total_cash' => round($rows->sum('balance_actual'), 4),
                'total_opening' => round($rows->sum('opening_amount'), 4),
                'total_sales' => round($rows->sum('net_sales'), 4),
            ],
        ];
    }

    public function currentSessionForUser(int $companyId, int $userId): ?CashSession
    {
        return CashSession::query()
            ->where('company_id', $companyId)
            ->where('opened_by', $userId)
            ->where('status', 'ABIERTA')
            ->with(['cashRegister', 'branch', 'terminal'])
            ->latest('opened_at')
            ->first();
    }

    /**
     * Registra o actualiza la terminal que origina una solicitud (patron de
     * "self check-in": el frontend genera y persiste un codigo estable en
     * localStorage la primera vez, y lo reenvia siempre). Sirve tanto para
     * abrir caja como para dejar rastro de auditoria (IP, SO, navegador,
     * ultima conexion, usuario actual).
     */
    public function checkinTerminal(int $companyId, ?int $branchId, int $userId, array $payload): Terminal
    {
        $code = trim((string) ($payload['terminal_code'] ?? ''));
        abort_if($code === '', 422, 'Falta el identificador de la terminal.');

        $terminal = Terminal::query()->where('company_id', $companyId)->where('code', $code)->first();
        $userAgent = trim((string) ($payload['user_agent'] ?? ''));
        [$os, $browser] = $this->parseUserAgent($userAgent);

        $attributes = array_filter([
            'branch_id' => $branchId,
            'name' => $this->nullableText($payload['terminal_name'] ?? null),
            'device_name' => $this->nullableText($payload['device_name'] ?? null),
            'ip_address' => $this->nullableText($payload['ip'] ?? null),
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'os_info' => $os,
            'browser_info' => $browser,
        ], fn ($value) => $value !== null);

        $attributes['last_seen_at'] = now();
        $attributes['current_user_id'] = $userId;

        if ($terminal) {
            $terminal->update($attributes);

            return $terminal->fresh();
        }

        return Terminal::create(array_merge($attributes, [
            'company_id' => $companyId,
            'code' => $code,
            'status' => 'ACTIVA',
            'created_by' => $userId,
        ]));
    }

    public function syncAuthorizedUsers(CashRegister $register, array $userIds): Collection
    {
        DB::table('cash_register_users')->where('cash_register_id', $register->id)->delete();

        $rows = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($rows->isNotEmpty()) {
            DB::table('cash_register_users')->insert(
                $rows->map(fn ($id) => [
                    'cash_register_id' => $register->id,
                    'user_id' => $id,
                    'created_at' => now(),
                ])->all()
            );
        }

        return $rows;
    }

    /**
     * Una caja "abierta a cualquiera" (sin lista de usuarios autorizados
     * configurada) puede ser operada por cualquiera con el permiso base del
     * modulo; en cuanto se define al menos un usuario autorizado, la caja
     * queda restringida a esa lista (mas quien tenga ver_todas).
     */
    public function userCanOperate(CashRegister $register, int $userId, bool $canViewAll = false): bool
    {
        if ($canViewAll) {
            return true;
        }

        $hasAuthorizedList = DB::table('cash_register_users')->where('cash_register_id', $register->id)->exists();

        if (! $hasAuthorizedList) {
            return true;
        }

        return DB::table('cash_register_users')
            ->where('cash_register_id', $register->id)
            ->where('user_id', $userId)
            ->exists();
    }

    private function resolveRegister(int $companyId, int $registerId): CashRegister
    {
        $register = CashRegister::query()->where('company_id', $companyId)->where('id', $registerId)->first();

        abort_unless($register, 422, 'La caja seleccionada no existe.');

        return $register;
    }

    private function parseUserAgent(string $userAgent): array
    {
        if ($userAgent === '') {
            return [null, null];
        }

        $os = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') && ! str_contains($userAgent, 'Chrome') => 'Safari',
            default => null,
        };

        return [$os, $browser];
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
