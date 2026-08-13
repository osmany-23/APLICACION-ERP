<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private function authenticateUser(array $modules = ['roles']): string
    {
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'NIO', 'name' => 'Cordoba', 'symbol' => 'C$', 'decimal_places' => 2,
            'is_base' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empresa Test',
            'currency_id' => $currencyId,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Tester', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($modules as $module) {
            $permissionId = DB::table('permissions')->insertGetId([
                'module_name' => $module,
                'action_name' => 'manage',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('role_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
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

        return 'test-token';
    }

    public function test_role_can_be_created_and_permissions_synced(): void
    {
        $token = $this->authenticateUser();

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/roles', ['name' => 'Cajero Turno Noche', 'description' => 'Solo ventas nocturnas']);

        $create->assertCreated();
        $roleId = $create->json('item.id');

        $permissionId = DB::table('permissions')->insertGetId([
            'module_name' => 'facturacion', 'action_name' => 'ver', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $sync = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/roles/{$roleId}/permissions", ['permission_ids' => [$permissionId]]);

        $sync->assertOk();
        $sync->assertJsonPath('item.permission_ids', [$permissionId]);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $roleId, 'permission_id' => $permissionId]);
    }

    public function test_role_cannot_be_deleted_when_it_has_users_assigned(): void
    {
        $token = $this->authenticateUser();

        $roleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Con usuarios', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('users')->insertGetId([
            'company_id' => $this->companyId,
            'role_id' => $roleId,
            'uuid' => (string) Str::uuid(),
            'username' => 'asignado',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Usuario Asignado',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->deleteJson("/api/roles/{$roleId}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('roles', ['id' => $roleId]);
    }
}
