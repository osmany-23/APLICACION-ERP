<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "product_price_list_items" ya existia en el esquema (junto con
     * "price_lists") pero no tenia forma de decir "este precio aplica a
     * partir de tal cantidad" — solo guardaba un precio fijo por producto y
     * tipo de precio. Esta columna es la pieza que faltaba para soportar
     * precios de mayoreo/por volumen. El default de 1 tambien cubre el caso
     * de un tipo de precio sin umbral real de cantidad (p. ej. un precio de
     * distribuidor que aplica desde la primera unidad).
     */
    public function up(): void
    {
        if (Schema::hasTable('product_price_list_items')) {
            Schema::table('product_price_list_items', function (Blueprint $table) {
                if (! Schema::hasColumn('product_price_list_items', 'min_quantity')) {
                    $table->decimal('min_quantity', 18, 4)->default(1)->after('price')
                        ->comment('Cantidad minima de la linea de venta a partir de la cual aplica este precio.');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_price_list_items')) {
            Schema::table('product_price_list_items', function (Blueprint $table) {
                if (Schema::hasColumn('product_price_list_items', 'min_quantity')) {
                    $table->dropColumn('min_quantity');
                }
            });
        }
    }
};
