<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'purchase_unit_id') || ! Schema::hasColumn('products', 'sale_unit_id')) {
            return;
        }

        DB::statement(
            'UPDATE `products` p
             JOIN (
                SELECT `company_id`, MIN(`id`) AS `unit_id`
                FROM `units`
                GROUP BY `company_id`
             ) u ON u.`company_id` = p.`company_id`
             SET p.`purchase_unit_id` = COALESCE(p.`purchase_unit_id`, u.`unit_id`),
                 p.`sale_unit_id` = COALESCE(p.`sale_unit_id`, u.`unit_id`)
             WHERE p.`purchase_unit_id` IS NULL
                OR p.`sale_unit_id` IS NULL'
        );
    }

    public function down(): void
    {
        //
    }
};
