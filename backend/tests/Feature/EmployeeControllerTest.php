<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private function authenticateUser(array $modulesWithActions = ['empleados' => ['manage']]): array
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

        foreach ($modulesWithActions as $module => $actions) {
            foreach ($actions as $action) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'module_name' => $module, 'action_name' => $action, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('role_permissions')->insert([
                    'role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $userId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId,
            'role_id' => $roleId,
            'uuid' => (string) Str::uuid(),
            'username' => 'tester',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Tester',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_api_tokens')->insert([
            'user_id' => $userId,
            'name' => 'Test token',
            'token_hash' => hash('sha256', 'test-token'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['test-token', $userId, $roleId];
    }

    private function createUserManagerRole(): int
    {
        $roleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Manager', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->insertGetId([
            'module_name' => 'usuarios', 'action_name' => 'manage', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $roleId;
    }

    public function test_employee_can_be_created_with_generated_code(): void
    {
        [$token] = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/employees', [
                'first_name' => 'Juan', 'last_name' => 'Perez', 'status' => 'ACTIVE',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.code', 'EMP-000001');
        $response->assertJsonPath('item.full_name', 'Juan Perez');
    }

    public function test_employee_cannot_link_user_already_linked_to_another_employee(): void
    {
        [$token] = $this->authenticateUser();

        $linkedRoleId = $this->createUserManagerRole();
        $targetUserId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId, 'role_id' => $linkedRoleId, 'uuid' => (string) Str::uuid(),
            'username' => 'cajero', 'password_hash' => bcrypt('password'), 'full_name' => 'Cajero',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $first = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/employees', [
                'first_name' => 'Primero', 'last_name' => 'Empleado', 'status' => 'ACTIVE', 'user_id' => $targetUserId,
            ]);
        $first->assertCreated();

        $second = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/employees', [
                'first_name' => 'Segundo', 'last_name' => 'Empleado', 'status' => 'ACTIVE', 'user_id' => $targetUserId,
            ]);

        $second->assertStatus(422);
        $second->assertJsonValidationErrors('user_id');
    }

    public function test_terminating_employee_auto_deactivates_linked_user(): void
    {
        [$token] = $this->authenticateUser();

        // El actor "tester" ya tiene permiso de 'usuarios' manage? No: tiene
        // 'empleados'. Le damos ademas 'usuarios' manage para que exista
        // OTRO manager activo distinto del empleado a desvincular.
        $adminRoleId = DB::table('roles')->where('company_id', $this->companyId)->where('name', 'Tester')->value('id');
        $usuariosPermId = DB::table('permissions')->insertGetId([
            'module_name' => 'usuarios', 'action_name' => 'manage', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'role_id' => $adminRoleId, 'permission_id' => $usuariosPermId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $cajeroRoleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Ventas', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $cajeroUserId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId, 'role_id' => $cajeroRoleId, 'uuid' => (string) Str::uuid(),
            'username' => 'cajero1', 'password_hash' => bcrypt('password'), 'full_name' => 'Cajero Uno',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/employees', [
                'first_name' => 'Cajero', 'last_name' => 'Uno', 'status' => 'ACTIVE', 'user_id' => $cajeroUserId,
            ]);
        $employeeId = $create->json('item.id');

        $update = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/employees/{$employeeId}", [
                'first_name' => 'Cajero', 'last_name' => 'Uno', 'status' => 'TERMINATED',
            ]);

        $update->assertOk();
        $update->assertJsonPath('item.status', 'TERMINATED');
        $this->assertNotNull($update->json('item.termination_date'));
        $this->assertDatabaseHas('users', ['id' => $cajeroUserId, 'status' => 0]);
    }

    public function test_terminating_employee_does_not_deactivate_last_user_manager(): void
    {
        [$token, $actorUserId] = $this->authenticateUser();

        $adminRoleId = DB::table('roles')->where('company_id', $this->companyId)->where('name', 'Tester')->value('id');
        $usuariosPermId = DB::table('permissions')->insertGetId([
            'module_name' => 'usuarios', 'action_name' => 'manage', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'role_id' => $adminRoleId, 'permission_id' => $usuariosPermId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // El actor (unico manager de usuarios activo) es tambien el empleado
        // que se va a terminar.
        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/employees', [
                'first_name' => 'Actor', 'last_name' => 'Unico', 'status' => 'ACTIVE', 'user_id' => $actorUserId,
            ]);
        $employeeId = $create->json('item.id');

        $update = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/employees/{$employeeId}", [
                'first_name' => 'Actor', 'last_name' => 'Unico', 'status' => 'TERMINATED',
            ]);

        $update->assertOk();
        $this->assertStringContainsString('unico que puede administrar usuarios', $update->json('message'));
        $this->assertDatabaseHas('users', ['id' => $actorUserId, 'status' => 1]);
    }

    public function test_employee_cannot_be_deleted_when_referenced_by_customer_salesperson(): void
    {
        [$token] = $this->authenticateUser();

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/employees', ['first_name' => 'Con', 'last_name' => 'Clientes', 'status' => 'ACTIVE']);
        $employeeId = $create->json('item.id');

        DB::table('customers')->insert([
            'company_id' => $this->companyId, 'code' => 'CLI-000001', 'full_name' => 'Cliente Test',
            'currency_id' => 1, 'salesperson_id' => $employeeId, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->deleteJson("/api/employees/{$employeeId}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('employees', ['id' => $employeeId]);
    }
}
