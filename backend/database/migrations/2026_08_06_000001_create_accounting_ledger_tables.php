<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motor de contabilidad de doble partida: asientos contables (journal_entries)
     * y sus líneas de debe/haber (journal_entry_lines).
     */
    public function up(): void
    {
        if (! Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('entry_number', 100)->comment('Correlativo, ej: AS-000123');
                $table->date('entry_date');
                $table->text('description')->nullable();
                $table->foreignId('fiscal_period_id')->nullable()->constrained('fiscal_periods')->nullOnDelete();
                $table->string('reference_table', 100)->nullable()->comment('Ej: sales');
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->enum('status', ['DRAFT', 'POSTED', 'VOID'])->default('DRAFT');
                $table->decimal('total_debit', 18, 4)->default(0);
                $table->decimal('total_credit', 18, 4)->default(0);
                $table->foreignId('currency_id')->default(1)->constrained('currencies');
                $table->decimal('exchange_rate', 18, 8)->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('posted_at')->nullable();
                $table->unique(['company_id', 'entry_number']);
                $table->index(['reference_table', 'reference_id']);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('journal_entry_lines')) {
            Schema::create('journal_entry_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
                $table->foreignId('accounting_account_id')->constrained('accounting_accounts')->restrictOnDelete();
                $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
                $table->decimal('debit', 18, 4)->default(0);
                $table->decimal('credit', 18, 4)->default(0);
                $table->string('description', 255)->nullable();
                $table->unsignedInteger('line_order')->default(1);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
    }
};
