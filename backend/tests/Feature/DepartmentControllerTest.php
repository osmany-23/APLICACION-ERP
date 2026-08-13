<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DepartmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private function authenticateUser(): string
    {
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'NIO', 'name' => 'Cordoba', 'symbol' => 'C$', 'decimal_places' => 2,
            'is_base' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => 'Empresa Test', 'currency_id' => $currencyId, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Tester', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->insertGetId([
            'module_name' => 'empleados', 'action_name' => 'manage', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId, 'role_id' => $roleId, 'uuid' => (string) Str::uuid(),
            'username' => 'tester', 'password_hash' => bcrypt('password'), 'full_name' => 'Tester',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('user_api_tokens')->insert([
            'user_id' => $userId, 'name' => 'Test token', 'token_hash' => hash('sha256', 'test-token'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return 'test-token';
    }

    public function test_department_can_be_created_with_a_manager(): void
    {
        $token = $this->authenticateUser();

        $employeeId = DB::table('employees')->insertGetId([
            'company_id' => $this->companyId, 'first_name' => 'Ana', 'last_name' => 'Lopez',
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/departments', ['name' => 'Ventas', 'manager_employee_id' => $employeeId]);

        $response->assertCreated();
        $response->assertJsonPath('item.manager_name', 'Ana Lopez');
    }

    public function test_department_name_must_be_unique_per_company(): void
    {
        $token = $this->authenticateUser();

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/departments', ['name' => 'Ventas']);
        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/departments', ['name' => 'Ventas']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_department_cannot_be_deleted_with_employees_assigned(): void
    {
        $token = $this->authenticateUser();

        $create = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/departments', ['name' => 'Bodega']);
        $departmentId = $create->json('item.id');

        DB::table('employees')->insert([
            'company_id' => $this->companyId, 'department_id' => $departmentId, 'first_name' => 'Luis', 'last_name' => 'Mora',
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->deleteJson("/api/departments/{$departmentId}");

        $response->assertStatus(409);
    }
}
