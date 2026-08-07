<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Refuerza accounts_payable para que sea simetrica a accounts_receivable:
     * agrega moneda/tipo de cambio propios del documento y el enlace a la
     * cuenta contable afectada. El estado (columna string, sin doctrine/dbal
     * instalado no se puede convertir a ENUM real sin romper sqlite en
     * tests) se normaliza de los valores en espanol originales
     * ('PENDIENTE'/'PARCIAL'/'PAGADO') a los mismos valores en ingles que
     * usa accounts_receivable ('PENDING'/'PARTIAL'/'PAID'/'OVERDUE'/'VOID'),
     * validados a nivel de aplicacion en AccountsPayable/PurchasesService.
     */
    public function up(): void
    {
        if (! Schema::hasTable('accounts_payable')) {
            return;
        }

        Schema::table('accounts_payable', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts_payable', 'currency_id')) {
                $table->foreignId('currency_id')->default(1)->after('purchase_id')->constrained('currencies');
            }
            if (! Schema::hasColumn('accounts_payable', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 8)->default(1)->after('currency_id');
            }
            if (! Schema::hasColumn('accounts_payable', 'accounting_account_id')) {
                $table->foreignId('accounting_account_id')->nullable()->after('exchange_rate')
                    ->constrained('accounting_accounts')->nullOnDelete()
                    ->comment('Cuenta de CxP afectada, normalmente 2101');
            }
        });

        DB::table('accounts_payable')->where('status', 'PENDIENTE')->update(['status' => 'PENDING']);
        DB::table('accounts_payable')->where('status', 'PARCIAL')->update(['status' => 'PARTIAL']);
        DB::table('accounts_payable')->where('status', 'PAGADO')->update(['status' => 'PAID']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounts_payable')) {
            return;
        }

        Schema::table('accounts_payable', function (Blueprint $table) {
            if (Schema::hasColumn('accounts_payable', 'accounting_account_id')) {
                $table->dropConstrainedForeignId('accounting_account_id');
            }
            if (Schema::hasColumn('accounts_payable', 'exchange_rate')) {
                $table->dropColumn('exchange_rate');
            }
            if (Schema::hasColumn('accounts_payable', 'currency_id')) {
                $table->dropConstrainedForeignId('currency_id');
            }
        });
    }
};
