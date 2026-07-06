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
            if (!Schema::hasColumn('products', 'conversion_factor')) {
                $table->decimal('conversion_factor', 18, 6)
                    ->default(1)
                    ->after('sale_unit_id')
                    ->comment('Factor de conversión entre unidad de compra y unidad de venta');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'conversion_factor')) {
                $table->dropColumn('conversion_factor');
            }
        });
    }
};
