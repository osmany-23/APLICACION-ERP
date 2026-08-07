<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentMethodsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateUser(): void
    {
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'USD',
            'name' => 'Dólar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_base' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empresa Test',
            'currency_id' => $currencyId,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'company_id' => $companyId,
            'name' => 'Administrador',
            'description' => 'Acceso completo a pruebas.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'company_id' => $companyId,
            'uuid' => (string) Str::uuid(),
            'username' => 'payment-user',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Payment User',
            'email' => 'payment@example.com',
            'role_id' => $roleId,
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
    }

    public function test_payment_methods_can_be_created_and_listed_in_their_own_api(): void
    {
        $this->authenticateUser();

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/settings/payment-methods', [
                'code' => 'TRF',
                'name' => 'Transferencia Bancaria',
                'description' => 'Pago por transferencia',
                'requires_reference' => true,
                'requires_bank' => true,
                'is_active' => true,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.name', 'Transferencia Bancaria');
        $this->assertDatabaseHas('payment_methods', [
            'code' => 'TRF',
            'name' => 'Transferencia Bancaria',
            'requires_reference' => true,
            'requires_bank' => true,
        ]);

        $listResponse = $this->withHeader('Authorization', 'Bearer test-token')
            ->getJson('/api/settings/payment-methods');

        $listResponse->assertOk();
        $this->assertGreaterThan(0, count($listResponse->json('data')));
    }
}
