<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ErpBaseSeeder extends Seeder
{
    public function run(): void
    {
        // Only run seeds when the expected tables exist (defensive seeding)
        if (! Schema::hasTable('currencies')) {
            $this->command?->info('Table currencies not found, skipping ErpBaseSeeder.');
            return;
        }

        // 1. Moneda Base: Córdoba (Nicaragua)
        DB::table('currencies')->updateOrInsert([
            'code' => 'NIO',
        ], [
            'name' => 'Córdoba',
            'symbol' => 'C$',
            'decimal_places' => 2,
            'is_base' => true,
            'is_active' => true,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
        $currencyId = (int) DB::table('currencies')->where('code', 'NIO')->value('id');

        // Moneda secundaria opcional (Dólar)
        if (Schema::hasTable('currencies')) {
            DB::table('currencies')->updateOrInsert([
                'code' => 'USD',
            ], [
                'name' => 'Dólar Estadounidense',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_base' => false,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }

        // 2. Impuestos básicos (IVA 15%)
        if (Schema::hasTable('taxes')) {
            DB::table('taxes')->updateOrInsert([
                'code' => 'IVA15',
            ], [
                'name' => 'Impuesto al Valor Agregado 15%',
                'rate' => 15.0000,
                'type' => 'VAT',
                'is_default' => true,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }

        // 3. Seeders: condiciones y métodos de pago (centralizados)
        if (Schema::hasTable('payment_terms') && Schema::hasTable('payment_methods')) {
            $this->call([
                PaymentTermsSeeder::class,
                PaymentMethodsSeeder::class,
            ]);
        }

        // 5. Empresa Principal (Requerida por las llaves foráneas globales)
        if (! Schema::hasTable('companies')) {
            $this->command?->info('Table companies not found, skipping company/branch seeds.');
            return;
        }

        DB::table('companies')->updateOrInsert([
            'name' => 'Sigma Enterprise',
        ], [
            'uuid' => Str::uuid(),
            'legal_name' => 'Sigma Enterprise S.A.',
            'currency_id' => $currencyId ?: 1,
            'timezone' => 'America/Managua',
            'country' => 'Nicaragua',
            'status' => 1,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
        $companyId = (int) DB::table('companies')->where('name', 'Sigma Enterprise')->value('id');

        // 5. Sucursal Principal
        if (Schema::hasTable('branches')) {
            DB::table('branches')->updateOrInsert([
                'company_id' => $companyId,
                'code' => 'SUC-CENTRAL',
            ], [
                'uuid' => Str::uuid(),
                'name' => 'Sucursal Central',
                'is_headquarters' => true,
                'status' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
            $branchId = (int) DB::table('branches')
                ->where('company_id', $companyId)
                ->where('code', 'SUC-CENTRAL')
                ->value('id');
        }

        // 6. Bodega Principal
        if (Schema::hasTable('warehouses') && isset($branchId)) {
            DB::table('warehouses')->updateOrInsert([
                'company_id' => $companyId,
                'code' => 'BOD-01',
            ], [
                'branch_id' => $branchId,
                'name' => 'Bodega Central',
                'type' => 'PRINCIPAL',
                'status' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }

        // 7. Lista de Precios por defecto
        if (Schema::hasTable('price_lists') && isset($companyId)) {
            DB::table('price_lists')->updateOrInsert([
                'company_id' => $companyId,
                'code' => 'LISTA-GRAL',
            ], [
                'name' => 'Lista de Precios General',
                'is_default' => true,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }
}
