<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cargo de envio/entrega, opcional, sumado al total de la venta (a
     * diferencia del descuento, que resta). Lo necesita el TPV para el
     * campo "Envio" del panel de cobro.
     */
    public function up(): void
    {
        if (Schema::hasTable('sales') && ! Schema::hasColumn('sales', 'shipping')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->decimal('shipping', 18, 4)->default(0)->after('discount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'shipping')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('shipping');
            });
        }
    }
};
