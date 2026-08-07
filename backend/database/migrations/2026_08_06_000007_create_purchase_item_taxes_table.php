<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Desglose de impuestos por linea de compra (credito fiscal / IVA
     * acreditable), simetrico a sale_item_taxes.
     */
    public function up(): void
    {
        if (! Schema::hasTable('purchase_item_taxes')) {
            Schema::create('purchase_item_taxes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_item_id')->constrained('purchase_items')->cascadeOnDelete();
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
        Schema::dropIfExists('purchase_item_taxes');
    }
};
