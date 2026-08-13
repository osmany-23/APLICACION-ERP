<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PositionControllerTest extends TestCase
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

    public function test_position_can_be_created_with_a_department(): void
    {
        $token = $this->authenticateUser();

        $departmentId = DB::table('departments')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Ventas', 'status' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/positions', ['name' => 'Vendedor', 'department_id' => $departmentId, 'base_salary' => 9000]);

        $response->assertCreated();
        $response->assertJsonPath('item.department_name', 'Ventas');
        $response->assertJsonPath('item.base_salary', 9000);
    }

    public function test_position_cannot_be_deleted_with_employees_assigned(): void
    {
        $token = $this->authenticateUser();

        $create = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/positions', ['name' => 'Cajero']);
        $positionId = $create->json('item.id');

        DB::table('employees')->insert([
            'company_id' => $this->companyId, 'position_id' => $positionId, 'first_name' => 'Pedro', 'last_name' => 'Ruiz',
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->deleteJson("/api/positions/{$positionId}");

        $response->assertStatus(409);
    }
}
