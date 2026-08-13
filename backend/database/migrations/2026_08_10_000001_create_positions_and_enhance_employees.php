<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modulo de Empleados: agrega el catalogo de "cargos" (positions) que no
     * existia, y mejora employees/departments para que la informacion sea
     * consistente y quede realmente enlazada:
     * - employees.code: identificador legible (EMP-000001), igual que ya
     *   tienen customers/suppliers.
     * - employees.position_id: el cargo real del empleado (antes no existia
     *   ningun concepto de cargo en el esquema).
     * - employees.termination_date: para que el estado TERMINATED (que ya
     *   existia en el enum pero nunca se usaba) tenga cuando.
     * - employees.user_id unico: un empleado <-> un usuario del sistema,
     *   nunca dos empleados apuntando al mismo usuario.
     * - departments.manager (string libre, sin validar) se reemplaza por
     *   manager_employee_id (FK real a employees). La tabla esta vacia y
     *   sin uso en toda la app (confirmado por grep), asi que no hay datos
     *   que migrar.
     */
    public function up(): void
    {
        if (! Schema::hasTable('positions') && Schema::hasTable('companies')) {
            Schema::create('positions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->decimal('base_salary', 18, 4)->nullable()
                    ->comment('Salario de referencia del cargo, no el salario real del empleado');
                $table->boolean('status')->default(true);
                $table->timestamps();
                $table->unique(['company_id', 'name']);
            });
        }

        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (! Schema::hasColumn('employees', 'code')) {
                    $table->string('code', 50)->nullable()->after('company_id');
                }
                if (! Schema::hasColumn('employees', 'position_id')) {
                    $table->foreignId('position_id')->nullable()->after('department_id')
                        ->constrained('positions')->nullOnDelete();
                }
                if (! Schema::hasColumn('employees', 'termination_date')) {
                    $table->date('termination_date')->nullable()->after('hire_date');
                }
            });

            Schema::table('employees', function (Blueprint $table) {
                $table->unique(['company_id', 'code']);
                $table->unique('user_id');
            });
        }

        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                if (! Schema::hasColumn('departments', 'manager_employee_id')) {
                    $table->foreignId('manager_employee_id')->nullable()->after('name')
                        ->constrained('employees')->nullOnDelete();
                }
            });

            if (Schema::hasColumn('departments', 'manager')) {
                Schema::table('departments', function (Blueprint $table) {
                    $table->dropColumn('manager');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                if (! Schema::hasColumn('departments', 'manager')) {
                    $table->string('manager', 120)->nullable();
                }
                if (Schema::hasColumn('departments', 'manager_employee_id')) {
                    $table->dropConstrainedForeignId('manager_employee_id');
                }
            });
        }

        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropUnique(['user_id']);
                $table->dropUnique(['company_id', 'code']);
            });
            Schema::table('employees', function (Blueprint $table) {
                if (Schema::hasColumn('employees', 'position_id')) {
                    $table->dropConstrainedForeignId('position_id');
                }
                foreach (['termination_date', 'code'] as $column) {
                    if (Schema::hasColumn('employees', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('positions');
    }
};
