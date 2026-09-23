<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modulo de Apertura de Caja (POS). Jerarquia:
     *
     *   companies -> branches -> terminals -> cash_registers (cajas fisicas)
     *                                              -> cash_sessions (aperturas/turnos)
     *                                                    -> cash_movements (ingresos/egresos manuales)
     *                                                    -> cash_counts (arqueos y cierre)
     *
     * Una caja fisica (cash_registers) es un concepto distinto de una
     * apertura (cash_sessions): la caja existe siempre, la apertura es la
     * sesion financiera de un turno especifico. Las ventas se enlazan a la
     * apertura activa via sales.cash_session_id para poder separar "ventas"
     * de "movimientos de caja" (ver SalesService::createSale()).
     */
    public function up(): void
    {
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('cash_register_id')->nullable()
                ->comment('Si se asigna, esta terminal solo puede abrir esa caja (candado de terminal)');
            $table->string('code', 40)->comment('Identificador estable del navegador/equipo, generado y guardado en localStorage');
            $table->string('name', 120)->nullable();
            $table->string('device_name', 120)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('os_info', 120)->nullable();
            $table->string('browser_info', 120)->nullable();
            $table->enum('status', ['ACTIVA', 'INACTIVA'])->default('ACTIVA');
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('current_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->foreignId('default_currency_id')->default(1)->constrained('currencies');
            $table->enum('status', ['ACTIVA', 'INACTIVA', 'MANTENIMIENTO'])->default('ACTIVA');
            $table->decimal('authorization_threshold', 18, 4)->nullable()
                ->comment('Monto de movimiento manual a partir del cual se exige autorizacion; null = sin limite');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        Schema::table('terminals', function (Blueprint $table) {
            $table->foreign('cash_register_id')->references('id')->on('cash_registers')->nullOnDelete();
        });

        Schema::create('cash_register_users', function (Blueprint $table) {
            $table->foreignId('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['cash_register_id', 'user_id']);
        });

        Schema::create('cash_movement_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete()
                ->comment('Null = motivo global disponible para todas las empresas (catalogo sembrado)');
            $table->enum('type', ['INGRESO', 'EGRESO']);
            $table->string('name', 120);
            $table->boolean('requires_note')->default(false)->comment('Obliga observacion, ej. motivo "Otro"');
            $table->boolean('is_system')->default(false)->comment('Sembrado por el sistema, no se puede eliminar');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->foreignId('terminal_id')->nullable()->constrained('terminals')->nullOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['ABIERTA', 'EN_ARQUEO', 'CERRADA', 'CANCELADA'])->default('ABIERTA');
            $table->foreignId('currency_id')->default(1)->constrained('currencies');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->decimal('opening_amount', 18, 4)->default(0);
            $table->json('opening_breakdown')->nullable()->comment('Desglose de billetes/monedas del fondo inicial');
            $table->text('opening_note')->nullable();
            $table->timestamp('opened_at');
            $table->string('opening_ip', 45)->nullable();
            $table->decimal('expected_cash', 18, 4)->nullable()->comment('Efectivo esperado, calculado al cerrar');
            $table->decimal('counted_cash', 18, 4)->nullable()->comment('Efectivo fisico contado al cerrar');
            $table->decimal('cash_difference', 18, 4)->nullable()->comment('counted_cash - expected_cash');
            $table->json('closing_breakdown')->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['cash_register_id', 'status']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('terminal_id')->nullable()->constrained('terminals')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->comment('Quien realizo el movimiento');
            $table->enum('type', ['INGRESO', 'EGRESO']);
            $table->foreignId('reason_id')->nullable()->constrained('cash_movement_reasons')->nullOnDelete();
            $table->string('reason_text', 255)->nullable()->comment('Motivo libre, obligatorio si el motivo es "Otro" o no hay catalogo');
            $table->decimal('amount', 18, 4);
            $table->foreignId('currency_id')->default(1)->constrained('currencies');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->string('reference', 100)->nullable();
            $table->text('observation')->nullable();
            $table->enum('status', ['ACTIVO', 'ANULADO', 'PENDIENTE_AUTORIZACION', 'RECHAZADO'])->default('ACTIVO');
            $table->boolean('requires_authorization')->default(false);
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['cash_session_id', 'status']);
        });

        Schema::create('cash_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->cascadeOnDelete();
            $table->enum('type', ['ARQUEO', 'CIERRE'])->default('ARQUEO');
            $table->foreignId('counted_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('expected_amount', 18, 4);
            $table->decimal('counted_amount', 18, 4);
            $table->decimal('difference', 18, 4);
            $table->json('breakdown')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (! Schema::hasColumn('sales', 'cash_session_id')) {
                    $table->foreignId('cash_session_id')->nullable()->after('salesperson_id')
                        ->constrained('cash_sessions')->nullOnDelete()
                        ->comment('Apertura de caja que recibio el cobro de esta venta, si habia una abierta');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'cash_session_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropConstrainedForeignId('cash_session_id');
            });
        }

        Schema::dropIfExists('cash_counts');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('cash_movement_reasons');
        Schema::dropIfExists('cash_register_users');

        if (Schema::hasTable('terminals')) {
            Schema::table('terminals', function (Blueprint $table) {
                $table->dropForeign(['cash_register_id']);
            });
        }

        Schema::dropIfExists('cash_registers');
        Schema::dropIfExists('terminals');
    }
};
