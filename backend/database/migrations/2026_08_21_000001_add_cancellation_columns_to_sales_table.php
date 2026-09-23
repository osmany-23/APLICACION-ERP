<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anular una factura ahora exige el codigo (PIN) de alguien con permiso
     * de administrar/eliminar facturacion (ver
     * SalesService::resolveAuthorizedCanceller()). Estas columnas dejan
     * auditado quien realmente anulo (created_by de la accion) y quien
     * autorizo con su PIN, ademas de cuando — mismo patron que
     * payment_confirmed_at/payment_confirmed_by para el cobro de ventas
     * pendientes de pago.
     */
    public function up(): void
    {
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (! Schema::hasColumn('sales', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('payment_confirmed_by');
                }
                if (! Schema::hasColumn('sales', 'cancelled_by')) {
                    $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')
                        ->constrained('users')->nullOnDelete()
                        ->comment('Usuario que ejecuto la anulacion (no necesariamente quien autorizo con su PIN).');
                }
                if (! Schema::hasColumn('sales', 'cancellation_authorized_by')) {
                    $table->foreignId('cancellation_authorized_by')->nullable()->after('cancelled_by')
                        ->constrained('users')->nullOnDelete()
                        ->comment('Usuario cuyo PIN autorizo la anulacion (rol con permiso facturacion:eliminar o administrar).');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                foreach (['cancellation_authorized_by', 'cancelled_by', 'cancelled_at'] as $column) {
                    if (Schema::hasColumn('sales', $column)) {
                        if (in_array($column, ['cancelled_by', 'cancellation_authorized_by'], true)) {
                            $table->dropConstrainedForeignId($column);
                        } else {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }
    }
};
