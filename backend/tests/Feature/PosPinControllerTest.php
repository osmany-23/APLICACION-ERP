<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosPinControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    /** Usuario "cajero" en sesion (logueado en la PC compartida). */
    private function authenticateSessionUser(): string
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
            'company_id' => $this->companyId, 'name' => 'Sesion PC', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId,
            'role_id' => $roleId,
            'uuid' => (string) Str::uuid(),
            'username' => 'pc-compartida',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Sesion PC Compartida',
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

    private function createSalespersonWithPin(string $plainPin): int
    {
        $roleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Ventas', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('users')->insertGetId([
            'company_id' => $this->companyId,
            'role_id' => $roleId,
            'uuid' => (string) Str::uuid(),
            'username' => 'cajero-pin',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Cajero Con Pin',
            'status' => 1,
            'pin_hash' => Hash::make($plainPin),
            'pin_generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_resolve_returns_matching_user_for_valid_pin(): void
    {
        $token = $this->authenticateSessionUser();
        $salespersonId = $this->createSalespersonWithPin('4026');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/pos/pin/resolve', ['pin' => '4026']);

        $response->assertOk();
        $response->assertJsonPath('data.id', $salespersonId);
    }

    public function test_resolve_rejects_invalid_pin(): void
    {
        $token = $this->authenticateSessionUser();
        $this->createSalespersonWithPin('4026');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/pos/pin/resolve', ['pin' => '9999']);

        $response->assertStatus(422);
    }

    public function test_resolve_does_not_match_pin_from_another_company(): void
    {
        $token = $this->authenticateSessionUser();

        // Otra empresa con un vendedor que tiene el MISMO pin en texto plano.
        $otherCompanyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => 'Otra Empresa', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherRoleId = DB::table('roles')->insertGetId([
            'company_id' => $otherCompanyId, 'name' => 'Ventas', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('users')->insert([
            'company_id' => $otherCompanyId,
            'role_id' => $otherRoleId,
            'uuid' => (string) Str::uuid(),
            'username' => 'otro-cajero',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Cajero De Otra Empresa',
            'status' => 1,
            'pin_hash' => Hash::make('4026'),
            'pin_generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/pos/pin/resolve', ['pin' => '4026']);

        $response->assertStatus(422);
    }

    public function test_resolve_is_rate_limited_after_repeated_failures(): void
    {
        $token = $this->authenticateSessionUser();
        $this->createSalespersonWithPin('4026');

        for ($i = 0; $i < 8; $i++) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->postJson('/api/pos/pin/resolve', ['pin' => '0000']);
        }

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/pos/pin/resolve', ['pin' => '4026']);

        $response->assertStatus(429);
    }
}
