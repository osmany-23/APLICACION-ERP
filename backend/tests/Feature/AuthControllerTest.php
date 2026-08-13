<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateUser(string $password = 'password123'): array
    {
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'NIO', 'name' => 'Cordoba', 'symbol' => 'C$', 'decimal_places' => 2,
            'is_base' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => 'Empresa Test', 'currency_id' => $currencyId, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'company_id' => $companyId,
            'uuid' => (string) Str::uuid(),
            'username' => 'tester',
            'password_hash' => Hash::make($password),
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

        return ['test-token', $userId];
    }

    public function test_me_includes_created_at(): void
    {
        [$token] = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/auth/me');

        $response->assertOk();
        $this->assertNotNull($response->json('user.created_at'));
    }

    public function test_user_can_update_own_profile(): void
    {
        [$token] = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/auth/me', [
                'full_name' => 'Nombre Actualizado',
                'email' => 'nuevo@example.com',
                'phone' => '8888-8888',
            ]);

        $response->assertOk();
        $response->assertJsonPath('user.full_name', 'Nombre Actualizado');
        $this->assertDatabaseHas('users', ['username' => 'tester', 'full_name' => 'Nombre Actualizado', 'email' => 'nuevo@example.com']);
    }

    public function test_profile_update_rejects_invalid_phone(): void
    {
        [$token] = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/auth/me', ['full_name' => 'Tester', 'phone' => 'llamame ya!']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone');
    }

    public function test_user_can_change_own_password(): void
    {
        [$token, $userId] = $this->authenticateUser('password123');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/change-password', [
                'current_password' => 'password123',
                'new_password' => 'nuevaClave456',
                'new_password_confirmation' => 'nuevaClave456',
            ]);

        $response->assertOk();

        $updated = DB::table('users')->where('id', $userId)->first();
        $this->assertTrue(Hash::check('nuevaClave456', $updated->password_hash));
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        [$token] = $this->authenticateUser('password123');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/change-password', [
                'current_password' => 'incorrecta',
                'new_password' => 'nuevaClave456',
                'new_password_confirmation' => 'nuevaClave456',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('current_password');
    }
}
