<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasColumn('products', 'sale_price') ||
            ! Schema::hasColumn('products', 'sale_price_with_tax') ||
            ! Schema::hasColumn('products', 'cost')
        ) {
            return;
        }

        DB::statement(
            'UPDATE `products` p
             LEFT JOIN `categories` c ON c.`id` = p.`category_id`
             SET p.`sale_price` = ROUND(COALESCE(p.`cost`, 0) + (COALESCE(p.`cost`, 0) * COALESCE(c.`margin_percent`, 0) / 100), 4),
                 p.`sale_price_with_tax` = ROUND(
                    (COALESCE(p.`cost`, 0) + (COALESCE(p.`cost`, 0) * COALESCE(c.`margin_percent`, 0) / 100))
                    + ((COALESCE(p.`cost`, 0) + (COALESCE(p.`cost`, 0) * COALESCE(c.`margin_percent`, 0) / 100)) * COALESCE(p.`tax_percentage`, 0) / 100),
                    4
                 )
             WHERE COALESCE(p.`allow_sale`, 1) = 1
               AND COALESCE(p.`sale_price`, 0) = 0
               AND COALESCE(p.`cost`, 0) > 0'
        );
    }

    public function down(): void
    {
        //
    }
};
