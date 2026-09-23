<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot de la garantia del producto al momento de vender (no una
     * referencia en vivo a products.*): la garantia de un producto puede
     * cambiar despues, pero la factura ya emitida debe seguir mostrando
     * los terminos con los que realmente se vendio. warranty_expires_at
     * se calcula una sola vez al confirmar la venta (sale_date +
     * warranty_days) — desde ahi la garantia "esta corriendo".
     */
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->boolean('has_warranty')->default(false)->after('total');
            $table->unsignedInteger('warranty_days')->nullable()->after('has_warranty');
            $table->string('warranty_period_unit', 10)->nullable()->after('warranty_days');
            $table->string('warranty_type', 120)->nullable()->after('warranty_period_unit');
            $table->date('warranty_expires_at')->nullable()->after('warranty_type');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['has_warranty', 'warranty_days', 'warranty_period_unit', 'warranty_type', 'warranty_expires_at']);
        });
    }
};
