<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Registro de movimientos de kardex (inventory_movements) + actualizacion
 * del stock actual (inventory_stock). Extraido de SalesService::recordMovement()
 * (que antes era privado y hardcodeaba reference_table => 'sales') para que
 * otros servicios que tambien mueven inventario (CreditNoteService, para
 * devoluciones de Notas de Credito) lo puedan reutilizar sin depender de
 * SalesService.
 */
class InventoryMovementService
{
    /**
     * $movementType 'SALE_EXIT' descuenta stock; cualquier otro valor
     * (RETURN_IN, etc.) lo incrementa — mismo vocabulario que ya usaba
     * SalesService.
     */
    public function record(
        int $companyId,
        int $warehouseId,
        int $productId,
        string $movementType,
        float $quantity,
        float $unitCost,
        int $userId,
        string $referenceTable,
        int $referenceId,
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
            'reference_table' => $referenceTable,
            'reference_id' => $referenceId,
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
}
