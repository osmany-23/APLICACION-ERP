<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columnas necesarias para que Facturación funcione con doble partida:
     * numeración por tipo de documento, saldo pendiente de cobro, y enlace
     * al asiento contable y a la cuenta por cobrar generados al confirmar
     * la venta. sale_items.unit_cost guarda el costo promedio capturado al
     * momento de la venta para poder reproducir el asiento de costo de venta.
     */
    public function up(): void
    {
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (! Schema::hasColumn('sales', 'document_type_id')) {
                    $table->foreignId('document_type_id')->nullable()->after('customer_id')
                        ->constrained('document_types')->nullOnDelete();
                }
                if (! Schema::hasColumn('sales', 'paid_amount')) {
                    $table->decimal('paid_amount', 18, 4)->default(0)->after('total');
                }
                if (! Schema::hasColumn('sales', 'balance_due')) {
                    $table->decimal('balance_due', 18, 4)->default(0)->after('paid_amount');
                }
                if (! Schema::hasColumn('sales', 'accounts_receivable_id')) {
                    $table->foreignId('accounts_receivable_id')->nullable()->after('balance_due')
                        ->constrained('accounts_receivable')->nullOnDelete();
                }
                if (! Schema::hasColumn('sales', 'journal_entry_id')) {
                    $table->foreignId('journal_entry_id')->nullable()->after('accounts_receivable_id')
                        ->constrained('journal_entries')->nullOnDelete()
                        ->comment('Asiento contable de ingreso generado al confirmar la venta');
                }
            });
        }

        if (Schema::hasTable('sale_items')) {
            Schema::table('sale_items', function (Blueprint $table) {
                if (! Schema::hasColumn('sale_items', 'unit_cost')) {
                    $table->decimal('unit_cost', 18, 4)->nullable()->after('unit_price')
                        ->comment('Costo promedio capturado al momento de la venta');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sale_items') && Schema::hasColumn('sale_items', 'unit_cost')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->dropColumn('unit_cost');
            });
        }

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                foreach (['journal_entry_id', 'accounts_receivable_id', 'document_type_id'] as $column) {
                    if (Schema::hasColumn('sales', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
                foreach (['balance_due', 'paid_amount'] as $column) {
                    if (Schema::hasColumn('sales', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
