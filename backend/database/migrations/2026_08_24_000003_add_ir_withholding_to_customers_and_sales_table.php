<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retencion en la fuente del IR (Nicaragua/DGI): si un cliente es un
     * agente retenedor (gran contribuyente u otro designado por la DGI),
     * al pagar de contado retiene un % del monto a cuenta del IR del
     * vendedor (2% es la tasa estandar para compraventa de bienes/
     * servicios en general, Art. 44 del Reglamento de la Ley 822). La
     * factura mantiene su total completo; lo que cambia es cuanto
     * efectivo/banco se recibe realmente (ver SalesService::finalizeSale()
     * y JournalPostingService::postSale()).
     */
    public function up(): void
    {
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (! Schema::hasColumn('customers', 'ir_withholding_agent')) {
                    $table->boolean('ir_withholding_agent')->default(false)->after('discount_rate');
                }
                if (! Schema::hasColumn('customers', 'ir_withholding_rate')) {
                    $table->decimal('ir_withholding_rate', 5, 2)->default(2.00)->after('ir_withholding_agent');
                }
            });
        }

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (! Schema::hasColumn('sales', 'ir_withholding_rate')) {
                    $table->decimal('ir_withholding_rate', 5, 2)->nullable()->after('exchange_rate');
                }
                if (! Schema::hasColumn('sales', 'ir_withholding_amount')) {
                    $table->decimal('ir_withholding_amount', 18, 4)->default(0)->after('ir_withholding_rate');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                foreach (['ir_withholding_amount', 'ir_withholding_rate'] as $column) {
                    if (Schema::hasColumn('sales', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                foreach (['ir_withholding_rate', 'ir_withholding_agent'] as $column) {
                    if (Schema::hasColumn('customers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
