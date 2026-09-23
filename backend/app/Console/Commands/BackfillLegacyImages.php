<?php

namespace App\Console\Commands;

use App\Services\ImageLibraryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Migra las imagenes "legadas" (URLs externas guardadas en columnas
 * image_url/logo/logo_dark/favicon, de antes de existir el modulo de
 * imagenes en BLOB) hacia la nueva tabla "images": descarga cada imagen,
 * la procesa con el mismo pipeline de optimizacion (resize + WEBP q85) y
 * la guarda en MySQL.
 *
 * Pensado para correrse UNA sola vez, entre la migracion que crea la tabla
 * "images" y la migracion posterior que elimina las columnas
 * image_url/logo/logo_dark/favicon (patron expand -> migrar datos ->
 * contraer). No es destructivo: si una URL no se puede descargar o procesar,
 * se registra y se continua con las demas, nunca aborta todo el proceso.
 */
class BackfillLegacyImages extends Command
{
    protected $signature = 'images:backfill-legacy';

    protected $description = 'Migra image_url/logo/logo_dark/favicon (URLs o archivos locales) hacia la tabla images (BLOB optimizado)';

    public function handle(ImageLibraryService $imageLibrary): int
    {
        // Comando historico, pensado para correrse UNA vez entre las dos
        // migraciones del modulo de imagenes. Si alguien lo vuelve a
        // ejecutar despues de que la migracion de "contraccion" ya elimino
        // las columnas legadas, no hay nada que migrar: se avisa y se sale
        // limpio en vez de fallar con un error SQL de columna inexistente.
        if (! Schema::hasColumn('products', 'image_url')) {
            $this->info('Las columnas legadas (image_url/logo/logo_dark/favicon) ya no existen: no hay nada que migrar.');

            return self::SUCCESS;
        }

        $this->migrateEntity($imageLibrary, 'products', 'image_url', 'product', ['id', 'company_id', 'image_url']);
        $this->migrateEntity($imageLibrary, 'categories', 'image_url', 'category', ['id', 'company_id', 'image_url']);
        $this->migrateEntity($imageLibrary, 'brands', 'image_url', 'brand', ['id', 'company_id', 'image_url']);

        $companies = DB::table('companies')->get(['id', 'logo', 'logo_dark', 'favicon']);
        foreach ($companies as $company) {
            $this->migrateCompanyField($imageLibrary, (int) $company->id, $company->logo, 'company_logo');
            $this->migrateCompanyField($imageLibrary, (int) $company->id, $company->logo_dark, 'company_logo_dark');
            $this->migrateCompanyField($imageLibrary, (int) $company->id, $company->favicon, 'company_favicon');
        }

        $this->info('Backfill de imagenes legadas finalizado.');

        return self::SUCCESS;
    }

    private function migrateEntity(ImageLibraryService $imageLibrary, string $table, string $column, string $imageType, array $columns): void
    {
        $rows = DB::table($table)->whereNotNull($column)->where($column, '!=', '')->get($columns);

        if ($rows->isEmpty()) {
            $this->line("[{$table}] nada que migrar.");
            return;
        }

        $this->line("[{$table}] migrando {$rows->count()} imagen(es)...");

        foreach ($rows as $row) {
            $fetched = $this->fetchBinary($row->{$column});

            if (! $fetched) {
                $this->warn("  #{$row->id}: no se pudo descargar '{$row->{$column}}', se omite.");
                continue;
            }

            try {
                $imageLibrary->storeSingleFromBinary(
                    $fetched['binary'],
                    $fetched['mime'],
                    $fetched['filename'],
                    $imageType,
                    (int) $row->id,
                    (int) $row->company_id,
                );
                $this->line("  #{$row->id}: OK");
            } catch (\Throwable $e) {
                $this->warn("  #{$row->id}: fallo al procesar ({$e->getMessage()}), se omite.");
            }
        }
    }

    private function migrateCompanyField(ImageLibraryService $imageLibrary, int $companyId, ?string $value, string $imageType): void
    {
        if (! $value) {
            return;
        }

        $fetched = $this->fetchBinary($value);

        if (! $fetched) {
            $this->warn("[companies] #{$companyId} ({$imageType}): no se pudo descargar '{$value}', se omite.");
            return;
        }

        try {
            $imageLibrary->storeSingleFromBinary(
                $fetched['binary'],
                $fetched['mime'],
                $fetched['filename'],
                $imageType,
                $companyId,
                $companyId,
            );
            $this->line("[companies] #{$companyId} ({$imageType}): OK");
        } catch (\Throwable $e) {
            $this->warn("[companies] #{$companyId} ({$imageType}): fallo al procesar ({$e->getMessage()}), se omite.");
        }
    }

    /**
     * @return array{binary:string,mime:?string,filename:string}|null
     */
    private function fetchBinary(string $value): ?array
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            try {
                $response = Http::timeout(10)->retry(1, 200)->get($value);
            } catch (\Throwable) {
                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            return [
                'binary' => $response->body(),
                'mime' => $response->header('Content-Type'),
                'filename' => basename(parse_url($value, PHP_URL_PATH) ?: 'imagen'),
            ];
        }

        // Ruta local relativa al disco "public" (p. ej. "companies/1/logo-....png"),
        // que es como el flujo anterior (basado en Storage::disk('public')) las guardaba.
        $relative = ltrim(str_replace('/storage/', '', $value), '/');

        if (! Storage::disk('public')->exists($relative)) {
            return null;
        }

        return [
            'binary' => Storage::disk('public')->get($relative),
            'mime' => Storage::disk('public')->mimeType($relative) ?: null,
            'filename' => basename($relative),
        ];
    }
}
