<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentMethodsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        if (! Schema::hasTable('payment_methods')) {
            $this->command?->info('Table payment_methods not found, skipping PaymentMethodsSeeder.');
            return;
        }

        DB::table('payment_methods')->upsert([
            ['code' => 'CASH', 'name' => 'Efectivo', 'description' => 'Pago en efectivo', 'cash' => true, 'is_active' => true, 'is_default' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'TRF', 'name' => 'Transferencia', 'description' => 'Transferencia bancaria', 'bank' => true, 'requires_reference' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CRT', 'name' => 'Tarjeta de Crédito', 'description' => 'Pago con tarjeta de crédito', 'card' => true, 'is_online' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DBT', 'name' => 'Tarjeta de Débito', 'description' => 'Pago con tarjeta de débito', 'card' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CHK', 'name' => 'Cheque', 'description' => 'Pago con cheque', 'check' => true, 'requires_reference' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DEP', 'name' => 'Depósito', 'description' => 'Depósito bancario', 'bank' => true, 'requires_reference' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PPAL', 'name' => 'PayPal', 'description' => 'Pago vía PayPal', 'digital_wallet' => true, 'is_online' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'WAL', 'name' => 'Billetera Digital', 'description' => 'Pago por billetera digital', 'digital_wallet' => true, 'is_online' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CRINT', 'name' => 'Crédito Interno', 'description' => 'Pago a crédito interno', 'credit' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'description', 'cash', 'card', 'bank', 'check', 'digital_wallet', 'credit', 'requires_reference', 'is_online', 'is_active', 'is_default', 'updated_at']);
    }
}
