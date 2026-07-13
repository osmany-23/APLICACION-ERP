<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentTypeControllerTest extends TestCase
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

        $userId = DB::table('users')->insertGetId([
            'company_id' => $companyId,
            'uuid' => (string) Str::uuid(),
            'username' => 'document-user',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Document User',
            'email' => 'document@example.com',
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

    public function test_document_type_creation_starts_next_number_at_one_without_code_field(): void
    {
        $this->authenticateUser();

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/settings/document-types', [
                'name' => 'Factura',
                'prefix' => 'FACT',
                'is_active' => true,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.next_number', 1);
        $this->assertDatabaseHas('document_types', ['prefix' => 'FACT', 'next_number' => 1]);
    }

    public function test_document_number_generation_uses_the_document_type_prefix_sequence(): void
    {
        $documentType = DocumentType::create([
            'code' => 'FACT',
            'name' => 'Factura',
            'prefix' => 'FACT',
            'next_number' => 1,
            'is_active' => true,
        ]);

        $this->assertSame('FACT-000001', $documentType->generateDocumentNumber($documentType));
        $this->assertSame(2, (int) $documentType->fresh()->next_number);
    }
}
