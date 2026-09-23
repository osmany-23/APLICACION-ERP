<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * products.measurement ya existia en el esquema base pero nunca se
     * conecto a ningun formulario — el usuario pidio un campo de texto
     * libre que no lo limite a un formato fijo (algunos productos se miden
     * "30x34x12", otros como "Altura: 30cm, Largo: 66cm, Grosor: 5cm"), asi
     * que se ensancha de varchar(50) a varchar(255) antes de exponerlo,
     * para no cortar a mitad una medida mas descriptiva.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('measurement', 255)->nullable()->comment('Medidas fisicas, formato libre. Ej: 30x34x12 o "Altura: 30cm, Largo: 66cm, Grosor: 5cm"')->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('measurement', 50)->nullable()->change();
        });
    }
};
