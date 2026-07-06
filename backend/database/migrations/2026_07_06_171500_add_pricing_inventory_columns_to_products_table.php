<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'cost')) {
                $table->decimal('cost', 18, 4)->default(0)->after('cost_method');
            }
            if (!Schema::hasColumn('products', 'sale_price')) {
                $table->decimal('sale_price', 18, 4)->default(0)->after('cost');
            }
            if (!Schema::hasColumn('products', 'tax_type')) {
                $table->enum('tax_type', ['EXEMPT', 'TAXABLE'])->default('EXEMPT')->after('sale_price');
            }
            if (!Schema::hasColumn('products', 'tax_percentage')) {
                $table->decimal('tax_percentage', 10, 2)->default(0)->after('tax_type');
            }
            if (!Schema::hasColumn('products', 'sale_price_with_tax')) {
                $table->decimal('sale_price_with_tax', 18, 4)->default(0)->after('tax_percentage');
            }
            if (!Schema::hasColumn('products', 'allow_negative_stock')) {
                $table->boolean('allow_negative_stock')->default(false)->after('maximum_stock');
            }
            if (!Schema::hasColumn('products', 'manages_lots')) {
                $table->boolean('manages_lots')->default(false)->after('allow_negative_stock');
            }
            if (!Schema::hasColumn('products', 'manages_expiration')) {
                $table->boolean('manages_expiration')->default(false)->after('manages_lots');
            }
            if (!Schema::hasColumn('products', 'observations')) {
                $table->text('observations')->nullable()->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'observations')) {
                $table->dropColumn('observations');
            }
            if (Schema::hasColumn('products', 'manages_expiration')) {
                $table->dropColumn('manages_expiration');
            }
            if (Schema::hasColumn('products', 'manages_lots')) {
                $table->dropColumn('manages_lots');
            }
            if (Schema::hasColumn('products', 'allow_negative_stock')) {
                $table->dropColumn('allow_negative_stock');
            }
            if (Schema::hasColumn('products', 'sale_price_with_tax')) {
                $table->dropColumn('sale_price_with_tax');
            }
            if (Schema::hasColumn('products', 'tax_percentage')) {
                $table->dropColumn('tax_percentage');
            }
            if (Schema::hasColumn('products', 'tax_type')) {
                $table->dropColumn('tax_type');
            }
            if (Schema::hasColumn('products', 'sale_price')) {
                $table->dropColumn('sale_price');
            }
            if (Schema::hasColumn('products', 'cost')) {
                $table->dropColumn('cost');
            }
        });
    }
};
