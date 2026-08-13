<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PIN rapido de vendedor (modulo Administracion):
     * - users.pin_hash / pin_generated_at: PIN de 4 digitos hasheado (igual
     *   que la contrasena, nunca en texto plano) para identificar quien
     *   vendio sin tener que cerrar la sesion compartida de la PC.
     * - sales.salesperson_id: separado de sales.created_by (quien opero la
     *   PC); es quien realmente atendio la venta segun el PIN ingresado.
     */
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'pin_hash')) {
                    $table->string('pin_hash', 255)->nullable()->after('password_hash');
                }
                if (! Schema::hasColumn('users', 'pin_generated_at')) {
                    $table->timestamp('pin_generated_at')->nullable()->after('pin_hash');
                }
            });
        }

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (! Schema::hasColumn('sales', 'salesperson_id')) {
                    $table->foreignId('salesperson_id')->nullable()->after('created_by')
                        ->constrained('users')->nullOnDelete()
                        ->comment('Vendedor real (via PIN); puede diferir de created_by');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'salesperson_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropConstrainedForeignId('salesperson_id');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach (['pin_generated_at', 'pin_hash'] as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
