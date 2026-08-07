<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columnas necesarias para que Compras funcione con doble partida:
     * saldo pendiente de pago, y enlace al asiento contable y a la cuenta
     * por pagar generados al confirmar la compra (recepcion de mercancia).
     */
    public function up(): void
    {
        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (! Schema::hasColumn('purchases', 'paid_amount')) {
                    $table->decimal('paid_amount', 18, 4)->default(0)->after('total');
                }
                if (! Schema::hasColumn('purchases', 'balance_due')) {
                    $table->decimal('balance_due', 18, 4)->default(0)->after('paid_amount');
                }
                if (! Schema::hasColumn('purchases', 'accounts_payable_id')) {
                    $table->foreignId('accounts_payable_id')->nullable()->after('balance_due')
                        ->constrained('accounts_payable')->nullOnDelete();
                }
                if (! Schema::hasColumn('purchases', 'journal_entry_id')) {
                    $table->foreignId('journal_entry_id')->nullable()->after('accounts_payable_id')
                        ->constrained('journal_entries')->nullOnDelete()
                        ->comment('Asiento contable generado al confirmar la compra');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                foreach (['journal_entry_id', 'accounts_payable_id'] as $column) {
                    if (Schema::hasColumn('purchases', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
                foreach (['balance_due', 'paid_amount'] as $column) {
                    if (Schema::hasColumn('purchases', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
