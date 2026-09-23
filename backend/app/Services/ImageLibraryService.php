<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Capa de orquestacion sobre la tabla generica "images": aplica las reglas
 * de negocio (maximo de imagenes por entidad, cual es la portada/primary)
 * y delega la conversion real del archivo a ImageProcessingService. Los
 * controladores de Producto/Categoria/Marca/Empresa solo hablan con este
 * servicio, nunca tocan la tabla "images" directamente.
 */
class ImageLibraryService
{
    public function __construct(private readonly ImageProcessingService $processor)
    {
    }

    /**
     * Sube una imagen para una entidad de "una sola imagen" (categoria,
     * marca, logo de empresa, etc.): si ya existia una, la reemplaza.
     */
    public function storeSingle(
        UploadedFile $file,
        string $type,
        int $imageableId,
        int $companyId,
        ?int $userId,
    ): Image {
        $processed = $this->processor->process($file);

        return DB::transaction(function () use ($processed, $type, $imageableId, $companyId, $userId) {
            Image::query()
                ->where('imageable_type', $type)
                ->where('imageable_id', $imageableId)
                ->delete();

            return Image::create([
                ...$processed,
                'company_id' => $companyId,
                'imageable_type' => $type,
                'imageable_id' => $imageableId,
                'uploaded_by' => $userId,
                'is_primary' => true,
                'sort_order' => 0,
            ]);
        });
    }

    /**
     * Sube una imagen para una entidad de galeria (hoy solo Producto),
     * respetando un maximo de imagenes simultaneas.
     *
     * @throws ValidationException si ya se alcanzo el maximo permitido.
     */
    public function storeMultiple(
        UploadedFile $file,
        string $type,
        int $imageableId,
        int $companyId,
        ?int $userId,
        int $maxImages,
    ): Image {
        return DB::transaction(function () use ($file, $type, $imageableId, $companyId, $userId, $maxImages) {
            $existing = Image::query()
                ->where('imageable_type', $type)
                ->where('imageable_id', $imageableId)
                ->lockForUpdate()
                ->orderBy('sort_order')
                ->get();

            if ($existing->count() >= $maxImages) {
                throw ValidationException::withMessages([
                    'image' => "Este producto ya tiene el maximo de {$maxImages} imagenes permitidas. Elimina una para poder agregar otra.",
                ]);
            }

            $processed = $this->processor->process($file);
            $nextOrder = $existing->max('sort_order');
            $nextOrder = $nextOrder === null ? 0 : $nextOrder + 1;

            return Image::create([
                ...$processed,
                'company_id' => $companyId,
                'imageable_type' => $type,
                'imageable_id' => $imageableId,
                'uploaded_by' => $userId,
                'is_primary' => $existing->isEmpty(),
                'sort_order' => $nextOrder,
            ]);
        });
    }

    /**
     * Variante usada por el backfill de imagenes legadas: recibe el
     * binario ya descargado en vez de un UploadedFile de un formulario.
     */
    public function storeSingleFromBinary(
        string $binary,
        ?string $declaredMime,
        string $originalFilename,
        string $type,
        int $imageableId,
        int $companyId,
    ): Image {
        $processed = $this->processor->processBinary($binary, $declaredMime, $originalFilename);

        return DB::transaction(function () use ($processed, $type, $imageableId, $companyId) {
            Image::query()
                ->where('imageable_type', $type)
                ->where('imageable_id', $imageableId)
                ->delete();

            return Image::create([
                ...$processed,
                'company_id' => $companyId,
                'imageable_type' => $type,
                'imageable_id' => $imageableId,
                'uploaded_by' => null,
                'is_primary' => true,
                'sort_order' => 0,
            ]);
        });
    }

    public function delete(string $type, int $imageableId, int $imageId, int $companyId): void
    {
        DB::transaction(function () use ($type, $imageableId, $imageId, $companyId) {
            $image = Image::query()
                ->where('id', $imageId)
                ->where('imageable_type', $type)
                ->where('imageable_id', $imageableId)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (! $image) {
                return;
            }

            $wasPrimary = (bool) $image->is_primary;
            $image->delete();

            if ($wasPrimary) {
                $next = Image::query()
                    ->where('imageable_type', $type)
                    ->where('imageable_id', $imageableId)
                    ->orderBy('sort_order')
                    ->first();

                $next?->update(['is_primary' => true]);
            }
        });
    }

    public function setPrimary(string $type, int $imageableId, int $imageId, int $companyId): void
    {
        DB::transaction(function () use ($type, $imageableId, $imageId, $companyId) {
            $target = Image::query()
                ->where('id', $imageId)
                ->where('imageable_type', $type)
                ->where('imageable_id', $imageableId)
                ->where('company_id', $companyId)
                ->first();

            if (! $target) {
                throw ValidationException::withMessages(['image' => 'La imagen indicada no existe.']);
            }

            Image::query()
                ->where('imageable_type', $type)
                ->where('imageable_id', $imageableId)
                ->update(['is_primary' => false]);

            $target->update(['is_primary' => true]);
        });
    }

    /**
     * @return array<int, array{id:int,uuid:string,url:string,width:?int,height:?int,size_bytes:int,is_primary:bool,sort_order:int}>
     */
    public function listFor(string $type, int $imageableId): array
    {
        return Image::query()
            ->where('imageable_type', $type)
            ->where('imageable_id', $imageableId)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Image $image) => $image->toPublicArray())
            ->all();
    }

    /**
     * URL de la imagen principal de una entidad de una sola imagen (o de la
     * portada de una galeria), o null si no tiene ninguna. Pensado para
     * poblar campos "xxx_url" en los listados sin cambiar el contrato JSON
     * que ya consume el frontend.
     */
    public function primaryUrlFor(string $type, int $imageableId): ?string
    {
        $image = Image::query()
            ->where('imageable_type', $type)
            ->where('imageable_id', $imageableId)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->first(['id', 'uuid']);

        return $image?->url();
    }

    /**
     * Version "bulk" de primaryUrlFor(): resuelve la URL de portada para
     * muchas entidades del mismo tipo en una sola consulta (evita N+1 en
     * listados, p. ej. el catalogo de productos completo).
     *
     * @param array<int> $imageableIds
     * @return array<int, string> mapa imageable_id => url
     */
    public function primaryUrlsFor(string $type, array $imageableIds): array
    {
        if ($imageableIds === []) {
            return [];
        }

        return Image::query()
            ->where('imageable_type', $type)
            ->whereIn('imageable_id', $imageableIds)
            ->where('is_primary', true)
            ->get(['imageable_id', 'uuid'])
            ->mapWithKeys(fn (Image $image) => [(int) $image->imageable_id => $image->url()])
            ->all();
    }
}
