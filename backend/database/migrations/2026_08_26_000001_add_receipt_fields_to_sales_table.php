<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos para poder reimprimir el ticket de una venta exactamente como
     * quedo cobrada (ver ReceiptTicket.tsx en el frontend): hasta ahora el
     * codigo de referencia de tarjeta/transferencia y el efectivo
     * recibido/vuelto solo se calculaban en el navegador al momento de
     * cobrar (PaymentModal.tsx) y se perdian — no habia forma de
     * reconstruirlos despues desde Ventas > Imprimir factura.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_reference', 120)->nullable()->after('exchange_rate');
            $table->decimal('amount_tendered', 18, 4)->nullable()->after('payment_reference');
            $table->foreignId('amount_tendered_currency_id')->nullable()->after('amount_tendered')
                ->constrained('currencies')->nullOnDelete();
            $table->decimal('change_amount', 18, 4)->nullable()->after('amount_tendered_currency_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('amount_tendered_currency_id');
            $table->dropColumn(['payment_reference', 'amount_tendered', 'change_amount']);
        });
    }
};
