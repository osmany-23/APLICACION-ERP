<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountsPayable;
use App\Models\JournalEntry;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

class PurchasesService
{
    private const ACC_CXP = '2101';

    public function __construct(private JournalPostingService $journalPosting)
    {
    }

    /**
     * Crea una compra (cabecera + lineas + impuestos por linea). Si
     * $payload['confirm'] es true (por defecto), la compra se confirma de
     * inmediato: se recibe la mercancia en inventario (recalculando el
     * costo promedio ponderado), se crea la cuenta por pagar (si es a
     * credito) y se postea el asiento contable.
     */
    public function createPurchase(int $companyId, int $userId, array $payload): Purchase
    {
        return DB::transaction(function () use ($companyId, $userId, $payload) {
            $supplier = $this->resolveSupplier($companyId, (int) $payload['supplier_id']);
            $warehouse = $this->resolveWarehouse($companyId, (int) $payload['warehouse_id']);

            $paymentMethodId = ! empty($payload['payment_method_id']) ? (int) $payload['payment_method_id'] : null;
            $currencyId = (int) ($payload['currency_id'] ?? $supplier->currency_id ?? 1);
            $exchangeRate = (float) ($payload['exchange_rate'] ?? 1);
            $purchaseDate = $payload['purchase_date'] ?? now()->toDateString();
            $confirm = array_key_exists('confirm', $payload) ? (bool) $payload['confirm'] : true;
            $branchId = $payload['branch_id'] ?? $warehouse->branch_id ?? null;

            [$lines, $subtotal, $discountTotal, $taxTotal] = $this->buildLines($companyId, $payload['items']);

            $subtotal = round($subtotal, 4);
            $discountTotal = round($discountTotal, 4);
            $taxTotal = round($taxTotal, 4);
            $total = round($subtotal - $discountTotal + $taxTotal, 4);

            $purchase = Purchase::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplier->id,
                'payment_term_id' => $payload['payment_term_id'] ?? $supplier->payment_term_id,
                'payment_method_id' => $paymentMethodId,
                'purchase_number' => $this->generatePurchaseNumber($companyId),
                'purchase_date' => $purchaseDate,
                'status' => 'DRAFT',
                'subtotal' => $subtotal,
                'discount' => $discountTotal,
                'tax' => $taxTotal,
                'total' => $total,
                'currency_id' => $currencyId,
                'exchange_rate' => $exchangeRate,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($lines as $line) {
                $purchaseItem = $purchase->items()->create([
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'discount' => $line['discount'],
                    'tax' => $line['tax_amount'],
                    'subtotal' => $line['subtotal'],
                    'total' => $line['total'],
                ]);

                if ($line['tax_id'] && $line['tax_amount'] > 0) {
                    $purchaseItem->taxes()->create([
                        'tax_id' => $line['tax_id'],
                        'rate' => $line['tax_rate'],
                        'taxable_base' => $line['subtotal'],
                        'tax_amount' => $line['tax_amount'],
                    ]);
                }
            }

            if (! $confirm) {
                return $purchase->fresh(['items.taxes']);
            }

            return $this->finalizePurchase($purchase, $userId);
        });
    }

    /**
     * Confirma (recibe) una compra que quedo en estado DRAFT.
     */
    public function confirmPurchase(Purchase $purchase, int $userId): Purchase
    {
        return DB::transaction(fn () => $this->finalizePurchase($purchase->fresh(), $userId));
    }

    /**
     * Anula una compra. Nunca borra el asiento posteado (lo marca VOID) ni
     * el kardex (revierte el inventario con un movimiento contrario).
     */
    public function cancelPurchase(Purchase $purchase, int $userId): Purchase
    {
        return DB::transaction(function () use ($purchase, $userId) {
            abort_if($purchase->status === 'CANCELLED', 409, 'La compra ya esta anulada.');

            if ($purchase->accounts_payable_id) {
                $payable = AccountsPayable::find($purchase->accounts_payable_id);

                if ($payable && (float) $payable->paid_amount > 0) {
                    abort(409, 'No se puede anular una compra con pagos aplicados.');
                }
            }

            if ($purchase->status === 'RECEIVED') {
                foreach ($purchase->items()->get() as $item) {
                    $product = DB::table('products')
                        ->where('company_id', $purchase->company_id)
                        ->where('id', $item->product_id)
                        ->first();

                    if ($product && (bool) $product->is_inventory && $purchase->warehouse_id) {
                        $this->recordReturnOut(
                            (int) $purchase->company_id,
                            (int) $purchase->warehouse_id,
                            (int) $item->product_id,
                            (float) $item->quantity,
                            (float) $item->unit_cost,
                            $userId,
                            (int) $purchase->id,
                        );
                    }
                }

                if ($purchase->journal_entry_id) {
                    JournalEntry::find($purchase->journal_entry_id)?->void();
                }

                if ($purchase->accounts_payable_id) {
                    AccountsPayable::where('id', $purchase->accounts_payable_id)->update(['status' => 'VOID']);
                }
            }

            $purchase->status = 'CANCELLED';
            $purchase->save();

            return $purchase->fresh(['items.taxes']);
        });
    }

