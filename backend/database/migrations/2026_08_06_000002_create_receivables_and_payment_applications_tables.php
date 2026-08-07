<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuentas por cobrar de clientes (simétrica a accounts_payable, pero con
     * estado tipado y moneda/tipo de cambio propios) y la tabla puente que
     * permite aplicar un pago a uno o varios documentos (CxC o CxP).
     */
    public function up(): void
    {
        if (! Schema::hasTable('accounts_receivable')) {
            Schema::create('accounts_receivable', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
                $table->foreignId('accounting_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete()
                    ->comment('Cuenta de CxC afectada, normalmente 1103');
                $table->string('document_number', 100)->nullable();
                $table->date('doc_date');
                $table->date('due_date');
                $table->foreignId('currency_id')->default(1)->constrained('currencies');
                $table->decimal('exchange_rate', 18, 8)->default(1);
                $table->decimal('total_amount', 18, 4);
                $table->decimal('paid_amount', 18, 4)->default(0);
                $table->decimal('balance', 18, 4);
                $table->enum('status', ['PENDING', 'PARTIAL', 'PAID', 'OVERDUE', 'VOID'])->default('PENDING');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'customer_id', 'status']);
            });
        }

        if (! Schema::hasTable('payment_applications')) {
            Schema::create('payment_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
                $table->string('applicable_type', 30)->comment('accounts_receivable | accounts_payable');
                $table->unsignedBigInteger('applicable_id');
                $table->decimal('amount_applied', 18, 4);
                $table->timestamps();
                $table->index(['applicable_type', 'applicable_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_applications');
        Schema::dropIfExists('accounts_receivable');
    }
};
