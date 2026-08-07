<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountsReceivable;
use App\Models\Customer;
use App\Models\DocumentType;
use App\Models\JournalEntry;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class SalesService
{
    private const ACC_CXC = '1103';

    public function __construct(private JournalPostingService $journalPosting)
    {
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

            [$lines, $subtotal, $discountTotal, $taxTotal] = $this->buildLines($companyId, $warehouse, $payload['items']);

            $subtotal = round($subtotal, 4);
            $discountTotal = round($discountTotal, 4);
            $taxTotal = round($taxTotal, 4);
            $total = round($subtotal - $discountTotal + $taxTotal, 4);

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
                'tax' => $taxTotal,
                'total' => $total,
                'currency_id' => $currencyId,
                'exchange_rate' => $exchangeRate,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($lines as $line) {
                $saleItem = $sale->items()->create([
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'unit_cost' => $line['unit_cost'],
                    'discount' => $line['discount'],
                    'tax' => $line['tax_amount'],
                    'subtotal' => $line['subtotal'],
                    'total' => $line['total'],
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
    public function cancelSale(Sale $sale, int $userId): Sale
    {
        return DB::transaction(function () use ($sale, $userId) {
            abort_if($sale->status === 'CANCELLED', 409, 'La venta ya esta anulada.');

            if ($sale->accounts_receivable_id) {
                $receivable = AccountsReceivable::find($sale->accounts_receivable_id);

                if ($receivable && (float) $receivable->paid_amount > 0) {
                    abort(409, 'No se puede anular una venta con pagos aplicados.');
                }
            }

            if ($sale->status === 'COMPLETED') {
                foreach ($sale->items()->get() as $item) {
                    $product = DB::table('products')
                        ->where('company_id', $sale->company_id)
                        ->where('id', $item->product_id)
                        ->first();

                    if ($product && (bool) $product->is_inventory && $sale->warehouse_id) {
                        $this->recordMovement(
                            (int) $sale->company_id,
                            (int) $sale->warehouse_id,
                            (int) $item->product_id,
                            'RETURN_IN',
                            (float) $item->quantity,
                            (float) ($item->unit_cost ?? $product->cost),
                            $userId,
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
            $sale->save();

            return $sale->fresh(['items.taxes']);
        });
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
        $costTotal = 0.0;

        if ($isCredit) {
            $customer = Customer::findOrFail($sale->customer_id);
            $creditLimit = (float) $customer->credit_limit;
            $projectedBalance = round((float) $customer->current_balance + (float) $sale->total, 4);

            if (! $overrideCreditLimit && ($creditLimit <= 0 || $projectedBalance > $creditLimit)) {
                abort(422, 'El cliente excede su limite de credito disponible.');
            }
        }

        foreach ($sale->items()->get() as $item) {
            $product = DB::table('products')
                ->where('company_id', $companyId)
                ->where('id', $item->product_id)
                ->first();

            abort_unless($product, 422, 'Uno de los productos de la venta ya no existe.');

            $unitCost = (float) ($item->unit_cost ?? $product->cost);
            $costTotal += (float) $item->quantity * $unitCost;

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

                $this->recordMovement(
                    $companyId,
                    (int) $sale->warehouse_id,
                    (int) $item->product_id,
                    'SALE_EXIT',
                    (float) $item->quantity,
                    $unitCost,
                    $userId,
                    (int) $sale->id,
                    $stockRow,
                );
            }
        }

        if ($isCredit) {
            $receivable = AccountsReceivable::create([
                'company_id' => $companyId,
                'customer_id' => $sale->customer_id,
                'sale_id' => $sale->id,
                'accounting_account_id' => AccountingAccount::byCode($companyId, self::ACC_CXC)->id,
                'document_number' => $sale->sale_number,
                'doc_date' => $sale->sale_date,
                'due_date' => $this->resolveDueDate((string) $sale->sale_date, $sale->payment_term_id),
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
        }

        $journalEntry = $this->journalPosting->postSale($sale, $costTotal, $isCredit, $userId);
        $sale->journal_entry_id = $journalEntry->id;
        $sale->status = 'COMPLETED';
        $sale->save();

        return $sale->fresh(['items.taxes']);
    }

    /**
     * Resuelve producto, precio, costo (snapshot de solo lectura de
     * inventory_stock.average_cost) e impuestos por linea. No bloquea ni
     * mueve inventario: eso ocurre unicamente en finalizeSale().
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float, 2: float, 3: float}
     */
    private function buildLines(int $companyId, object $warehouse, array $items): array
    {
        abort_if(empty($items), 422, 'La venta debe tener al menos una linea.');

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
            abort_unless((bool) $product->allow_sale, 422, "El producto {$product->short_name} no esta habilitado para venta.");

            $quantity = (float) ($itemInput['quantity'] ?? 0);
            abort_if($quantity <= 0, 422, 'La cantidad debe ser mayor que cero.');

            $unitPrice = isset($itemInput['unit_price']) ? (float) $itemInput['unit_price'] : (float) $product->sale_price;
            $discount = (float) ($itemInput['discount'] ?? 0);
            $lineBase = round(($quantity * $unitPrice) - $discount, 4);
            abort_if($lineBase < 0, 422, 'El descuento no puede ser mayor que el importe de la linea.');

            [$taxId, $taxRate, $taxAmount] = $this->resolveLineTax($product, $lineBase);

            $unitCost = (float) $product->cost;

            if ((bool) $product->is_inventory) {
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

    private function resolveDueDate(string $saleDate, ?int $paymentTermId): string
    {
        if (! $paymentTermId) {
            return $saleDate;
        }

        $days = (int) (DB::table('payment_terms')->where('id', $paymentTermId)->value('days') ?? 0);

        return date('Y-m-d', strtotime($saleDate.' +'.$days.' days'));
    }

    private function fallbackSaleNumber(int $companyId): string
    {
        $count = DB::table('sales')->where('company_id', $companyId)->count();

        return 'VTA-'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }

    private function recordMovement(
        int $companyId,
        int $warehouseId,
        int $productId,
        string $movementType,
        float $quantity,
        float $unitCost,
        int $userId,
        int $saleId,
        ?object $stockRow = null,
    ): void {
        $isExit = $movementType === 'SALE_EXIT';

        $stockRow ??= DB::table('inventory_stock')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        $stockBefore = $stockRow ? (float) $stockRow->quantity : 0.0;
        $stockAfter = round($isExit ? $stockBefore - $quantity : $stockBefore + $quantity, 4);

        DB::table('inventory_movements')->insert([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_type' => $movementType,
            'reference_table' => 'sales',
            'reference_id' => $saleId,
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
        } else {
            DB::table('inventory_stock')->insert([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'quantity' => $stockAfter,
                'average_cost' => $unitCost,
                'last_movement_date' => now(),
            ]);
        }
    }

    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
