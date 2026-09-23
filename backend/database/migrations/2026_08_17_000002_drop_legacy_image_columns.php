<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase de "contraccion" del nuevo modulo de imagenes: una vez que
 * `php artisan images:backfill-legacy` migro las URLs/archivos existentes
 * hacia la tabla "images" (BLOB), estas columnas ya no se usan en ningun
 * lado del codigo y se eliminan para que sea imposible volver a guardar
 * una URL o ruta de archivo por error. También se elimina la tabla
 * "product_images" original: nunca llego a tener modelo/controlador que la
 * usara (quedo como esqueleto del diseño inicial) y se reemplaza por la
 * tabla "images" generica (imageable_type = 'product').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['logo', 'logo_dark', 'favicon']);
        });

        Schema::dropIfExists('product_images');
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_url', 255)->nullable();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('image_url', 255)->nullable();
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('image_url', 255)->nullable();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('logo', 255)->nullable();
            $table->string('logo_dark', 255)->nullable();
            $table->string('favicon', 255)->nullable();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('image_url', 255);
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
