<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abonos parciales sobre una venta "Pendiente de pago" (ver
     * SalesService::registerPendingPayment()). Deliberadamente NO reutiliza
     * las tablas `payments`/`payment_applications` (scaffolding viejo,
     * nunca conectado a ningun controlador, y que ademas usa un ENUM fijo
     * de metodo de pago en vez del catalogo real `payment_methods`) — esta
     * tabla es consistente con como el resto del sistema ya referencia el
     * metodo de pago (Sale.payment_method_id).
     */
    public function up(): void
    {
        if (! Schema::hasTable('sale_payments')) {
            Schema::create('sale_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
                $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
                $table->decimal('amount', 18, 4);
                $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->nullOnDelete()
                    ->comment('Sesion de caja abierta del usuario en el momento del abono, si tenia una.');
                $table->foreignId('cash_movement_id')->nullable()->constrained('cash_movements')->nullOnDelete()
                    ->comment('Movimiento de caja (INGRESO) creado si el abono fue en efectivo y habia sesion abierta.');
                $table->string('reference', 120)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['sale_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
