<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'purchase_unit_id')) {
                $table->unsignedBigInteger('purchase_unit_id')->nullable()->after('supplier_id');
            }

            if (! Schema::hasColumn('products', 'sale_unit_id')) {
                $table->unsignedBigInteger('sale_unit_id')->nullable()->after('purchase_unit_id');
            }

            if (! Schema::hasColumn('products', 'conversion_factor')) {
                $table->decimal('conversion_factor', 18, 6)->default(1)->after('sale_unit_id');
            }

            if (! Schema::hasColumn('products', 'description')) {
                $table->text('description')->nullable()->after('long_name');
            }

            if (! Schema::hasColumn('products', 'cost')) {
                $table->decimal('cost', 18, 4)->default(0)->after('model');
            }

            if (! Schema::hasColumn('products', 'sale_price')) {
                $table->decimal('sale_price', 18, 4)->default(0)->after('cost');
            }

            if (! Schema::hasColumn('products', 'tax_type')) {
                $table->string('tax_type', 20)->default('EXEMPT')->after('sale_price');
            }

            if (! Schema::hasColumn('products', 'tax_percentage')) {
                $table->decimal('tax_percentage', 10, 2)->default(0)->after('tax_type');
            }

            if (! Schema::hasColumn('products', 'sale_price_with_tax')) {
                $table->decimal('sale_price_with_tax', 18, 4)->default(0)->after('tax_percentage');
            }

            if (! Schema::hasColumn('products', 'minimum_stock')) {
                $table->decimal('minimum_stock', 18, 4)->default(0)->after('sale_price_with_tax');
            }

            if (! Schema::hasColumn('products', 'maximum_stock')) {
                $table->decimal('maximum_stock', 18, 4)->nullable()->after('minimum_stock');
            }

            if (! Schema::hasColumn('products', 'allow_negative_stock')) {
                $table->boolean('allow_negative_stock')->default(false)->after('maximum_stock');
            }

            if (! Schema::hasColumn('products', 'manages_lots')) {
                $table->boolean('manages_lots')->default(false)->after('allow_negative_stock');
            }

            if (! Schema::hasColumn('products', 'manages_expiration')) {
                $table->boolean('manages_expiration')->default(false)->after('manages_lots');
            }

            if (! Schema::hasColumn('products', 'physical_location')) {
                $table->string('physical_location', 255)->nullable()->after('manages_expiration');
            }

            if (! Schema::hasColumn('products', 'notes')) {
                $table->text('notes')->nullable()->after('physical_location');
            }

            if (! Schema::hasColumn('products', 'observations')) {
                $table->text('observations')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('products', 'is_inventory')) {
                $table->boolean('is_inventory')->default(true)->after('status');
            }

            if (! Schema::hasColumn('products', 'is_service')) {
                $table->boolean('is_service')->default(false)->after('is_inventory');
            }

            if (! Schema::hasColumn('products', 'is_kit')) {
                $table->boolean('is_kit')->default(false)->after('is_service');
            }

            if (! Schema::hasColumn('products', 'allow_sale')) {
                $table->boolean('allow_sale')->default(true)->after('is_kit');
            }

            if (! Schema::hasColumn('products', 'allow_purchase')) {
                $table->boolean('allow_purchase')->default(true)->after('allow_sale');
            }

            if (! Schema::hasColumn('products', 'is_favorite')) {
                $table->boolean('is_favorite')->default(false)->after('allow_purchase');
            }

            if (! Schema::hasColumn('products', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('is_favorite');
            }

            if (! Schema::hasColumn('products', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }

            if (! Schema::hasColumn('products', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        $this->addForeignIfMissing('products', 'fk_products_purchase_unit', 'purchase_unit_id', 'units');
        $this->addForeignIfMissing('products', 'fk_products_sale_unit', 'sale_unit_id', 'units');
        $this->addForeignIfMissing('products', 'fk_products_created_by', 'created_by', 'users');
        $this->addForeignIfMissing('products', 'fk_products_updated_by', 'updated_by', 'users');

        if ($this->indexExists('products', 'products_code_unique')) {
            DB::statement('ALTER TABLE `products` DROP INDEX `products_code_unique`');
        }

        if (! $this->indexExists('products', 'uq_products_company_code')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unique(['company_id', 'code'], 'uq_products_company_code');
            });
        }

        if (! $this->indexExists('products', 'uq_products_company_barcode')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unique(['company_id', 'barcode'], 'uq_products_company_barcode');
            });
        }

        $this->syncProductDefaults();
        $this->enhanceInventoryMovements();
        $this->backfillInitialMovements();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach ([
                'purchase_unit_id',
                'sale_unit_id',
                'conversion_factor',
                'description',
                'cost',
                'sale_price',
                'tax_type',
                'tax_percentage',
                'sale_price_with_tax',
                'minimum_stock',
                'maximum_stock',
                'allow_negative_stock',
                'physical_location',
                'notes',
                'observations',
                'created_by',
                'updated_by',
                'updated_at',
            ] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function syncProductDefaults(): void
    {
        if (Schema::hasColumn('products', 'unit_id')) {
            DB::statement(
                'UPDATE `products`
                 SET `purchase_unit_id` = COALESCE(`purchase_unit_id`, `unit_id`),
                     `sale_unit_id` = COALESCE(`sale_unit_id`, `unit_id`)
                 WHERE `unit_id` IS NOT NULL'
            );
        }

        if (Schema::hasColumn('products', 'price')) {
            DB::statement(
                'UPDATE `products`
                 SET `sale_price` = COALESCE(NULLIF(`sale_price`, 0), `price`, 0)'
            );
        }

        if (Schema::hasColumn('products', 'tax_percent')) {
            DB::statement(
                'UPDATE `products`
                 SET `tax_percentage` = COALESCE(NULLIF(`tax_percentage`, 0), `tax_percent`, 0)'
            );
        }

        DB::statement(
            'UPDATE `products` p
             JOIN `inventory_stock` s ON s.product_id = p.id
             SET p.cost = s.average_cost
             WHERE COALESCE(p.cost, 0) = 0 AND COALESCE(s.average_cost, 0) > 0'
        );

        DB::statement(
            "UPDATE `products`
             SET `tax_type` = CASE WHEN COALESCE(`tax_percentage`, 0) > 0 THEN 'TAXABLE' ELSE 'EXEMPT' END"
        );

        DB::statement(
            'UPDATE `products`
             SET `sale_price_with_tax` = ROUND(COALESCE(`sale_price`, 0) + (COALESCE(`sale_price`, 0) * COALESCE(`tax_percentage`, 0) / 100), 4)'
        );
    }

    private function enhanceInventoryMovements(): void
    {
        DB::statement(
            "ALTER TABLE `inventory_movements`
             MODIFY `movement_type` ENUM('INITIAL_INVENTORY','ENTRY','EXIT','TRANSFER','ADJUSTMENT') NULL"
        );

        Schema::table('inventory_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_movements', 'inventory_status')) {
                $table->enum('inventory_status', ['RECEIVED', 'PENDING_RECEIPT', 'IN_TRANSIT', 'RESERVED'])
                    ->nullable()
                    ->after('movement_type');
            }

            if (! Schema::hasColumn('inventory_movements', 'lot_number')) {
                $table->string('lot_number', 100)->nullable()->after('stock_after');
            }

            if (! Schema::hasColumn('inventory_movements', 'expiration_date')) {
                $table->date('expiration_date')->nullable()->after('lot_number');
            }
        });
    }

    private function backfillInitialMovements(): void
    {
        DB::statement(
            "INSERT INTO `inventory_movements` (
                `company_id`,
                `warehouse_id`,
                `product_id`,
                `movement_type`,
                `inventory_status`,
                `reference_table`,
                `reference_id`,
                `quantity`,
                `unit_cost`,
                `total_cost`,
                `stock_before`,
                `stock_after`,
                `movement_date`,
                `created_by`,
                `created_at`
             )
             SELECT
                s.`company_id`,
                s.`warehouse_id`,
                s.`product_id`,
                'INITIAL_INVENTORY',
                'RECEIVED',
                'inventory_stock',
                s.`id`,
                COALESCE(s.`quantity`, 0),
                COALESCE(s.`average_cost`, 0),
                COALESCE(s.`quantity`, 0) * COALESCE(s.`average_cost`, 0),
                0,
                COALESCE(s.`quantity`, 0),
                NOW(),
                NULL,
                NOW()
             FROM `inventory_stock` s
             WHERE s.`product_id` IS NOT NULL
               AND NOT EXISTS (
                    SELECT 1
                    FROM `inventory_movements` m
                    WHERE m.`company_id` <=> s.`company_id`
                      AND m.`product_id` <=> s.`product_id`
               )"
        );
    }

    private function addForeignIfMissing(string $table, string $constraint, string $column, string $references): void
    {
        if (! Schema::hasColumn($table, $column) || $this->foreignKeyExists($table, $constraint)) {
            return;
        }

        DB::statement(
            sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`id`)',
                $table,
                $constraint,
                $column,
                $references,
            )
        );
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
