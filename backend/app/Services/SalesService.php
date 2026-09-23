<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountsReceivable;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\DocumentType;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SalesService
{
    private const ACC_CXC = '1103';

    public function __construct(
        private JournalPostingService $journalPosting,
        private InventoryMovementService $inventoryMovements,
        private CashRegisterService $cashRegister,
        private CompanySettingsService $companySettings,
    ) {
    }

    /**
     * Crea una venta (cabecera + lineas + impuestos por linea) dentro de una
     * transaccion. Si $payload['confirm'] es true (por defecto), la venta se
     * confirma de inmediato: se mueve inventario, se crea la cuenta por
     * cobrar (si es a credito) y se postea el asiento contable. Si falla
     * cualquier paso (stock insuficiente, limite de credito excedido,
     * asiento descuadrado), todo se revierte.
     */
    public function createSale(int $companyId, int $userId, array $payload): Sale
    {
        return DB::transaction(function () use ($companyId, $userId, $payload) {
            $customer = $this->resolveCustomer($companyId, (int) $payload['customer_id']);
            $warehouse = $this->resolveWarehouse($companyId, (int) $payload['warehouse_id']);
            $documentType = $this->resolveDocumentType($payload['document_type_id'] ?? null);

            $paymentMethodId = ! empty($payload['payment_method_id']) ? (int) $payload['payment_method_id'] : null;
            $currencyId = (int) ($payload['currency_id'] ?? $customer->currency_id ?? 1);
            $exchangeRate = (float) ($payload['exchange_rate'] ?? 1);
            $saleDate = $payload['sale_date'] ?? now()->toDateString();
            $confirm = array_key_exists('confirm', $payload) ? (bool) $payload['confirm'] : true;
            $branchId = $payload['branch_id'] ?? $warehouse->branch_id ?? null;

            // "Pendiente de pago" (contra entrega): se factura y se entrega
            // el producto ya, pero el cobro real se confirma despues a
            // mano (ver confirmPaymentReceived()). No es lo mismo que
            // credito real del cliente (no toca su limite/balance) ni que
            // un pago de contado ya recibido, por eso no debe traer
            // payment_method_id todavia, y tampoco tiene sentido dejarla
            // sin confirmar como un borrador.
            $isPendingPayment = (bool) ($payload['is_pending_payment'] ?? false);

            if ($isPendingPayment && $paymentMethodId !== null) {
                abort(422, 'Una venta pendiente de pago no debe tener un metodo de pago; este se define al confirmar el cobro.');
            }
            if ($isPendingPayment && ! $confirm) {
                abort(422, 'Una venta pendiente de pago debe confirmarse de inmediato, no puede quedar en borrador.');
            }

            $paymentReference = $this->nullableTrim($payload['payment_reference'] ?? null);

            // Tarjeta (credito/debito) o cualquier metodo marcado como
            // "requires_reference" (transferencia, cheque, deposito) exige
            // un codigo de referencia para poder reimprimir el ticket con
            // esa constancia — igual que ya se le exige al abonar una venta
            // pendiente de pago (ver PaymentMethodsSeeder).
            if ($paymentMethodId !== null && ! $isPendingPayment) {
                $paymentMethod = PaymentMethod::find($paymentMethodId);

                if ($paymentMethod && ($paymentMethod->card || $paymentMethod->requires_reference) && $paymentReference === null) {
                    abort(422, 'Esta forma de pago requiere un codigo de referencia.');
                }
            }

            // Un cliente puede pagar con una mezcla de billetes en ambas
            // monedas a la vez (ej. un billete de $10 y uno de C$500 en la
            // misma venta) — se guardan por separado en vez de forzarlos a
            // una sola moneda "dominante". La venta siempre se contabiliza
            // en la moneda base; lo recibido en moneda extranjera solo
            // sirve para reconstruir el ticket y calcular el vuelto (que
            // PosTerminal.tsx ya calculo en base y manda en change_amount).
            $amountTenderedBase = array_key_exists('amount_tendered_base', $payload) && $payload['amount_tendered_base'] !== null
                ? (float) $payload['amount_tendered_base']
                : null;
            $amountTenderedForeign = array_key_exists('amount_tendered_foreign', $payload) && $payload['amount_tendered_foreign'] !== null
                ? (float) $payload['amount_tendered_foreign']
                : null;
            $changeAmount = array_key_exists('change_amount', $payload) && $payload['change_amount'] !== null
                ? (float) $payload['change_amount']
                : null;

            $salespersonId = $this->resolveSalespersonId($companyId, $userId, $payload['salesperson_id'] ?? null);

            [$lines, $subtotal, $discountTotal, $taxTotal] = $this->buildLines($companyId, $warehouse, $payload['items'], $salespersonId);

            $subtotal = round($subtotal, 4);
            $discountTotal = round($discountTotal, 4);
            $taxTotal = round($taxTotal, 4);
            $shipping = round((float) ($payload['shipping'] ?? 0), 4);
            $total = round($subtotal - $discountTotal + $taxTotal + $shipping, 4);

            $saleNumber = $documentType
                ? $documentType->generateDocumentNumber($documentType)
                : $this->fallbackSaleNumber($companyId);

            $sale = Sale::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouse->id,
                'customer_id' => $customer->id,
                'document_type_id' => $documentType->id ?? null,
                'payment_term_id' => $payload['payment_term_id'] ?? $customer->payment_term_id,
                'payment_method_id' => $paymentMethodId,
                'sale_number' => $saleNumber,
                'sale_date' => $saleDate,
                'status' => 'DRAFT',
                'subtotal' => $subtotal,
                'discount' => $discountTotal,
                'shipping' => $shipping,
                'tax' => $taxTotal,
                'total' => $total,
                'currency_id' => $currencyId,
                'exchange_rate' => $exchangeRate,
                'payment_reference' => $paymentReference,
                'amount_tendered_base' => $amountTenderedBase,
                'amount_tendered_foreign' => $amountTenderedForeign,
                'change_amount' => $changeAmount,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $userId,
                'salesperson_id' => $salespersonId,
                'cash_session_id' => $this->resolveOpenCashSessionId($companyId, $userId),
            ]);

            foreach ($lines as $line) {
                $product = $line['product'];
                // La garantia se copia del producto (no se referencia en
                // vivo): si luego cambian los terminos de garantia del
                // producto, las facturas ya emitidas deben seguir
                // mostrando con lo que realmente se vendio. Empieza a
                // "correr" desde la fecha de venta, tal como lo pidio el
                // usuario.
                $hasWarranty = (bool) ($product->has_warranty ?? false);
                $warrantyDays = $hasWarranty ? (int) ($product->warranty_days ?? 0) : null;

                $saleItem = $sale->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'unit_cost' => $line['unit_cost'],
                    'discount' => $line['discount'],
                    'tax' => $line['tax_amount'],
                    'subtotal' => $line['subtotal'],
                    'total' => $line['total'],
                    'has_warranty' => $hasWarranty && $warrantyDays > 0,
                    'warranty_days' => $warrantyDays,
                    'warranty_period_unit' => $hasWarranty ? ($product->warranty_period_unit ?: 'DAYS') : null,
                    'warranty_type' => $hasWarranty ? $product->warranty_type : null,
                    'warranty_expires_at' => ($hasWarranty && $warrantyDays > 0)
                        ? date('Y-m-d', strtotime($saleDate.' +'.$warrantyDays.' days'))
                        : null,
                ]);

                if ($line['tax_id'] && $line['tax_amount'] > 0) {
                    $saleItem->taxes()->create([
                        'tax_id' => $line['tax_id'],
                        'rate' => $line['tax_rate'],
                        'taxable_base' => $line['subtotal'],
                        'tax_amount' => $line['tax_amount'],
                    ]);
                }
            }

            if (! $confirm) {
                return $sale->fresh(['items.taxes']);
            }

            if ($isPendingPayment) {
                return $this->finalizeSaleAsPending($sale, $userId);
            }

            return $this->finalizeSale($sale, $userId, (bool) ($payload['override_credit_limit'] ?? false));
        });
    }

    /**
     * Confirma (postea) una venta que quedo en estado DRAFT: mueve
     * inventario, crea la cuenta por cobrar si aplica, y postea el asiento.
     */
    public function confirmSale(Sale $sale, int $userId, bool $overrideCreditLimit = false): Sale
    {
        return DB::transaction(fn () => $this->finalizeSale($sale->fresh(), $userId, $overrideCreditLimit));
    }

    /**
     * Anula una venta. Nunca borra el asiento contable ya posteado (lo marca
     * VOID) ni el kardex (revierte el inventario con un movimiento
     * contrario), para mantener la trazabilidad contable integra.
     */
    /**
     * Anula una venta. Exige el codigo (PIN) de alguien con permiso para
     * administrar/eliminar facturacion — el usuario que ejecuta la accion
     * (logueado en el navegador) puede no ser quien tiene autoridad para
     * aprobarla, por eso se pide un segundo codigo aparte (ver
     * resolveAuthorizedCanceller()). Revierte inventario (afecta el
     * kardex via inventory_movements), anula el asiento contable y la
     * cuenta por cobrar si existian. El impacto en caja se corrige solo:
     * el resumen de una sesion de caja se recalcula en vivo filtrando
     * status='COMPLETED' (ver CashRegisterService::computeSummary()), asi
     * que en cuanto la venta pasa a CANCELLED deja de contar ahi sin
     * necesidad de tocar ningun movimiento de caja.
     */
    public function cancelSale(Sale $sale, int $userId, string $authorizationPin): Sale
    {
        return DB::transaction(function () use ($sale, $userId, $authorizationPin) {
            abort_if($sale->status === 'CANCELLED', 409, 'La venta ya esta anulada.');

            $authorizer = $this->resolveAuthorizedCanceller((int) $sale->company_id, $authorizationPin);

            if ($sale->accounts_receivable_id) {
                $receivable = AccountsReceivable::find($sale->accounts_receivable_id);

                if ($receivable && (float) $receivable->paid_amount > 0) {
                    abort(409, 'No se puede anular una venta con pagos aplicados.');
                }
            }

            // PENDING (pendiente de pago) tambien revierte inventario: el
            // producto ya salio de bodega al facturar, aunque el cobro
            // nunca se haya confirmado. journal_entry_id/accounts_receivable_id
            // siempre son null en ese caso (no se llegan a crear hasta
            // confirmar el cobro), asi que los bloques de abajo no hacen
            // nada extra para una venta PENDING — ya vienen protegidos con
            // "if ($sale->journal_entry_id)"/"if ($sale->accounts_receivable_id)".
            if (in_array($sale->status, ['COMPLETED', 'PENDING'], true)) {
                foreach ($sale->items()->get() as $item) {
                    $product = DB::table('products')
                        ->where('company_id', $sale->company_id)
                        ->where('id', $item->product_id)
                        ->first();

                    if (! $product || ! $sale->warehouse_id) {
                        continue;
                    }

                    // Un combo/kit no tiene existencia propia: anular su
                    // venta devuelve el inventario de cada componente
                    // (ver moveInventoryForKitComponents(), que fue lo que
                    // realmente se descargo al confirmar la venta) en vez
                    // de intentar devolver el kit mismo.
                    if ((bool) $product->is_kit) {
                        $this->reverseKitInventory($sale, $product, (float) $item->quantity, $userId);

                        continue;
                    }

                    if ((bool) $product->is_inventory) {
                        $this->inventoryMovements->record(
                            (int) $sale->company_id,
                            (int) $sale->warehouse_id,
                            (int) $item->product_id,
                            'RETURN_IN',
                            (float) $item->quantity,
                            (float) ($item->unit_cost ?? $product->cost),
                            $userId,
                            'sales',
                            (int) $sale->id,
                        );
                    }
                }

                if ($sale->journal_entry_id) {
                    JournalEntry::find($sale->journal_entry_id)?->void();
                }

                if ($sale->accounts_receivable_id) {
                    AccountsReceivable::where('id', $sale->accounts_receivable_id)->update(['status' => 'VOID']);
                    Customer::query()->where('id', $sale->customer_id)->decrement('current_balance', (float) $sale->total);
                }
            }

            $sale->status = 'CANCELLED';
            $sale->cancelled_at = now();
            $sale->cancelled_by = $userId;
            $sale->cancellation_authorized_by = $authorizer->id;
            $sale->save();

            return $sale->fresh(['items.taxes']);
        });
    }

    /**
     * Valida el PIN de un administrador contra los usuarios activos de la
     * empresa (mismo mecanismo de hash que PosPinController::resolve(), no
     * se puede consultar por hash directo porque bcrypt salta cada uno) y
     * exige ademas que ese usuario tenga permiso para administrar o
     * eliminar facturacion — un vendedor comun puede tener acceso de
     * "ver"/"manage" sobre facturacion (para emitir/consultar) pero no
     * "eliminar"/"administrar" (ver SecuritySeeder.php), asi que el PIN de
     * un vendedor normal no basta para anular.
     */
    private function resolveAuthorizedCanceller(int $companyId, string $pin): User
    {
        $candidates = User::query()
            ->where('company_id', $companyId)
            ->where('status', 1)
            ->whereNotNull('pin_hash')
            ->get(['id', 'full_name', 'pin_hash', 'role_id']);

        $match = $candidates->first(fn (User $candidate) => Hash::check($pin, $candidate->pin_hash));

        abort_if(! $match, 422, 'Codigo de autorizacion invalido.');

        $hasAuthority = DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $match->role_id)
            ->where('permissions.module_name', 'facturacion')
            ->whereIn('permissions.action_name', ['eliminar', 'administrar'])
            ->exists();

        abort_if(! $hasAuthority, 403, 'El codigo ingresado no tiene autorizacion para anular facturas.');

        return $match;
    }

    /**
     * Mueve inventario (si aplica), crea la cuenta por cobrar si la venta es
     * a credito, postea el asiento contable, y marca la venta COMPLETED.
     */
    private function finalizeSale(Sale $sale, int $userId, bool $overrideCreditLimit = false): Sale
    {
        abort_if($sale->status === 'COMPLETED', 409, 'La venta ya esta confirmada.');
        abort_if($sale->status === 'CANCELLED', 409, 'La venta esta anulada y no se puede confirmar.');

        $companyId = (int) $sale->company_id;
        $isCredit = $sale->payment_method_id === null;

        if ($isCredit) {
            $customer = Customer::findOrFail($sale->customer_id);
            $creditLimit = (float) $customer->credit_limit;
            $projectedBalance = round((float) $customer->current_balance + (float) $sale->total, 4);

            if (! $overrideCreditLimit && ($creditLimit <= 0 || $projectedBalance > $creditLimit)) {
                abort(422, 'El cliente excede su limite de credito disponible.');
            }
        }

        $costTotal = $this->moveInventoryForSale($sale, $userId);

        if ($isCredit) {
            $receivable = AccountsReceivable::create([
                'company_id' => $companyId,
                'customer_id' => $sale->customer_id,
                'sale_id' => $sale->id,
                'accounting_account_id' => AccountingAccount::byCode($companyId, self::ACC_CXC)->id,
                'document_number' => $sale->sale_number,
                'doc_date' => $sale->sale_date,
                'due_date' => $this->resolveDueDate((string) $sale->sale_date, $sale->payment_term_id, (int) $customer->credit_days),
                'currency_id' => $sale->currency_id,
                'exchange_rate' => $sale->exchange_rate,
                'total_amount' => $sale->total,
                'paid_amount' => 0,
                'balance' => $sale->total,
                'status' => 'PENDING',
            ]);

            $sale->accounts_receivable_id = $receivable->id;
            $sale->balance_due = $sale->total;
            $sale->paid_amount = 0;

            Customer::query()->where('id', $sale->customer_id)->increment('current_balance', (float) $sale->total);
        } else {
            $sale->paid_amount = $sale->total;
            $sale->balance_due = 0;

            // Retencion en la fuente del IR (Nicaragua/DGI): si el cliente
            // es un agente retenedor, al pagar de contado retiene un % del
            // monto a cuenta de su propio IR y le da al vendedor una
            // constancia de retencion — la factura sigue "pagada" por el
            // total completo (no es saldo pendiente), pero el efectivo/
            // banco que realmente entra es total - retencion (ver
            // JournalPostingService::postSale(), que divide la linea de
            // debito en dos por esta misma razon). Solo aplica a ventas de
            // contado en esta version — una venta a credito no tiene un
            // "momento de pago" todavia del cual retener.
            $customer ??= Customer::find($sale->customer_id);

            if ($customer && $customer->ir_withholding_agent) {
                $rate = (float) $customer->ir_withholding_rate;
                $sale->ir_withholding_rate = $rate;
                $sale->ir_withholding_amount = round((float) $sale->total * $rate / 100, 4);
            }
        }

        $journalEntry = $this->journalPosting->postSale($sale, $costTotal, $isCredit, $userId);
        $sale->journal_entry_id = $journalEntry->id;
        $sale->status = 'COMPLETED';
        $sale->save();

        return $sale->fresh(['items.taxes']);
    }

    /**
     * Descuenta el inventario de cada linea de la venta (si el producto es
     * inventariable), validando stock suficiente segun allow_negative_stock.
     * Extraido de finalizeSale() para poder reutilizarlo en
     * finalizeSaleAsPending(): una venta "pendiente de pago" mueve
     * inventario de inmediato (el producto ya se entrega), aunque el cobro
     * se confirme despues. Devuelve el costo total de la venta (para el
     * asiento contable, que se postea aca mismo o mas adelante segun el
     * flujo).
     */
    private function moveInventoryForSale(Sale $sale, int $userId): float
    {
        $companyId = (int) $sale->company_id;
        $costTotal = 0.0;

        foreach ($sale->items()->get() as $item) {
            $product = DB::table('products')
                ->where('company_id', $companyId)
                ->where('id', $item->product_id)
                ->first();

            abort_unless($product, 422, 'Uno de los productos de la venta ya no existe.');

            $unitCost = (float) ($item->unit_cost ?? $product->cost);
            $costTotal += (float) $item->quantity * $unitCost;

            if ((bool) $product->is_kit) {
                $this->moveInventoryForKitComponents($sale, $product, (float) $item->quantity, $userId);

                continue;
            }

            if ((bool) $product->is_inventory) {
                abort_unless($sale->warehouse_id, 422, 'La venta no tiene un almacen asignado.');

                $stockRow = DB::table('inventory_stock')
                    ->where('company_id', $companyId)
                    ->where('warehouse_id', $sale->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                $available = $stockRow ? ((float) $stockRow->quantity - (float) $stockRow->reserved_quantity) : 0.0;

                if (! (bool) $product->allow_negative_stock && $available < (float) $item->quantity) {
                    abort(422, sprintf(
                        'Stock insuficiente para %s. Disponible: %s, solicitado: %s.',
                        $product->short_name,
                        $this->trimNumber($available),
                        $this->trimNumber((float) $item->quantity),
                    ));
                }

                $this->inventoryMovements->record(
                    $companyId,
                    (int) $sale->warehouse_id,
                    (int) $item->product_id,
                    'SALE_EXIT',
                    (float) $item->quantity,
                    $unitCost,
                    $userId,
                    'sales',
                    (int) $sale->id,
                    $stockRow,
                );
            }
        }

        return $costTotal;
    }

    /**
     * Todos los productos que componen un kit (product_kit_items),
     * ordenados por id de componente para que el orden de bloqueo de filas
     * (lockForUpdate) sea siempre el mismo y dos ventas simultaneas del
     * mismo kit no puedan hacer deadlock entre si.
     */
    private function kitComponents(int $companyId, int $kitProductId)
    {
        return DB::table('product_kit_items')
            ->where('company_id', $companyId)
            ->where('kit_product_id', $kitProductId)
            ->orderBy('component_product_id')
            ->get();
    }

    /**
     * Costo de un kit = suma del costo de cada componente (su costo
     * promedio en ese almacen si es inventariable, o su costo de lista si
     * no) multiplicado por la cantidad que el kit necesita de cada uno.
     */
    private function kitUnitCost(int $companyId, int $kitProductId, int $warehouseId): float
    {
        $cost = 0.0;

        foreach ($this->kitComponents($companyId, $kitProductId) as $component) {
            $componentProduct = DB::table('products')
                ->where('company_id', $companyId)
                ->where('id', $component->component_product_id)
                ->first();

            if (! $componentProduct) {
                continue;
            }

            $componentCost = (float) $componentProduct->cost;

            if ((bool) $componentProduct->is_inventory) {
                $averageCost = DB::table('inventory_stock')
                    ->where('company_id', $companyId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $componentProduct->id)
                    ->value('average_cost');

                if ($averageCost !== null) {
                    $componentCost = (float) $averageCost;
                }
            }

            $cost += $componentCost * (float) $component->quantity;
        }

        return round($cost, 4);
    }

    /**
     * Un kit no tiene existencia propia: al venderlo se descuenta el
     * inventario de cada componente (product_kit_items), multiplicado por
     * la cantidad de kits vendidos — con la misma validacion de stock
     * suficiente / allow_negative_stock que un producto normal. La reversa
     * (cancelSale()) hace exactamente lo mismo al reves, ver
     * reverseKitInventory().
     */
    private function moveInventoryForKitComponents(Sale $sale, object $kitProduct, float $kitQuantity, int $userId): void
    {
        $companyId = (int) $sale->company_id;
        $components = $this->kitComponents($companyId, (int) $kitProduct->id);

        abort_if($components->isEmpty(), 422, "El kit {$kitProduct->short_name} no tiene productos configurados.");

        foreach ($components as $component) {
            $componentProduct = DB::table('products')
                ->where('company_id', $companyId)
                ->where('id', $component->component_product_id)
                ->first();

            abort_unless($componentProduct, 422, 'Uno de los productos del kit ya no existe.');

            if (! (bool) $componentProduct->is_inventory) {
                continue;
            }

            abort_unless($sale->warehouse_id, 422, 'La venta no tiene un almacen asignado.');

            $neededQuantity = round((float) $component->quantity * $kitQuantity, 4);

            $stockRow = DB::table('inventory_stock')
                ->where('company_id', $companyId)
                ->where('warehouse_id', $sale->warehouse_id)
                ->where('product_id', $componentProduct->id)
                ->lockForUpdate()
                ->first();

            $available = $stockRow ? ((float) $stockRow->quantity - (float) $stockRow->reserved_quantity) : 0.0;

            if (! (bool) $componentProduct->allow_negative_stock && $available < $neededQuantity) {
                abort(422, sprintf(
                    'Stock insuficiente para %s (componente del kit %s). Disponible: %s, requerido: %s.',
                    $componentProduct->short_name,
                    $kitProduct->short_name,
                    $this->trimNumber($available),
                    $this->trimNumber($neededQuantity),
                ));
            }

            $unitCost = $stockRow && $stockRow->average_cost !== null ? (float) $stockRow->average_cost : (float) $componentProduct->cost;

            $this->inventoryMovements->record(
                $companyId,
                (int) $sale->warehouse_id,
                (int) $componentProduct->id,
                'SALE_EXIT',
                $neededQuantity,
                $unitCost,
                $userId,
                'sales',
                (int) $sale->id,
                $stockRow,
            );
        }
    }

    /**
     * Reversa de moveInventoryForKitComponents() al anular una venta:
     * devuelve a cada componente inventariable la cantidad que se le
     * desconto (cantidad del kit x kits vendidos). Se usa desde
     * cancelSale() para que anular una venta con combos/kits deje el
     * inventario exactamente como estaba antes de venderlos.
     */
    private function reverseKitInventory(Sale $sale, object $kitProduct, float $kitQuantity, int $userId): void
    {
        $companyId = (int) $sale->company_id;

        foreach ($this->kitComponents($companyId, (int) $kitProduct->id) as $component) {
            $componentProduct = DB::table('products')
                ->where('company_id', $companyId)
                ->where('id', $component->component_product_id)
                ->first();

            if (! $componentProduct || ! (bool) $componentProduct->is_inventory) {
                continue;
            }

            $neededQuantity = round((float) $component->quantity * $kitQuantity, 4);

            $this->inventoryMovements->record(
                $companyId,
                (int) $sale->warehouse_id,
                (int) $componentProduct->id,
                'RETURN_IN',
                $neededQuantity,
                (float) $componentProduct->cost,
                $userId,
                'sales',
                (int) $sale->id,
            );
        }
    }

    /**
     * Finaliza una venta como "Pendiente de pago" (contra entrega): mueve
     * inventario de inmediato (igual que una venta normal), pero a
     * diferencia de finalizeSale() NO crea cuenta por cobrar (no es
     * credito real del cliente, no debe afectar su limite/balance) y NO
     * postea el asiento contable todavia — eso ocurre recien cuando se
     * confirma el cobro real via confirmPaymentReceived(). La venta queda
     * en status 'PENDING', que el dashboard y el resumen de caja ya
     * excluyen automaticamente (ambos solo cuentan 'COMPLETED').
     */
    private function finalizeSaleAsPending(Sale $sale, int $userId): Sale
    {
        abort_if($sale->status === 'COMPLETED', 409, 'La venta ya esta confirmada.');
        abort_if($sale->status === 'CANCELLED', 409, 'La venta esta anulada y no se puede confirmar.');

        $this->moveInventoryForSale($sale, $userId);

        $sale->paid_amount = 0;
        $sale->balance_due = $sale->total;
        $sale->status = 'PENDING';
        $sale->save();

        return $sale->fresh(['items.taxes']);
    }

    /**
     * Registra un abono (parcial o por el saldo completo) sobre una venta
     * "Pendiente de pago": recien aca se define el metodo de pago real
     * (efectivo, transferencia, etc. — siempre validado contra el catalogo
     * de la propia empresa). Si el metodo es efectivo y el usuario tiene
     * una sesion de caja abierta EN ESTE MOMENTO, el abono ademas genera un
     * movimiento de caja (INGRESO) para que el corte del dia lo refleje.
     * Cuando el saldo llega a cero (en uno o varios abonos, posiblemente
     * con metodos de pago distintos) se postea recien ahi el asiento
     * contable real y la venta pasa a 'COMPLETED' — desde ahi cuenta en
     * caja, dashboard y reportes, igual que cualquier venta de contado.
     * Reemplaza al viejo "confirmar pago recibido" (que solo permitia
     * pagar el saldo completo de una sola vez): abonar el saldo entero en
     * un solo abono logra exactamente lo mismo.
     */
    public function registerPendingPayment(
        Sale $sale,
        float $amount,
        int $paymentMethodId,
        int $userId,
        ?string $reference = null,
        ?string $notes = null,
    ): Sale {
        return DB::transaction(function () use ($sale, $amount, $paymentMethodId, $userId, $reference, $notes) {
            $sale = $sale->fresh(['items']);
            $companyId = (int) $sale->company_id;

            abort_if($sale->status !== 'PENDING', 409, 'Solo se puede abonar una venta Pendiente de pago.');

            // payment_methods es un catalogo global con overrides opcionales
            // por empresa (company_id nullable) — mismo criterio que ya usa
            // el resto del sistema (ver PaymentMethodController): una fila
            // sin company_id es valida para cualquier empresa, una fila CON
            // company_id solo es valida para esa empresa puntual.
            $method = PaymentMethod::query()
                ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $companyId))
                ->where('id', $paymentMethodId)
                ->where('is_active', true)
                ->first();

            abort_unless($method, 422, 'El metodo de pago indicado no es valido.');

            $amount = round($amount, 4);
            $balanceDue = round((float) $sale->balance_due, 4);
            abort_if($amount <= 0, 422, 'El monto del abono debe ser mayor que cero.');
            abort_if($amount > $balanceDue + 0.0001, 422, sprintf(
                'El monto no puede superar el saldo pendiente (%s).',
                number_format($balanceDue, 2),
            ));

            $cashSessionId = $this->resolveOpenCashSessionId($companyId, $userId);
            $cashMovementId = null;

            if ($method->cash && $cashSessionId) {
                $session = CashSession::find($cashSessionId);

                if ($session) {
                    $movement = $this->cashRegister->recordAutomaticMovement(
                        $session,
                        $userId,
                        'INGRESO',
                        $amount,
                        "Abono a factura {$sale->sale_number}",
                    );
                    $cashMovementId = $movement->id;
                }
            }

            $payment = SalePayment::create([
                'company_id' => $companyId,
                'sale_id' => $sale->id,
                'payment_method_id' => $method->id,
                'amount' => $amount,
                'cash_session_id' => $method->cash ? $cashSessionId : null,
                'cash_movement_id' => $cashMovementId,
                'reference' => $reference,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            $sale->paid_amount = round((float) $sale->paid_amount + $amount, 4);
            $sale->balance_due = max(0, round($balanceDue - $amount, 4));

            if ($sale->balance_due <= 0.0001) {
                $costTotal = (float) $sale->items->sum(
                    fn ($item) => (float) $item->quantity * (float) ($item->unit_cost ?? 0),
                );

                // Los abonos de esta venta pueden haber usado metodos de
                // pago distintos a lo largo del tiempo (efectivo hoy,
                // transferencia despues); se agrupan por si van a Caja o a
                // Bancos para que el asiento final quede correcto sin
                // asumir un unico metodo de pago.
                $payments = SalePayment::where('sale_id', $sale->id)->with('paymentMethod')->get();
                $cashAmount = (float) $payments->filter(fn ($p) => (bool) $p->paymentMethod?->cash)->sum('amount');
                $bankAmount = (float) $payments->filter(fn ($p) => ! $p->paymentMethod?->cash)->sum('amount');

                $journalEntry = $this->journalPosting->postSaleCollection($sale, $costTotal, $cashAmount, $bankAmount, $userId);
                $sale->journal_entry_id = $journalEntry->id;
                $sale->status = 'COMPLETED';
                $sale->payment_confirmed_at = now();
                $sale->payment_confirmed_by = $userId;
                // Metodo de pago "representativo" (el del abono que la
                // completa) solo para que las vistas que leen
                // payment_method_id/transaction_status (listados, recibo)
                // dejen de mostrarla como "credito" — no para el calculo de
                // caja. cash_session_id se deja explicitamente en null: el
                // impacto real en caja de cada abono ya quedo registrado al
                // momento de cada uno via CashMovement (arriba); si se
                // dejara aca el cash_session_id de la creacion (posible-
                // mente distinto al de cuando se completo, o inclusive el
                // mismo re-abierto), CashRegisterService::computeSummary()
                // volveria a sumar el total completo de la venta en
                // "cash_sales" y duplicaria el efectivo ya contado por los
                // movimientos.
                $sale->payment_method_id = $method->id;
                $sale->cash_session_id = null;
            }

            $sale->save();

            return $sale->fresh(['items.taxes', 'payments']);
        });
    }

    /**
     * Resuelve producto, precio, costo (snapshot de solo lectura de
     * inventory_stock.average_cost) e impuestos por linea. No bloquea ni
     * mueve inventario: eso ocurre unicamente en finalizeSale().
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float, 2: float, 3: float}
     */
    private function buildLines(int $companyId, object $warehouse, array $items, int $salespersonId): array
    {
        abort_if(empty($items), 422, 'La venta debe tener al menos una linea.');

        $lines = [];
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;

        $sellerMaxDiscount = User::query()->where('id', $salespersonId)->value('max_discount_percentage');
        $sellerMaxDiscountPercent = $sellerMaxDiscount !== null ? (float) $sellerMaxDiscount : null;

        foreach ($items as $itemInput) {
            $product = DB::table('products')
                ->where('company_id', $companyId)
                ->where('id', (int) ($itemInput['product_id'] ?? 0))
                ->first();

            abort_unless($product, 422, 'Uno de los productos seleccionados no existe.');
            abort_unless((bool) $product->allow_sale, 422, "El producto {$product->short_name} no esta habilitado para venta.");

            $quantity = (float) ($itemInput['quantity'] ?? 0);
            abort_if($quantity <= 0, 422, 'La cantidad debe ser mayor que cero.');

            $minimumAllowedPrice = $this->resolveMinimumAllowedPrice($product, $quantity);
            $unitPrice = isset($itemInput['unit_price']) ? (float) $itemInput['unit_price'] : $minimumAllowedPrice;

            // El precio unitario nunca puede quedar por debajo del precio
            // minimo que corresponde a esa cantidad (el tier de mayoreo
            // aplicable, o el precio base si ninguno aplica). Esto NO
            // limita el campo "descuento": una rebaja puntual negociada
            // sigue siendo posible ahi, solo que queda registrada aparte en
            // vez de disfrazada como un precio unitario equivocado.
            abort_if(
                $unitPrice < $minimumAllowedPrice - 0.0001,
                422,
                sprintf(
                    'El precio unitario de "%s" (%s) no puede ser menor al precio minimo permitido (%s) para una cantidad de %s.',
                    $product->short_name,
                    number_format($unitPrice, 2),
                    number_format($minimumAllowedPrice, 2),
                    rtrim(rtrim(number_format($quantity, 4), '0'), '.'),
                ),
            );

            $discount = (float) ($itemInput['discount'] ?? 0);

            if ($discount > 0) {
                $maxDiscount = $this->resolveMaxLineDiscount($product, $quantity, $unitPrice, $sellerMaxDiscountPercent, $companyId);

                if ($maxDiscount !== null && $discount > $maxDiscount + 0.0001) {
                    abort(422, sprintf(
                        'El descuento para "%s" (%s) supera el maximo permitido (%s).',
                        $product->short_name,
                        number_format($discount, 2),
                        number_format($maxDiscount, 2),
                    ));
                }
            }

            $lineBase = round(($quantity * $unitPrice) - $discount, 4);
            abort_if($lineBase < 0, 422, 'El descuento no puede ser mayor que el importe de la linea.');

            [$taxId, $taxRate, $taxAmount] = $this->resolveLineTax($product, $lineBase);

            $unitCost = (float) $product->cost;

            if ((bool) $product->is_kit) {
                // Un kit no tiene costo propio confiable (no se le exige
                // llenarlo, no tiene existencia): su costo es la suma de lo
                // que cuesta cada componente, en la cantidad que el kit
                // necesita de cada uno.
                $unitCost = $this->kitUnitCost($companyId, (int) $product->id, (int) $warehouse->id);
            } elseif ((bool) $product->is_inventory) {
                $averageCost = DB::table('inventory_stock')
                    ->where('company_id', $companyId)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $product->id)
                    ->value('average_cost');

                if ($averageCost !== null) {
                    $unitCost = (float) $averageCost;
                }
            }

            $lines[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'unit_cost' => $unitCost,
                'discount' => $discount,
                'subtotal' => $lineBase,
                'tax_id' => $taxId,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => round($lineBase + $taxAmount, 4),
            ];

            $subtotal += $quantity * $unitPrice;
            $discountTotal += $discount;
            $taxTotal += $taxAmount;
        }

        return [$lines, $subtotal, $discountTotal, $taxTotal];
    }

    /**
     * Precio minimo permitido para vender $product en la cantidad $quantity:
     * el precio por volumen (product_price_list_items) mas especifico que
     * la cantidad alcanza, o el precio base (sale_price) si ninguno aplica.
     * "Mas especifico" = el que tenga la min_quantity mas alta que siga
     * siendo <= $quantity (el escalon mas profundo alcanzado); si dos tipos
     * de precio distintos comparten esa misma min_quantity, se desempata
     * por el precio mas bajo y luego por id, para que el resultado sea
     * siempre determinista.
     */
    private function resolveMinimumAllowedPrice(object $product, float $quantity): float
    {
        $tierPrice = DB::table('product_price_list_items')
            ->join('price_lists', 'price_lists.id', '=', 'product_price_list_items.price_list_id')
            ->where('product_price_list_items.product_id', $product->id)
            ->whereNull('product_price_list_items.variant_id')
            ->where('product_price_list_items.is_active', true)
            ->where('price_lists.is_active', true)
            ->where('price_lists.company_id', $product->company_id)
            ->where('product_price_list_items.min_quantity', '<=', $quantity)
            ->orderByDesc('product_price_list_items.min_quantity')
            ->orderBy('product_price_list_items.price')
            ->orderBy('product_price_list_items.id')
            ->value('product_price_list_items.price');

        return $tierPrice !== null ? (float) $tierPrice : (float) $product->sale_price;
    }

    /**
     * Tope de descuento (en cordobas, para esa linea completa) que puede
     * darse en esta venta, combinando hasta dos limites independientes:
     *
     * - El del producto (products.max_discount_type/max_discount_value): si
     *   el producto trae su propio limite especifico, ese manda y el tope
     *   general de Configuracion General NO se aplica para el (un producto
     *   con limite propio no hereda el general). Si es de tipo PERCENTAGE
     *   se calcula sobre el importe bruto de la linea (cantidad x precio
     *   unitario); si es FIXED, el valor configurado es por unidad y se
     *   escala por la cantidad. Si el producto no trae limite propio, se
     *   usa el tope general (sales.max_discount_percentage) solo si esta
     *   activado en Configuracion General.
     * - El del vendedor que hizo la venta (users.max_discount_percentage,
     *   resuelto sobre salesperson_id, no sobre el usuario logueado — ver
     *   resolveSalespersonId()): un limite personal que aplica sin importar
     *   que producto sea.
     *
     * Cuando ambos limites aplican se usa el MAS RESTRICTIVO (el menor de
     * los dos), nunca se suman ni se promedian. Devuelve null si ningun
     * limite aplica (descuento libre, igual que el comportamiento previo a
     * esta funcion).
     */
    private function resolveMaxLineDiscount(
        object $product,
        float $quantity,
        float $unitPrice,
        ?float $sellerMaxDiscountPercent,
        int $companyId,
    ): ?float {
        $lineGross = $quantity * $unitPrice;
        $limits = [];

        $productDiscountType = $product->max_discount_type ?? null;
        $productDiscountValue = $product->max_discount_value ?? null;

        if ($productDiscountType && $productDiscountValue !== null) {
            $limits[] = $productDiscountType === 'PERCENTAGE'
                ? $lineGross * ((float) $productDiscountValue / 100)
                : (float) $productDiscountValue * $quantity;
        } elseif ((bool) $this->companySettings->get($companyId, 'sales', 'max_discount_enabled', false)) {
            $generalPercent = (float) $this->companySettings->get($companyId, 'sales', 'max_discount_percentage', 100);
            $limits[] = $lineGross * ($generalPercent / 100);
        }

        if ($sellerMaxDiscountPercent !== null) {
            $limits[] = $lineGross * ($sellerMaxDiscountPercent / 100);
        }

        if (empty($limits)) {
            return null;
        }

        return round(min($limits), 4);
    }

    private function resolveLineTax(object $product, float $taxableBase): array
    {
        if (($product->tax_type ?? 'EXEMPT') !== 'TAXABLE' || $taxableBase <= 0) {
            return [null, 0.0, 0.0];
        }

        $pivotTaxId = DB::table('product_taxes')
            ->where('product_id', $product->id)
            ->orderByDesc('is_default')
            ->value('tax_id');

        $tax = $pivotTaxId
            ? DB::table('taxes')->where('id', $pivotTaxId)->first()
            : DB::table('taxes')->where('is_default', true)->where('is_active', true)->first();

        if (! $tax) {
            return [null, 0.0, 0.0];
        }

        $rate = (float) $tax->rate;
        $amount = round($taxableBase * $rate / 100, 4);

        return [(int) $tax->id, $rate, $amount];
    }

    private function resolveCustomer(int $companyId, int $customerId): Customer
    {
        $customer = Customer::query()->where('company_id', $companyId)->where('id', $customerId)->first();

        abort_unless($customer, 422, 'El cliente seleccionado no existe.');
        abort_unless((int) $customer->status === 1, 422, 'El cliente seleccionado esta inactivo.');

        return $customer;
    }

    /**
     * Si no se paso salesperson_id (flujo normal), la venta se atribuye al
     * usuario logueado, igual que siempre. Si se paso (resuelto via PIN en
     * PosPinController), se valida que pertenezca a la misma empresa antes
     * de confiar en el — nunca se atribuye una venta a un usuario de otra
     * empresa por un payload manipulado.
     */
    private function resolveSalespersonId(int $companyId, int $userId, mixed $rawSalespersonId): int
    {
        if ($rawSalespersonId === null || $rawSalespersonId === '') {
            return $userId;
        }

        $salespersonId = (int) $rawSalespersonId;
        $exists = User::query()->where('company_id', $companyId)->where('id', $salespersonId)->exists();

        abort_unless($exists, 422, 'El vendedor indicado no es valido.');

        return $salespersonId;
    }

    /**
     * Si el usuario que factura tiene una apertura de caja ABIERTA a su
     * nombre, la venta se enlaza a esa sesion (sales.cash_session_id) para
     * que el modulo de Caja pueda separar "ventas" de "movimientos de caja"
     * y calcular cuanto efectivo entro realmente a esa caja. Si no hay
     * ninguna abierta, la venta se registra igual sin apertura asociada:
     * el modulo de Caja es opcional y no bloquea la facturacion.
     */
    private function resolveOpenCashSessionId(int $companyId, int $userId): ?int
    {
        return CashSession::query()
            ->where('company_id', $companyId)
            ->where('opened_by', $userId)
            ->where('status', 'ABIERTA')
            ->value('id');
    }

    private function resolveWarehouse(int $companyId, int $warehouseId): object
    {
        $warehouse = DB::table('warehouses')
            ->where('company_id', $companyId)
            ->where('id', $warehouseId)
            ->first();

        abort_unless($warehouse, 422, 'El almacen seleccionado no existe.');

        return $warehouse;
    }

    private function resolveDocumentType(?int $documentTypeId): ?DocumentType
    {
        if ($documentTypeId) {
            $documentType = DocumentType::find($documentTypeId);
            abort_unless($documentType, 422, 'El tipo de documento seleccionado no existe.');

            return $documentType;
        }

        return DocumentType::where('code', 'FACT')->first();
    }

    /**
     * Si la venta no trae una condicion de pago explicita (payment_terms,
     * ej. "30 dias"), la fecha de vencimiento cae al plazo de credito
     * propio del cliente (customers.credit_days) — asi una venta a credito
     * siempre tiene una fecha de vencimiento real en vez de caer en "vence
     * el mismo dia" solo porque no se eligio una condicion de pago.
     */
    private function resolveDueDate(string $saleDate, ?int $paymentTermId, int $customerCreditDays = 0): string
    {
        if ($paymentTermId) {
            $days = (int) (DB::table('payment_terms')->where('id', $paymentTermId)->value('days') ?? 0);

            return date('Y-m-d', strtotime($saleDate.' +'.$days.' days'));
        }

        if ($customerCreditDays > 0) {
            return date('Y-m-d', strtotime($saleDate.' +'.$customerCreditDays.' days'));
        }

        return $saleDate;
    }

    private function fallbackSaleNumber(int $companyId): string
    {
        $count = DB::table('sales')->where('company_id', $companyId)->count();

        return 'VTA-'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }

    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
