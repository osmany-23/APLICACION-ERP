<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentTermsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        if (! Schema::hasTable('payment_terms')) {
            $this->command?->info('Table payment_terms not found, skipping PaymentTermsSeeder.');
            return;
        }

        $rows = [
            ['code' => 'CONT', 'name' => 'Contado', 'description' => 'Pago inmediato', 'type' => 'CASH', 'days' => 0, 'cash' => true, 'is_default' => true, 'sort_order' => 10],
            ['code' => 'CR15', 'name' => 'Credito 15 dias', 'description' => 'Credito comercial a 15 dias', 'type' => 'CREDIT', 'days' => 15, 'credit' => true, 'sort_order' => 20],
            ['code' => 'CR30', 'name' => 'Credito 30 dias', 'description' => 'Credito comercial a 30 dias', 'type' => 'CREDIT', 'days' => 30, 'credit' => true, 'sort_order' => 30],
            ['code' => 'CR45', 'name' => 'Credito 45 dias', 'description' => 'Credito comercial a 45 dias', 'type' => 'CREDIT', 'days' => 45, 'credit' => true, 'sort_order' => 40],
            ['code' => 'CR60', 'name' => 'Credito 60 dias', 'description' => 'Credito comercial a 60 dias', 'type' => 'CREDIT', 'days' => 60, 'credit' => true, 'sort_order' => 50],
            ['code' => 'CR90', 'name' => 'Credito 90 dias', 'description' => 'Credito comercial a 90 dias', 'type' => 'CREDIT', 'days' => 90, 'credit' => true, 'sort_order' => 60],
            ['code' => 'ADV50', 'name' => 'Anticipo 50%', 'description' => 'Anticipo del 50 por ciento', 'type' => 'ADVANCE', 'days' => 0, 'advance' => true, 'down_payment_percent' => 50, 'sort_order' => 70],
            ['code' => 'ADV100', 'name' => 'Anticipo 100%', 'description' => 'Anticipo total', 'type' => 'ADVANCE', 'days' => 0, 'advance' => true, 'down_payment_percent' => 100, 'sort_order' => 80],
        ];

        DB::table('payment_terms')->upsert(
            array_map(fn (array $row) => $this->row($row, $now), $rows),
            ['code'],
            [
                'name',
                'description',
                'type',
                'cash',
                'credit',
                'advance',
                'days',
                'discount_percent',
                'discount_days',
                'late_fee_percent',
                'down_payment_percent',
                'allow_partial_payments',
                'installments',
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
            'type' => 'CREDIT',
            'cash' => false,
            'credit' => false,
            'advance' => false,
            'days' => 0,
            'discount_percent' => 0,
            'discount_days' => null,
            'late_fee_percent' => null,
            'down_payment_percent' => null,
            'allow_partial_payments' => false,
            'installments' => null,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 100,
            'created_at' => $now,
            'updated_at' => $now,
        ], $values);
    }
}