    /**
     * Recibe la mercancia en inventario (recalculando el costo promedio
     * ponderado por almacen), crea la cuenta por pagar si la compra es a
     * credito, postea el asiento contable, y marca la compra RECEIVED.
     */
    private function finalizePurchase(Purchase $purchase, int $userId): Purchase
    {
        abort_if($purchase->status === 'RECEIVED', 409, 'La compra ya esta confirmada.');
        abort_if($purchase->status === 'CANCELLED', 409, 'La compra esta anulada y no se puede confirmar.');

        $companyId = (int) $purchase->company_id;
        $isCredit = $purchase->payment_method_id === null;

        foreach ($purchase->items()->get() as $item) {
            $product = DB::table('products')
                ->where('company_id', $companyId)
                ->where('id', $item->product_id)
                ->first();

            abort_unless($product, 422, 'Uno de los productos de la compra ya no existe.');

            if ((bool) $product->is_inventory) {
                abort_unless($purchase->warehouse_id, 422, 'La compra no tiene un almacen asignado.');

                $this->recordPurchaseEntry(
                    $companyId,
                    (int) $purchase->warehouse_id,
                    (int) $item->product_id,
                    (float) $item->quantity,
                    (float) $item->unit_cost,
                    $userId,
                    (int) $purchase->id,
                );
            }
        }

        if ($isCredit) {
            $payable = AccountsPayable::create([
                'company_id' => $companyId,
                'supplier_id' => $purchase->supplier_id,
                'purchase_id' => $purchase->id,
                'accounting_account_id' => AccountingAccount::byCode($companyId, self::ACC_CXP)->id,
                'document_number' => $purchase->purchase_number,
                'doc_date' => $purchase->purchase_date,
                'due_date' => $this->resolveDueDate((string) $purchase->purchase_date, $purchase->payment_term_id),
                'currency_id' => $purchase->currency_id,
                'exchange_rate' => $purchase->exchange_rate,
                'total_amount' => $purchase->total,
                'paid_amount' => 0,
                'balance' => $purchase->total,
                'status' => 'PENDING',
            ]);

            $purchase->accounts_payable_id = $payable->id;
            $purchase->balance_due = $purchase->total;
            $purchase->paid_amount = 0;
        } else {
            $purchase->paid_amount = $purchase->total;
            $purchase->balance_due = 0;
        }

        $journalEntry = $this->journalPosting->postPurchase($purchase, $isCredit, $userId);
        $purchase->journal_entry_id = $journalEntry->id;
        $purchase->status = 'RECEIVED';
        $purchase->save();

        return $purchase->fresh(['items.taxes']);
    }

    /**
     * Resuelve producto, costo e impuestos por linea. No mueve inventario:
     * eso ocurre unicamente en finalizePurchase().
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float, 2: float, 3: float}
     */
    private function buildLines(int $companyId, array $items): array
    {
        abort_if(empty($items), 422, 'La compra debe tener al menos una linea.');

        $lines = [];
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $itemInput) {
            $product = DB::table('products')
                ->where('company_id', $companyId)
                ->where('id', (int) ($itemInput['product_id'] ?? 0))
                ->first();

            abort_unless($product, 422, 'Uno de los productos seleccionados no existe.');
            abort_unless((bool) $product->allow_purchase, 422, "El producto {$product->short_name} no esta habilitado para compra.");

            $quantity = (float) ($itemInput['quantity'] ?? 0);
            abort_if($quantity <= 0, 422, 'La cantidad debe ser mayor que cero.');

            $unitCost = isset($itemInput['unit_cost']) ? (float) $itemInput['unit_cost'] : (float) $product->cost;
            $discount = (float) ($itemInput['discount'] ?? 0);
            $lineBase = round(($quantity * $unitCost) - $discount, 4);
            abort_if($lineBase < 0, 422, 'El descuento no puede ser mayor que el importe de la linea.');

            [$taxId, $taxRate, $taxAmount] = $this->resolveLineTax($product, $lineBase);

            $lines[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'discount' => $discount,
                'subtotal' => $lineBase,
                'tax_id' => $taxId,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => round($lineBase + $taxAmount, 4),
            ];

            $subtotal += $quantity * $unitCost;
            $discountTotal += $discount;
            $taxTotal += $taxAmount;
        }

