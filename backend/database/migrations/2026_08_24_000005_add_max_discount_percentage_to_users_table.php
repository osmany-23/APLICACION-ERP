<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Limite personal de descuento del vendedor (no del rol: dos usuarios
     * con el mismo rol "Vendedor" pueden tener valores distintos, ej.
     * Osmany 2% / Carlos 1%). Se valida sobre salesperson_id, no sobre el
     * usuario logueado (ver SalesService::buildLines()/resolveSalespersonId()),
     * asi que sigue aplicando aunque la venta se registre desde una
     * terminal logueada con otro usuario e identificada por PIN. Null =
     * sin limite personal (solo quedan los topes de producto/general, si
     * estan configurados).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'max_discount_percentage')) {
                $table->decimal('max_discount_percentage', 5, 2)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'max_discount_percentage')) {
                $table->dropColumn('max_discount_percentage');
            }
        });
    }
};
