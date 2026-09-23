<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un cliente puede pagar con una mezcla de billetes en las dos monedas
     * a la vez (ej. un billete de $10 y uno de C$500 en la misma venta):
     * la pareja amount_tendered + amount_tendered_currency_id solo podia
     * representar una sola moneda "dominante", asi que se reemplaza por dos
     * columnas independientes — una por cada moneda que el POS maneja
     * (base y la moneda extranjera configurada, siempre USD en este
     * sistema). Las columnas viejas se dejan intactas (no se borran datos
     * historicos), simplemente dejan de llenarse.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('amount_tendered_base', 18, 4)->nullable()->after('amount_tendered_currency_id');
            $table->decimal('amount_tendered_foreign', 18, 4)->nullable()->after('amount_tendered_base');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['amount_tendered_base', 'amount_tendered_foreign']);
        });
    }
};
