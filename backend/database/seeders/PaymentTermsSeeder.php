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

        DB::table('payment_terms')->upsert([
            ['code' => 'CONT', 'name' => 'Contado', 'days' => 0, 'cash' => true, 'is_default' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CR15', 'name' => 'Crédito 15 días', 'days' => 15, 'credit' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CR30', 'name' => 'Crédito 30 días', 'days' => 30, 'credit' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CR45', 'name' => 'Crédito 45 días', 'days' => 45, 'credit' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CR60', 'name' => 'Crédito 60 días', 'days' => 60, 'credit' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CR90', 'name' => 'Crédito 90 días', 'days' => 90, 'credit' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ADV50', 'name' => 'Anticipo 50%', 'down_payment_percent' => 50, 'advance' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ADV100', 'name' => 'Anticipo 100%', 'down_payment_percent' => 100, 'advance' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name','days','discount_percent','discount_days','down_payment_percent','is_active','is_default','updated_at']);
    }
}
