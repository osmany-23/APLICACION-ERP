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
            if (! Schema::hasColumn('products', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('company_id');
                $table->foreign('supplier_id', 'fk_products_suppliers')
                    ->references('id')
                    ->on('suppliers');
                $table->index('supplier_id', 'idx_products_supplier');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'supplier_id')) {
                $table->dropForeign('fk_products_suppliers');
                $table->dropIndex('idx_products_supplier');
                $table->dropColumn('supplier_id');
            }
        });
    }
};
