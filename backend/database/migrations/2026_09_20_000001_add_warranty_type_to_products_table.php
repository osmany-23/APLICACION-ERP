<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * products.warranty_days ya existia en el esquema base pero nunca se
     * conecto a ningun formulario ni logica — esta migracion la completa
     * con un toggle explicito y el tipo/unidad que pidio el usuario, para
     * poder mostrar "Garantia de fabrica, 6 meses" en vez de solo un
     * numero de dias suelto.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_warranty')->default(false)->after('warranty_days');
            $table->string('warranty_period_unit', 10)->default('DAYS')->after('has_warranty')
                ->comment('DAYS, MONTHS o YEARS — unidad en la que el usuario captura la duracion; warranty_days siempre guarda el total ya convertido a dias.');
            $table->string('warranty_type', 120)->nullable()->after('warranty_period_unit')
                ->comment('Ej. "Garantia de fabrica", "Garantia de tienda"');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['has_warranty', 'warranty_period_unit', 'warranty_type']);
        });
    }
};
