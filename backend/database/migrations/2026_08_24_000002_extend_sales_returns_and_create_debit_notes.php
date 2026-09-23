<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `sales_returns`/`sales_return_items` ya existian en el schema inicial
     * pero nunca se usaron (sin modelo, controlador ni ruta) — se adoptan
     * como el molde de "Nota de Credito" (ver CreditNoteService) y solo les
     * faltan columnas de trazabilidad: a que almacen vuelve el producto, y
     * que asiento/movimiento de caja genero la nota.
     *
     * "Nota de Debito" no tenia ningun scaffolding previo: se crean
     * sales_debit_notes/sales_debit_note_items espejando la forma de
     * sales_returns/sales_return_items (ver DebitNoteService).
     */
    public function up(): void
    {
        if (Schema::hasTable('sales_returns')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_returns', 'warehouse_id')) {
                    $table->foreignId('warehouse_id')->nullable()->after('sale_id')
                        ->constrained('warehouses')->nullOnDelete();
                }
                if (! Schema::hasColumn('sales_returns', 'journal_entry_id')) {
                    $table->foreignId('journal_entry_id')->nullable()->after('total')
                        ->constrained('journal_entries')->nullOnDelete();
                }
                if (! Schema::hasColumn('sales_returns', 'cash_movement_id')) {
                    $table->foreignId('cash_movement_id')->nullable()->after('journal_entry_id')
                        ->constrained('cash_movements')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('sales_debit_notes')) {
            Schema::create('sales_debit_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
                $table->string('debit_number', 100);
                $table->date('debit_date');
                $table->enum('status', ['PROCESSED', 'CANCELLED'])->default('PROCESSED');
                $table->decimal('subtotal', 18, 4)->default(0);
                $table->decimal('tax', 18, 4)->default(0);
                $table->decimal('total', 18, 4)->default(0);
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->text('reason')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unique(['company_id', 'debit_number']);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('sales_debit_note_items')) {
            Schema::create('sales_debit_note_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_debit_note_id')->constrained('sales_debit_notes')->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('description', 255)->nullable();
                $table->decimal('quantity', 18, 4)->default(1);
                $table->decimal('unit_price', 18, 4);
                $table->decimal('tax', 18, 4)->default(0);
                $table->decimal('subtotal', 18, 4);
                $table->decimal('total', 18, 4);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_debit_note_items');
        Schema::dropIfExists('sales_debit_notes');

        if (Schema::hasTable('sales_returns')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                foreach (['cash_movement_id', 'journal_entry_id', 'warehouse_id'] as $column) {
                    if (Schema::hasColumn('sales_returns', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
            });
        }
    }
};
