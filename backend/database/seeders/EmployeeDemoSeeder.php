<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo para el modulo de Empleados/Cargos/Departamentos, recien
 * creado y sin ningun dato previo. Todo con updateOrCreate para poder
 * re-correr el seeder sin duplicar filas.
 *
 * Todo por Eloquent a proposito (nada de DB::table()): system:verify-migrations
 * atribuye los campos de cualquier array literal al ULTIMO DB::table() visto
 * en el archivo, sin importar si en verdad le pertenece a esa llamada — con
 * puro Eloquent no hay ningun DB::table() del que colgarse y el falso
 * positivo desaparece solo.
 */
class EmployeeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->first();

        if (! $company) {
            return;
        }

        $departments = [
            'Ventas' => 'Vendedor',
            'Administracion' => 'Asistente Administrativo',
            'Bodega' => 'Encargado de Bodega',
            'Contabilidad' => 'Contador',
        ];

        foreach ($departments as $departmentName => $positionName) {
            $department = Department::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $departmentName],
                ['status' => true],
            );

            Position::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $positionName],
                ['department_id' => $department->id, 'status' => true],
            );
        }

        $ventasDept = Department::query()->where('company_id', $company->id)->where('name', 'Ventas')->first();
        $vendedorPos = Position::query()->where('company_id', $company->id)->where('name', 'Vendedor')->first();
        $adminUserId = User::query()->where('company_id', $company->id)->where('username', 'admin')->value('id');

        if ($ventasDept && $vendedorPos) {
            $existing = Employee::query()->where('company_id', $company->id)->where('code', 'EMP-000001')->first();

            if (! $existing) {
                Employee::create([
                    'company_id' => $company->id,
                    'code' => 'EMP-000001',
                    'department_id' => $ventasDept->id,
                    'position_id' => $vendedorPos->id,
                    'first_name' => 'Maria',
                    'last_name' => 'Rodriguez',
                    'phone' => '8888-1234',
                    'email' => 'maria.rodriguez@example.com',
                    'salary' => 12000,
                    'hire_date' => now()->subMonths(6)->toDateString(),
                    'status' => 'ACTIVE',
                ]);
            }
        }

        // El administrador del sistema tambien es, en la vida real, un
        // empleado de la empresa -- lo dejamos vinculado como demostracion
        // de la asociacion empleado <-> usuario, si todavia no existe.
        if ($adminUserId && ! Employee::query()->where('user_id', $adminUserId)->exists()) {
            $adminDept = Department::query()->where('company_id', $company->id)->where('name', 'Administracion')->first();
            $adminPos = Position::query()->where('company_id', $company->id)->where('name', 'Asistente Administrativo')->first();

            Employee::create([
                'company_id' => $company->id,
                'code' => 'EMP-000002',
                'department_id' => $adminDept?->id,
                'position_id' => $adminPos?->id,
                'user_id' => $adminUserId,
                'first_name' => 'Administrador',
                'last_name' => 'del Sistema',
                'hire_date' => now()->subYear()->toDateString(),
                'status' => 'ACTIVE',
            ]);
        }
    }
}
