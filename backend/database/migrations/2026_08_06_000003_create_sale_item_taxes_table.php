<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Desglose de impuestos por línea de venta (permite más de un impuesto
     * por producto, ej. IVA + impuesto selectivo), en vez del campo plano
     * sale_items.tax.
     */
    public function up(): void
    {
        if (! Schema::hasTable('sale_item_taxes')) {
            Schema::create('sale_item_taxes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_item_id')->constrained('sale_items')->cascadeOnDelete();
                $table->foreignId('tax_id')->constrained('taxes')->restrictOnDelete();
                $table->decimal('rate', 8, 4);
                $table->decimal('taxable_base', 18, 4);
                $table->decimal('tax_amount', 18, 4);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_item_taxes');
    }
};
