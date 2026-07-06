<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Agregamos las columnas como nulables por seguridad con los datos existentes
            $table->foreignId('purchase_unit_id')->nullable()->after('brand_id')->constrained('units')->nullOnDelete();
            $table->foreignId('sale_unit_id')->nullable()->after('purchase_unit_id')->constrained('units')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['purchase_unit_id']);
            $table->dropForeign(['sale_unit_id']);
            $table->dropColumn(['purchase_unit_id', 'sale_unit_id']);
        });
    }
};