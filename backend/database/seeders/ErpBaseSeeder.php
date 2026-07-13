<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ErpBaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Moneda Base: Córdoba (Nicaragua)
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'NIO',
            'name' => 'Córdoba',
            'symbol' => 'C$',
            'decimal_places' => 2,
            'is_base' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Moneda secundaria opcional (Dólar)
        DB::table('currencies')->insert([
            'code' => 'USD',
            'name' => 'Dólar Estadounidense',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_base' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Impuestos básicos (IVA 15%)
        DB::table('taxes')->insert([
            'code' => 'IVA15',
            'name' => 'Impuesto al Valor Agregado 15%',
            'rate' => 15.0000,
            'type' => 'VAT',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Condiciones de Pago por defecto
        $paymentTermId = DB::table('payment_terms')->insertGetId([
            'name' => 'Contado',
            'days' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_terms')->insert([
            'name' => 'Crédito 30 días',
            'days' => 30,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Métodos de Pago por defecto
        DB::table('payment_methods')->insert([
            ['code' => 'CASH', 'name' => 'Efectivo', 'description' => 'Pago en efectivo', 'requires_reference' => false, 'requires_bank' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'TRF', 'name' => 'Transferencia Bancaria', 'description' => 'Pago mediante transferencia bancaria', 'requires_reference' => true, 'requires_bank' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'DBT', 'name' => 'Tarjeta de Débito', 'description' => 'Pago con tarjeta de débito', 'requires_reference' => false, 'requires_bank' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CRT', 'name' => 'Tarjeta de Crédito', 'description' => 'Pago con tarjeta de crédito', 'requires_reference' => false, 'requires_bank' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CHK', 'name' => 'Cheque', 'description' => 'Pago con cheque', 'requires_reference' => true, 'requires_bank' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'DEP', 'name' => 'Depósito Bancario', 'description' => 'Pago mediante depósito bancario', 'requires_reference' => true, 'requires_bank' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'MOV', 'name' => 'Pago Móvil', 'description' => 'Pago con aplicación móvil', 'requires_reference' => false, 'requires_bank' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'INT', 'name' => 'Transferencia Internacional', 'description' => 'Pago por transferencia internacional', 'requires_reference' => true, 'requires_bank' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CRD', 'name' => 'Crédito', 'description' => 'Pago a crédito', 'requires_reference' => true, 'requires_bank' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'OTH', 'name' => 'Otro', 'description' => 'Otro método de pago', 'requires_reference' => false, 'requires_bank' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 5. Empresa Principal (Requerida por las llaves foráneas globales)
        $companyId = DB::table('companies')->insertGetId([
            'uuid' => Str::uuid(),
            'name' => 'Sigma Enterprise',
            'legal_name' => 'Sigma Enterprise S.A.',
            'currency_id' => $currencyId,
            'timezone' => 'America/Managua',
            'country' => 'Nicaragua',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Sucursal Principal
        $branchId = DB::table('branches')->insertGetId([
            'company_id' => $companyId,
            'uuid' => Str::uuid(),
            'code' => 'SUC-CENTRAL',
            'name' => 'Sucursal Central',
            'is_headquarters' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 6. Bodega Principal
        DB::table('warehouses')->insert([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'code' => 'BOD-01',
            'name' => 'Bodega Central',
            'type' => 'PRINCIPAL',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 7. Lista de Precios por defecto
        DB::table('price_lists')->insert([
            'company_id' => $companyId,
            'code' => 'LISTA-GRAL',
            'name' => 'Lista de Precios General',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}