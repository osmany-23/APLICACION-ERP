<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soporte para ventas "Pendiente de pago" (contra entrega): la venta se
     * factura y el inventario sale de bodega de inmediato, pero el cobro
     * real (y por lo tanto el asiento contable y el impacto en caja) se
     * confirma despues, manualmente. Estas dos columnas dejan auditado
     * quien confirmo el cobro y cuando (ver SalesService::confirmPaymentReceived()).
     * No hace falta tocar el enum de sales.status: ya trae un valor
     * 'PENDING' sin usar en ningun lado del codigo, que es justo el que se
     * reutiliza para este estado.
     */
    public function up(): void
    {
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (! Schema::hasColumn('sales', 'payment_confirmed_at')) {
                    $table->timestamp('payment_confirmed_at')->nullable()->after('cash_session_id');
                }
                if (! Schema::hasColumn('sales', 'payment_confirmed_by')) {
                    $table->foreignId('payment_confirmed_by')->nullable()->after('payment_confirmed_at')
                        ->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (Schema::hasColumn('sales', 'payment_confirmed_by')) {
                    $table->dropConstrainedForeignId('payment_confirmed_by');
                }
                if (Schema::hasColumn('sales', 'payment_confirmed_at')) {
                    $table->dropColumn('payment_confirmed_at');
                }
            });
        }
    }
};
