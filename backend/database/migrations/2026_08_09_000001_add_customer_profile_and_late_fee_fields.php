<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amplia el perfil del cliente (nacionalidad, genero, ubicacion, tipo de
     * venta, telefono con prefijo internacional/convencional) y agrega la
     * configuracion de mora por atraso (porcentaje libre + periodo en
     * dias/semanas/meses). El calculo/aplicacion real de la mora vive en el
     * comando ApplyLateFees, que necesita accounts_receivable.late_fee_accrued
     * y late_fee_last_run_at para no cobrar dos veces el mismo periodo.
     */
    public function up(): void
    {
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (! Schema::hasColumn('customers', 'residency_type')) {
                    $table->enum('residency_type', ['NACIONAL', 'EXTRANJERO'])->default('NACIONAL')->after('tax_id_type');
                }
                if (! Schema::hasColumn('customers', 'gender')) {
                    $table->enum('gender', ['FEMENINO', 'MASCULINO', 'EMPRESA', 'OTRO'])->nullable()->after('residency_type');
                }
                if (! Schema::hasColumn('customers', 'sales_type')) {
                    $table->enum('sales_type', ['CONTADO', 'CREDITO'])->default('CREDITO')->after('gender');
                }
                if (! Schema::hasColumn('customers', 'phone_country_id')) {
                    $table->foreignId('phone_country_id')->nullable()->after('phone')
                        ->constrained('countries')->nullOnDelete()
                        ->comment('Prefijo internacional del telefono principal (movil)');
                }
                if (! Schema::hasColumn('customers', 'has_landline')) {
                    $table->boolean('has_landline')->default(false)->after('phone_country_id');
                }
                if (! Schema::hasColumn('customers', 'landline_phone')) {
                    $table->string('landline_phone', 30)->nullable()->after('has_landline')
                        ->comment('Telefono convencional/fijo, sin prefijo internacional');
                }
                if (! Schema::hasColumn('customers', 'country_id')) {
                    $table->foreignId('country_id')->nullable()->after('address')
                        ->constrained('countries')->nullOnDelete();
                }
                if (! Schema::hasColumn('customers', 'city')) {
                    $table->string('city', 100)->nullable()->after('country_id');
                }
                if (! Schema::hasColumn('customers', 'applies_late_fee')) {
                    $table->boolean('applies_late_fee')->default(false)->after('credit_days')
                        ->comment('Si true, el saldo vencido se incrementa automaticamente (ver comando sales:apply-late-fees)');
                }
                if (! Schema::hasColumn('customers', 'late_fee_percentage')) {
                    $table->decimal('late_fee_percentage', 5, 2)->nullable()->after('applies_late_fee')
                        ->comment('Porcentaje libre por periodo vencido, ej. 2.00 = 2%. Sin valor por defecto fijo.');
                }
                if (! Schema::hasColumn('customers', 'late_fee_period_unit')) {
                    $table->enum('late_fee_period_unit', ['DAYS', 'WEEKS', 'MONTHS'])->nullable()->after('late_fee_percentage');
                }
            });
        }

        if (Schema::hasTable('accounts_receivable')) {
            Schema::table('accounts_receivable', function (Blueprint $table) {
                if (! Schema::hasColumn('accounts_receivable', 'late_fee_accrued')) {
                    $table->decimal('late_fee_accrued', 18, 4)->default(0)->after('balance');
                }
                if (! Schema::hasColumn('accounts_receivable', 'late_fee_last_run_at')) {
                    $table->date('late_fee_last_run_at')->nullable()->after('late_fee_accrued');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('accounts_receivable')) {
            Schema::table('accounts_receivable', function (Blueprint $table) {
                foreach (['late_fee_last_run_at', 'late_fee_accrued'] as $column) {
                    if (Schema::hasColumn('accounts_receivable', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                foreach (['phone_country_id', 'country_id'] as $fk) {
                    if (Schema::hasColumn('customers', $fk)) {
                        $table->dropConstrainedForeignId($fk);
                    }
                }
                foreach ([
                    'residency_type', 'gender', 'sales_type', 'has_landline', 'landline_phone',
                    'city', 'applies_late_fee', 'late_fee_percentage', 'late_fee_period_unit',
                ] as $column) {
                    if (Schema::hasColumn('customers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