        return [$lines, $subtotal, $discountTotal, $taxTotal];
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

    private function resolveSupplier(int $companyId, int $supplierId): object
    {
        $supplier = DB::table('suppliers')
            ->where('company_id', $companyId)
            ->where('id', $supplierId)
            ->first();

        abort_unless($supplier, 422, 'El proveedor seleccionado no existe.');
        abort_unless((int) $supplier->status === 1, 422, 'El proveedor seleccionado esta inactivo.');

        return $supplier;
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

    private function resolveDueDate(string $purchaseDate, ?int $paymentTermId): string
    {
        if (! $paymentTermId) {
            return $purchaseDate;
        }

        $days = (int) (DB::table('payment_terms')->where('id', $paymentTermId)->value('days') ?? 0);

        return date('Y-m-d', strtotime($purchaseDate.' +'.$days.' days'));
    }

    /**
     * Genera el correlativo de la compra (ej. COM-000001) reutilizando la
     * tabla number_sequences, con bloqueo pesimista para evitar duplicados.
     */
    private function generatePurchaseNumber(int $companyId): string
    {
        return DB::transaction(function () use ($companyId) {
            $sequence = DB::table('number_sequences')
                ->where('company_id', $companyId)
                ->where('code', 'COMPRA')
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                DB::table('number_sequences')->insert([
                    'company_id' => $companyId,
                    'code' => 'COMPRA',
                    'description' => 'Correlativo de compras',
                    'prefix' => 'COM',
                    'current_value' => 1,
                    'pad_length' => 6,
                    'reset_on' => 'NEVER',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $sequence = DB::table('number_sequences')
                    ->where('company_id', $companyId)
                    ->where('code', 'COMPRA')
                    ->lockForUpdate()
                    ->first();
            }

            $nextValue = (int) $sequence->current_value;
            $padLength = (int) ($sequence->pad_length ?? 6);
            $prefix = (string) ($sequence->prefix ?: 'COM');

            DB::table('number_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'current_value' => $nextValue + 1,
                    'updated_at' => now(),
                ]);

            return sprintf('%s-%s', $prefix, str_pad((string) $nextValue, $padLength, '0', STR_PAD_LEFT));
        });
    }

    private function recordPurchaseEntry(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $quantity,
        float $unitCost,
        int $userId,
        int $purchaseId,
    ): void {
        $stockRow = DB::table('inventory_stock')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        $stockBefore = $stockRow ? (float) $stockRow->quantity : 0.0;
        $previousAvgCost = $stockRow ? (float) $stockRow->average_cost : 0.0;
        $stockAfter = round($stockBefore + $quantity, 4);

        // Costo promedio ponderado: (stock actual * costo actual + compra * costo compra) / stock resultante.
        $newAverageCost = $stockAfter > 0
            ? round((($stockBefore * $previousAvgCost) + ($quantity * $unitCost)) / $stockAfter, 4)
            : $unitCost;

        DB::table('inventory_movements')->insert([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_type' => 'PURCHASE_ENTRY',
            'reference_table' => 'purchases',
            'reference_id' => $purchaseId,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 4),
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'movement_date' => now(),
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        if ($stockRow) {
            DB::table('inventory_stock')
                ->where('id', $stockRow->id)
                ->update([
                    'quantity' => $stockAfter,
                    'average_cost' => $newAverageCost,
                    'last_movement_date' => now(),
                ]);
        } else {
            DB::table('inventory_stock')->insert([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'quantity' => $stockAfter,
                'average_cost' => $newAverageCost,
                'last_movement_date' => now(),
            ]);
        }
    }

    private function recordReturnOut(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $quantity,
        float $unitCost,
        int $userId,
        int $purchaseId,
    ): void {
        $stockRow = DB::table('inventory_stock')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        $stockBefore = $stockRow ? (float) $stockRow->quantity : 0.0;
        $stockAfter = round($stockBefore - $quantity, 4);

        DB::table('inventory_movements')->insert([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_type' => 'RETURN_OUT',
            'reference_table' => 'purchases',
            'reference_id' => $purchaseId,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 4),
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'movement_date' => now(),
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        if ($stockRow) {
            DB::table('inventory_stock')
                ->where('id', $stockRow->id)
                ->update([
                    'quantity' => $stockAfter,
                    'last_movement_date' => now(),
                ]);
        }
    }
}
