<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts_payable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            
            // Relación opcional con la compra que originó la deuda
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            
            $table->string('document_number')->nullable(); // Número de factura del proveedor
            $table->date('doc_date'); // Fecha de emisión
            $table->date('due_date'); // Fecha de vencimiento
            
            // Montos monetarios (usando precisión de 4 decimales como tus otros campos)
            $table->decimal('total_amount', 18, 4); // Monto total de la deuda original
            $table->decimal('paid_amount', 18, 4)->default(0); // Lo que ya has abonado
            $table->decimal('balance', 18, 4); // El saldo pendiente actual (exigido por tu consulta)
            
            $table->string('status')->default('PENDIENTE'); // PENDIENTE, PARCIAL, PAGADO
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts_payable');
    }
};