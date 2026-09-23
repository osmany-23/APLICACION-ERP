<?php

namespace App\Services;

use App\Models\AccountsReceivable;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\DocumentType;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Illuminate\Support\Facades\DB;

/**
 * Nota de Credito: ajusta una factura ya emitida (COMPLETED) devolviendo
 * cantidades especificas de sus lineas. Se apoya en las tablas
 * `sales_returns`/`sales_return_items`, que existian en el schema desde el
 * inicio pero nunca se habian conectado a ningun servicio.
 */
class CreditNoteService
{
    public function __construct(
        private JournalPostingService $journalPosting,
        private InventoryMovementService $inventoryMovements,
        private CashRegisterService $cashRegister,
    ) {
    }

    /**
     * $payload: ['sale_id' => int, 'reason' => string, 'items' => [['sale_item_id' => int, 'quantity' => float], ...]]
     */
    public function createCreditNote(int $companyId, int $userId, array $payload): SalesReturn
    {
        return DB::transaction(function () use ($companyId, $userId, $payload) {
            $sale = Sale::query()
                ->with(['items.taxes', 'paymentMethod'])
                ->where('company_id', $companyId)
                ->where('id', (int) ($payload['sale_id'] ?? 0))
                ->first();

            abort_unless($sale, 422, 'La factura indicada no existe.');
            abort_unless($sale->status === 'COMPLETED', 422, 'Solo se pueden generar notas de credito sobre facturas emitidas.');

            $reason = trim((string) ($payload['reason'] ?? ''));
            abort_if($reason === '', 422, 'Debes indicar la razon de la nota de credito.');

            $itemsInput = $payload['items'] ?? [];
            abort_if(empty($itemsInput), 422, 'La nota de credito debe tener al menos una linea.');

            $lines = [];
            $subtotal = 0.0;
            $taxTotal = 0.0;

            foreach ($itemsInput as $itemInput) {
                $saleItem = $sale->items->firstWhere('id', (int) ($itemInput['sale_item_id'] ?? 0));
                abort_unless($saleItem, 422, 'Una de las lineas seleccionadas no pertenece a esta factura.');

                $quantity = (float) ($itemInput['quantity'] ?? 0);
                abort_if($quantity <= 0, 422, 'La cantidad a acreditar debe ser mayor que cero.');

                // SalesReturnItem no guarda el sale_item_id original (la
                // tabla ya existente solo tiene product_id), asi que "cuanto
                // ya se acredito de esta linea" se calcula por producto
                // dentro de la misma factura — suficiente en la practica
                // porque SalesService::buildLines() nunca genera dos lineas
                // para el mismo producto en una misma venta.
                $alreadyCredited = (float) SalesReturnItem::query()
                    ->whereHas('salesReturn', fn ($query) => $query->where('sale_id', $sale->id)->where('status', '!=', 'CANCELLED'))
                    ->where('product_id', $saleItem->product_id)
                    ->sum('quantity');

                $availableQuantity = round((float) $saleItem->quantity - $alreadyCredited, 4);

                abort_if($quantity > $availableQuantity + 0.0001, 422, sprintf(
                    'No puedes acreditar mas de lo vendido/disponible para este producto. Disponible: %s, solicitado: %s.',
                    rtrim(rtrim(number_format($availableQuantity, 4), '0'), '.'),
                    rtrim(rtrim(number_format($quantity, 4), '0'), '.'),
                ));

                // Se escala proporcionalmente desde la linea original: si se
                // acredita la mitad de la cantidad vendida, se acredita la
                // mitad del subtotal/impuesto de esa linea.
                $ratio = $quantity / (float) $saleItem->quantity;
                $lineUnitPrice = (float) $saleItem->unit_price;
                $lineDiscount = round((float) $saleItem->discount * $ratio, 4);
                $lineSubtotal = round(($quantity * $lineUnitPrice) - $lineDiscount, 4);
                $lineTax = round((float) $saleItem->tax * $ratio, 4);

                $lines[] = [
                    'product_id' => $saleItem->product_id,
                    'quantity' => $quantity,
                    'unit_price' => $lineUnitPrice,
                    'discount' => $lineDiscount,
                    'tax' => $lineTax,
                    'subtotal' => $lineSubtotal,
                    'total' => round($lineSubtotal + $lineTax, 4),
                    'unit_cost' => (float) $saleItem->unit_cost,
                ];

                $subtotal += $lineSubtotal;
                $taxTotal += $lineTax;
            }

            $subtotal = round($subtotal, 4);
            $taxTotal = round($taxTotal, 4);
            $total = round($subtotal + $taxTotal, 4);

            $documentType = DocumentType::where('code', 'NC')->first();
            abort_unless($documentType, 500, 'No esta configurado el tipo de documento de Nota de Credito.');
            $returnNumber = $documentType->generateDocumentNumber($documentType);

            $return = SalesReturn::create([
                'company_id' => $companyId,
                'branch_id' => $sale->branch_id,
                'customer_id' => $sale->customer_id,
                'sale_id' => $sale->id,
                'warehouse_id' => $sale->warehouse_id,
                'return_number' => $returnNumber,
                'return_date' => now()->toDateString(),
                'status' => 'PROCESSED',
                'subtotal' => $subtotal,
                'tax' => $taxTotal,
                'total' => $total,
                'reason' => $reason,
                'created_by' => $userId,
            ]);

            $costTotal = 0.0;

            foreach ($lines as $line) {
                SalesReturnItem::create([
                    'sales_return_id' => $return->id,
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount' => $line['discount'],
                    'tax' => $line['tax'],
                    'subtotal' => $line['subtotal'],
                    'total' => $line['total'],
                ]);

                $product = DB::table('products')->where('company_id', $companyId)->where('id', $line['product_id'])->first();

                if ($product && (bool) $product->is_inventory && $sale->warehouse_id) {
                    $this->inventoryMovements->record(
                        $companyId,
                        (int) $sale->warehouse_id,
                        (int) $line['product_id'],
                        'RETURN_IN',
                        $line['quantity'],
                        $line['unit_cost'],
                        $userId,
                        'sales_returns',
                        (int) $return->id,
                    );

                    $costTotal += $line['quantity'] * $line['unit_cost'];
                }
            }

            $journalEntry = $this->journalPosting->postCreditNote($return, $sale, $costTotal, $userId);
            $return->journal_entry_id = $journalEntry->id;

            if ($sale->accounts_receivable_id) {
                $receivable = AccountsReceivable::find($sale->accounts_receivable_id);

                if ($receivable) {
                    $receivable->total_amount = max(0, round((float) $receivable->total_amount - $total, 4));
                    $receivable->balance = max(0, round((float) $receivable->balance - $total, 4));
                    $receivable->save();
                }

                Customer::query()->where('id', $sale->customer_id)->decrement('current_balance', $total);
            } elseif ($sale->paymentMethod?->cash) {
                $cashSessionId = $this->resolveOpenCashSessionId($companyId, $userId);

                if ($cashSessionId) {
                    $session = CashSession::find($cashSessionId);

                    if ($session) {
                        $movement = $this->cashRegister->recordAutomaticMovement(
                            $session,
                            $userId,
                            'EGRESO',
                            $total,
                            "Nota de credito {$returnNumber} (factura {$sale->sale_number})",
                        );
                        $return->cash_movement_id = $movement->id;
                    }
                }
            }

            $return->save();

            return $return->fresh(['items']);
        });
    }

    private function resolveOpenCashSessionId(int $companyId, int $userId): ?int
    {
        return CashSession::query()
            ->where('company_id', $companyId)
            ->where('opened_by', $userId)
            ->where('status', 'ABIERTA')
            ->value('id');
    }
}
