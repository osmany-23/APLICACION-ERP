<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lista de materiales (BOM) de un producto "Combo/Kit": que otros
     * productos de la empresa lo componen y en que cantidad. Un kit no
     * tiene existencia propia (products.is_kit fuerza is_inventory=false);
     * al venderlo, SalesService descuenta el inventario de cada componente
     * listado aqui en vez del kit — ver moveInventoryForKitComponents().
     */
    public function up(): void
    {
        Schema::create('product_kit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('kit_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->timestamps();
            $table->unique(['kit_product_id', 'component_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_kit_items');
    }
};
