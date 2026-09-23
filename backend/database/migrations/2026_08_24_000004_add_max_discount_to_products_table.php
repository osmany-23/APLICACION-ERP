<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Limite de descuento propio de cada producto: si esta configurado,
     * SalesService::resolveMaxLineDiscount() lo usa como tope al facturar
     * en vez del tope general de Configuracion General (sales.
     * max_discount_percentage). max_discount_type distingue si el valor es
     * un porcentaje sobre el importe de la linea o un monto fijo por
     * unidad. Ambas columnas nulas = el producto no tiene limite propio
     * (cae al tope general, si esta activado).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'max_discount_type')) {
                $table->enum('max_discount_type', ['PERCENTAGE', 'FIXED'])->nullable()->after('tax_percentage');
            }
            if (! Schema::hasColumn('products', 'max_discount_value')) {
                $table->decimal('max_discount_value', 18, 4)->nullable()->after('max_discount_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'max_discount_value')) {
                $table->dropColumn('max_discount_value');
            }
            if (Schema::hasColumn('products', 'max_discount_type')) {
                $table->dropColumn('max_discount_type');
            }
        });
    }
};
