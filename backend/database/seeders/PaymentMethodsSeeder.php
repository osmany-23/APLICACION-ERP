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

        $rows = [
            ['code' => 'CASH', 'name' => 'Efectivo', 'description' => 'Pago en efectivo', 'type' => 'CASH', 'cash' => true, 'allow_change' => true, 'is_default' => true, 'sort_order' => 10],
            ['code' => 'TRF', 'name' => 'Transferencia', 'description' => 'Transferencia bancaria', 'type' => 'BANK_TRANSFER', 'bank' => true, 'requires_reference' => true, 'requires_bank' => true, 'sort_order' => 20],
            ['code' => 'CRT', 'name' => 'Tarjeta de Credito', 'description' => 'Pago con tarjeta de credito', 'type' => 'CARD', 'card' => true, 'is_online' => true, 'sort_order' => 30],
            ['code' => 'DBT', 'name' => 'Tarjeta de Debito', 'description' => 'Pago con tarjeta de debito', 'type' => 'CARD', 'card' => true, 'sort_order' => 40],
            ['code' => 'CHK', 'name' => 'Cheque', 'description' => 'Pago con cheque', 'type' => 'CHECK', 'check' => true, 'requires_reference' => true, 'requires_bank' => true, 'sort_order' => 50],
            ['code' => 'DEP', 'name' => 'Deposito', 'description' => 'Deposito bancario', 'type' => 'DEPOSIT', 'bank' => true, 'requires_reference' => true, 'requires_bank' => true, 'sort_order' => 60],
            ['code' => 'PPAL', 'name' => 'PayPal', 'description' => 'Pago via PayPal', 'type' => 'DIGITAL_WALLET', 'digital_wallet' => true, 'is_online' => true, 'sort_order' => 70],
            ['code' => 'WAL', 'name' => 'Billetera Digital', 'description' => 'Pago por billetera digital', 'type' => 'DIGITAL_WALLET', 'digital_wallet' => true, 'is_online' => true, 'sort_order' => 80],
            ['code' => 'CRINT', 'name' => 'Credito Interno', 'description' => 'Pago a credito interno', 'type' => 'CREDIT_INTERNAL', 'credit' => true, 'sort_order' => 90],
        ];

        DB::table('payment_methods')->upsert(
            array_map(fn (array $row) => $this->row($row, $now), $rows),
            ['code'],
            [
                'name',
                'description',
                'type',
                'cash',
                'card',
                'bank',
                'check',
                'digital_wallet',
                'credit',
                'other',
                'requires_reference',
                'requires_bank',
                'requires_authorization',
                'allow_change',
                'allow_partial_payment',
                'is_online',
                'is_default',
                'is_active',
                'sort_order',
                'updated_at',
            ]
        );
    }

    private function row(array $values, mixed $now): array
    {
        return array_merge([
            'company_id' => null,
            'code' => null,
            'name' => null,
            'description' => null,
            'type' => 'OTHER',
            'cash' => false,
            'card' => false,
            'bank' => false,
            'check' => false,
            'digital_wallet' => false,
            'credit' => false,
            'other' => false,
            'requires_reference' => false,
            'requires_bank' => false,
            'requires_authorization' => false,
            'allow_change' => false,
            'allow_partial_payment' => true,
            'is_online' => false,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 100,
            'created_at' => $now,
            'updated_at' => $now,
        ], $values);
    }
}
