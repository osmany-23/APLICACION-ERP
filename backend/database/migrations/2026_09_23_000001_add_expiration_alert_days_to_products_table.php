<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Politica de alerta de vencimiento por producto: a cuantos dias antes
     * del vencimiento de un lote se debe avisar (util para farmacias y
     * empresas de alimentos, que necesitan anticipar la rotacion de lotes
     * proximos a vencer). Solo aplica cuando el producto maneja lotes.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('expiration_alert_days')->nullable()->after('manages_expiration');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('expiration_alert_days');
        });
    }
};
