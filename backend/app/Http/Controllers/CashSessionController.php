<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Services\CashRegisterService;
use App\Traits\AuthorizesCashRegister;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashSessionController extends Controller
{
    use AuthorizesCashRegister;

    public function __construct(private CashRegisterService $service)
    {
    }

    /**
     * Historial de aperturas. Un usuario sin "ver_todas" solo ve las suyas
     * (las que el mismo abrio); con ese permiso ve las de toda la empresa.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeCajaView($request);
        $user = $this->authenticatedUser($request);
        $canViewAll = $this->canViewAllCajas($request);

        $query = CashSession::query()
            ->where('company_id', $user->company_id)
            ->with(['cashRegister:id,name,code', 'branch:id,name', 'openedBy:id,full_name', 'closedBy:id,full_name', 'terminal:id,name'])
            ->orderByDesc('opened_at');

        if (! $canViewAll) {
            $query->where('opened_by', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('cash_register_id')) {
            $query->where('cash_register_id', (int) $request->query('cash_register_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('opened_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('opened_at', '<=', $request->query('date_to'));
        }

        $sessions = $query->limit(200)->get()->map(fn (CashSession $session) => $this->listPayload($session));

        return response()->json(['data' => $sessions]);
    }

    /**
     * Apertura ABIERTA del usuario logueado (o null). Es el endpoint que
     * decide si el frontend muestra "Abrir caja" o el panel operativo.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $session = $this->service->currentSessionForUser((int) $user->company_id, (int) $user->id);

        if (! $session) {
            return response()->json(['item' => null]);
        }

        return response()->json(['item' => $this->detailPayload($session)]);
    }

    public function monitor(Request $request): JsonResponse
    {
        abort_unless($this->canViewAllCajas($request), 403, 'No tienes permiso para ver el monitor de todas las cajas.');
        $user = $this->authenticatedUser($request);

        $result = $this->service->monitor((int) $user->company_id, [
            'branch_id' => $request->query('branch_id'),
            'status' => $request->query('status'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ]);

        return response()->json($result);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorizeCajaView($request);
        $session = $this->findSessionForView($request, $id);

        return response()->json(['item' => $this->detailPayload($session)]);
    }

    public function summary(Request $request, int $id): JsonResponse
    {
        $this->authorizeCajaView($request);
        $session = $this->findSessionForView($request, $id);

        return response()->json(['item' => $this->service->computeSummary($session)]);
    }

    public function open(Request $request): JsonResponse
    {
        $this->authorizeCajaAction($request, 'abrir');
        $user = $this->authenticatedUser($request);

        $validated = $request->validate([
            'cash_register_id' => ['required', 'integer'],
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'opening_breakdown' => ['nullable', 'array'],
            'opening_note' => ['nullable', 'string'],
            'terminal_code' => ['nullable', 'string', 'max:40'],
            'terminal_name' => ['nullable', 'string', 'max:120'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.00000001'],
        ], [
            'cash_register_id.required' => 'Selecciona la caja que vas a abrir.',
            'opening_amount.required' => 'Ingresa el monto inicial.',
        ]);

        $session = $this->service->openSession(
            (int) $user->company_id,
            (int) $user->id,
            array_merge($validated, [
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]),
            $this->canViewAllCajas($request)
        );

        return response()->json([
            'message' => 'Caja abierta correctamente.',
            'item' => $this->detailPayload($session),
        ], 201);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $session = $this->findSessionForOperate($request, $id);
        $this->authorizeCajaAction($request, 'cerrar');

        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'closing_breakdown' => ['nullable', 'array'],
            'closing_note' => ['nullable', 'string'],
        ], [
            'counted_cash.required' => 'Ingresa el efectivo contado fisicamente.',
        ]);

        $session = $this->service->closeSession($session, (int) $user->id, $validated);

        return response()->json([
            'message' => 'Caja cerrada correctamente.',
            'item' => $this->detailPayload($session),
        ]);
    }

    public function count(Request $request, int $id): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $session = $this->findSessionForOperate($request, $id);
        $this->authorizeCajaAction($request, 'realizar_arqueo');

        $validated = $request->validate([
            'counted_amount' => ['required', 'numeric', 'min:0'],
            'breakdown' => ['nullable', 'array'],
            'note' => ['nullable', 'string'],
        ], [
            'counted_amount.required' => 'Ingresa el efectivo contado.',
        ]);

        $count = $this->service->recordCount($session, (int) $user->id, $validated);

        return response()->json([
            'message' => 'Arqueo registrado correctamente.',
            'item' => [
                'id' => $count->id,
                'expected_amount' => (float) $count->expected_amount,
                'counted_amount' => (float) $count->counted_amount,
                'difference' => (float) $count->difference,
                'note' => $count->note,
                'created_at' => $count->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function listPayload(CashSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status,
            'cash_register' => $session->cashRegister ? ['id' => $session->cashRegister->id, 'name' => $session->cashRegister->name, 'code' => $session->cashRegister->code] : null,
            'branch' => $session->branch ? ['id' => $session->branch->id, 'name' => $session->branch->name] : null,
            'terminal' => $session->terminal ? ['id' => $session->terminal->id, 'name' => $session->terminal->name] : null,
            'opened_by' => $session->openedBy ? ['id' => $session->openedBy->id, 'full_name' => $session->openedBy->full_name] : null,
            'closed_by' => $session->closedBy ? ['id' => $session->closedBy->id, 'full_name' => $session->closedBy->full_name] : null,
            'opening_amount' => (float) $session->opening_amount,
            'opened_at' => $session->opened_at?->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
            'expected_cash' => $session->expected_cash !== null ? (float) $session->expected_cash : null,
            'counted_cash' => $session->counted_cash !== null ? (float) $session->counted_cash : null,
            'cash_difference' => $session->cash_difference !== null ? (float) $session->cash_difference : null,
        ];
    }

    private function detailPayload(CashSession $session): array
    {
        $session->loadMissing(['cashRegister', 'branch', 'terminal', 'openedBy:id,full_name', 'closedBy:id,full_name']);

        $payload = $this->listPayload($session);
        $payload['opening_note'] = $session->opening_note;
        $payload['opening_breakdown'] = $session->opening_breakdown;
        $payload['closing_note'] = $session->closing_note;
        $payload['closing_breakdown'] = $session->closing_breakdown;
        $payload['currency_id'] = $session->currency_id;

        if (in_array($session->status, ['ABIERTA', 'EN_ARQUEO'], true)) {
            $payload['summary'] = $this->service->computeSummary($session);
        }

        return $payload;
    }

    private function findSessionForView(Request $request, int $id): CashSession
    {
        $user = $this->authenticatedUser($request);
        $session = CashSession::query()->where('company_id', $user->company_id)->where('id', $id)->first();

        abort_unless($session, 404, 'Apertura no encontrada.');

        if (! $this->canViewAllCajas($request) && (int) $session->opened_by !== (int) $user->id) {
            abort(403, 'No tienes permiso para ver la caja de otro usuario.');
        }

        return $session;
    }

    /**
     * Igual que findSessionForView, pero se usa antes de acciones de
     * escritura (cerrar/arquear) donde ademas hace falta el permiso puntual
     * de la accion — ese chequeo lo hace el metodo que la llama.
     */
    private function findSessionForOperate(Request $request, int $id): CashSession
    {
        return $this->findSessionForView($request, $id);
    }
}
