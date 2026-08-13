<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    /**
     * Crea una empresa + rol con los permisos indicados + usuario autenticado
     * con token fijo 'test-token'. Devuelve [token, userId, roleId].
     */
    private function authenticateUser(array $modulesWithActions = ['usuarios' => ['manage']]): array
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
            'company_id' => $this->companyId,
            'name' => 'Tester',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($modulesWithActions as $module => $actions) {
            foreach ($actions as $action) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'module_name' => $module,
                    'action_name' => $action,
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

    private function permissionId(string $module, string $action): int
    {
        return (int) (DB::table('permissions')->where('module_name', $module)->where('action_name', $action)->value('id')
            ?? DB::table('permissions')->insertGetId([
                'module_name' => $module,
                'action_name' => $action,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    public function test_user_can_be_created_with_role_and_listed(): void
    {
        [$token] = $this->authenticateUser();

        $ventasRoleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Ventas', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'username' => 'cajero1',
                'password' => 'password123',
                'full_name' => 'Cajero Uno',
                'role_id' => $ventasRoleId,
                'status' => true,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'username' => 'cajero1',
            'company_id' => $this->companyId,
            'role_id' => $ventasRoleId,
            'status' => 1,
        ]);

        $list = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/users');
        $list->assertOk();
        $list->assertJsonCount(2, 'data'); // tester + cajero1
    }

    public function test_user_creation_requires_password(): void
    {
        [$token] = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', ['username' => 'sinpass', 'full_name' => 'Sin Password']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_user_cannot_deactivate_own_account(): void
    {
        [$token, $userId] = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/{$userId}/status", ['status' => false]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $userId, 'status' => 1]);
    }

    public function test_cannot_deactivate_last_user_manager(): void
    {
        [$token, $userId, $roleId] = $this->authenticateUser();

        // Segundo usuario, sin permiso de gestionar usuarios (rol distinto).
        $ventasRoleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Ventas', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherUserId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId,
            'role_id' => $ventasRoleId,
            'uuid' => (string) Str::uuid(),
            'username' => 'cajero2',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Cajero Dos',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // cajero2 solo puede VER usuarios (entra al endpoint) pero no
        // "administrar" — no cuenta como manager para el guard. userId
        // (tester) es el UNICO manager activo de la empresa: intentar
        // desactivarlo debe bloquearse porque dejaria la empresa sin nadie
        // que pueda revertir el cambio.
        DB::table('user_api_tokens')->insert([
            'user_id' => $otherUserId,
            'name' => 'Other token',
            'token_hash' => hash('sha256', 'other-token'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'role_id' => $ventasRoleId,
            'permission_id' => $this->permissionId('usuarios', 'ver'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer other-token')
            ->patchJson("/api/users/{$userId}/status", ['status' => false]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $userId, 'status' => 1]);
    }

    public function test_generate_pin_requires_role_with_pin_permission(): void
    {
        [$token] = $this->authenticateUser();

        $roleWithoutPin = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Sin PIN', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $targetUserId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId,
            'role_id' => $roleWithoutPin,
            'uuid' => (string) Str::uuid(),
            'username' => 'sinpin',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Usuario Sin Pin',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/users/{$targetUserId}/pin");

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $targetUserId, 'pin_hash' => null]);
    }

    public function test_generate_and_revoke_pin_flow(): void
    {
        [$token] = $this->authenticateUser();

        $ventasRoleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Ventas', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'role_id' => $ventasRoleId,
            'permission_id' => $this->permissionId('usuarios', 'pin'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $targetUserId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId,
            'role_id' => $ventasRoleId,
            'uuid' => (string) Str::uuid(),
            'username' => 'cajero3',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Cajero Tres',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $generate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/users/{$targetUserId}/pin");

        $generate->assertOk();
        $pin = $generate->json('pin');
        $this->assertMatchesRegularExpression('/^\d{4}$/', $pin);

        $fresh = DB::table('users')->where('id', $targetUserId)->first();
        $this->assertNotNull($fresh->pin_hash);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($pin, $fresh->pin_hash));

        $revoke = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/users/{$targetUserId}/pin");

        $revoke->assertOk();
        $this->assertDatabaseHas('users', ['id' => $targetUserId, 'pin_hash' => null]);
    }
}
