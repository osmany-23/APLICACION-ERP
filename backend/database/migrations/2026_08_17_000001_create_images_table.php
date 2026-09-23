<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Almacen unico y generico de imagenes para todo el ERP: cada foto se
 * guarda como binario (WebP optimizado) directamente en MySQL, nunca como
 * archivo fisico en disco ni como URL/Base64 en otras tablas.
 *
 * "imageable_type" identifica a que entidad pertenece la imagen ('product',
 * 'category', 'brand', 'company_logo', 'company_logo_dark',
 * 'company_favicon'). Para entidades de una sola imagen (categoria, marca,
 * logos de empresa) solo puede existir una fila por (imageable_type,
 * imageable_id); para productos se permiten hasta 5, controlado a nivel de
 * aplicacion (ImageLibraryService), con "is_primary"/"sort_order" para
 * decidir cual se muestra como portada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Identificador publico usado en la URL de la imagen (no correlativo, no adivinable)');
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('imageable_type', 40);
            $table->unsignedBigInteger('imageable_id');
            // Se crea como BLOB y se amplia a LONGBLOB justo abajo: Laravel
            // no tiene un helper longBlob() en el Schema Builder, así que el
            // tipo final se fuerza con SQL nativo para soportar fotos de
            // varios cientos de KB sin el limite de 64KB de un BLOB normal.
            $table->binary('data');
            $table->string('mime_type', 50)->default('image/webp');
            $table->unsignedInteger('size_bytes')->default(0);
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->char('checksum', 64)->nullable()->comment('sha256 del binario, usado como ETag para cache HTTP');
            $table->boolean('is_primary')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['imageable_type', 'imageable_id', 'sort_order'], 'images_imageable_index');
        });

        // Solo aplica en MySQL: binary() ya genera un BLOB de MySQL (limite
        // de 64KB), que se amplia a LONGBLOB con SQL nativo porque el
        // Schema Builder de Laravel no expone un helper longBlob(). En
        // SQLite (usado por el suite de tests) la columna BLOB no tiene ese
        // limite de tamaño, así que no hace falta (y la sintaxis MODIFY ni
        // siquiera existe ahí).
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE images MODIFY data LONGBLOB NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
