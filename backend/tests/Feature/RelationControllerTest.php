<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RelationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regresion del bug confirmado: RelationController::countRows() consultaba
     * las tablas cash_registers/expenses/journal_entries sin verificar que
     * existieran, lo cual hacia fallar con SQL error el borrado de una
     * sucursal sin dependencias.
     */
    public function test_branch_can_be_deleted_when_it_has_no_dependencies(): void
    {
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'USD', 'name' => 'Dolar', 'symbol' => '$', 'decimal_places' => 2,
            'is_base' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => 'Empresa Test', 'currency_id' => $currencyId,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'company_id' => $companyId, 'uuid' => (string) Str::uuid(), 'username' => 'tester',
            'password_hash' => bcrypt('password'), 'full_name' => 'Tester', 'email' => 'tester@example.com',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('user_api_tokens')->insert([
            'user_id' => $userId, 'name' => 'Test token', 'token_hash' => hash('sha256', 'test-token'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'company_id' => $companyId, 'uuid' => (string) Str::uuid(), 'code' => 'SUC-002',
            'name' => 'Sucursal Secundaria', 'is_headquarters' => false, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->deleteJson("/api/relations/branches/{$branchId}");

        $response->assertOk();
        $this->assertDatabaseMissing('branches', ['id' => $branchId]);
    }
}
