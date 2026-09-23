<?php

namespace App\Http\Controllers;

use App\Traits\StatusUpdateable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Tipos de precio" (price_lists): catalogo por empresa que un producto
 * puede usar para definir precios adicionales al precio base
 * (products.sale_price) — p. ej. "Mayorista", "Distribuidor". La cantidad
 * minima a la que aplica cada precio vive en product_price_list_items
 * (ver ProductController::syncPriceTiers()), esta tabla solo define el
 * catalogo de tipos, no los precios en si.
 */
class PriceListController extends Controller
{
    use StatusUpdateable;

    public function index(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;

        return response()->json(['data' => $this->items($companyId)]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $validated = $this->validatePriceList($request);

        $id = DB::transaction(function () use ($companyId, $validated) {
            $id = DB::table('price_lists')->insertGetId([
                'company_id' => $companyId,
                'code' => $this->generateCode($companyId, $validated['name']),
                'name' => trim($validated['name']),
                'description' => $this->nullableText($validated['description'] ?? null),
                'is_default' => (bool) ($validated['is_default'] ?? false),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncDefault($companyId, $id, (bool) ($validated['is_default'] ?? false));

            return $id;
        });

        return response()->json([
            'message' => 'Tipo de precio guardado correctamente.',
            'item' => $this->findItem($companyId, $id),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $this->ensureItemExists($companyId, $id);
        $validated = $this->validatePriceList($request, $id);

        DB::transaction(function () use ($companyId, $id, $validated) {
            DB::table('price_lists')
                ->where('id', $id)
                ->where('company_id', $companyId)
                ->update([
                    'name' => trim($validated['name']),
                    'description' => $this->nullableText($validated['description'] ?? null),
                    'is_default' => (bool) ($validated['is_default'] ?? false),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                    'updated_at' => now(),
                ]);

            $this->syncDefault($companyId, $id, (bool) ($validated['is_default'] ?? false));
        });

        return response()->json([
            'message' => 'Tipo de precio guardado correctamente.',
            'item' => $this->findItem($companyId, $id),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $this->ensureItemExists($companyId, $id);

        if ($this->isReferenced($id)) {
            return response()->json([
                'message' => 'No se puede eliminar porque esta asignado a productos.',
            ], 409);
        }

        try {
            DB::table('price_lists')->where('id', $id)->where('company_id', $companyId)->delete();
        } catch (QueryException) {
            return response()->json([
                'message' => 'No se puede eliminar porque tiene registros relacionados.',
            ], 409);
        }

        return response()->json([
            'message' => 'Tipo de precio eliminado correctamente.',
            'deleted' => true,
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $this->ensureItemExists($companyId, $id);
        $validated = $this->validateStatusUpdate($request);

        DB::table('price_lists')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->update(['is_active' => $validated['status'], 'updated_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado correctamente.',
            'item' => $this->findItem($companyId, $id),
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function items(int $companyId)
    {
        return DB::table('price_lists')
            ->where('company_id', $companyId)
            ->select(['id', 'code', 'name', 'description', 'is_default', 'is_active'])
            ->selectSub(function ($query) use ($companyId) {
                $query->from('product_price_list_items')
                    ->join('products', 'products.id', '=', 'product_price_list_items.product_id')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('product_price_list_items.price_list_id', 'price_lists.id')
                    ->where('products.company_id', $companyId);
            }, 'products_count')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'description' => $row->description,
                'is_default' => (bool) $row->is_default,
                'is_active' => (bool) $row->is_active,
                'products_count' => (int) $row->products_count,
            ])
            ->values();
    }

    private function findItem(int $companyId, int $id): array
    {
        $item = $this->items($companyId)->firstWhere('id', $id);

        abort_unless($item, 404, 'Tipo de precio no encontrado.');

        return $item;
    }

    private function ensureItemExists(int $companyId, int $id): void
    {
        abort_unless(
            DB::table('price_lists')->where('id', $id)->where('company_id', $companyId)->exists(),
            404,
            'Tipo de precio no encontrado.',
        );
    }

    private function isReferenced(int $priceListId): bool
    {
        return DB::table('product_price_list_items')
            ->where('price_list_id', $priceListId)
            ->exists();
    }

    private function syncDefault(int $companyId, int $id, bool $isDefault): void
    {
        if (! $isDefault) {
            return;
        }

        DB::table('price_lists')
            ->where('company_id', $companyId)
            ->where('id', '!=', $id)
            ->update(['is_default' => false]);
    }

    private function validatePriceList(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'El nombre es obligatorio.',
        ]);
    }

    // Codigo corto y legible (ej. "MAYORISTA", "MAYORISTA-2" si se repite),
    // no lo captura el usuario — mismo espiritu que ProductCatalogController,
    // aunque ahi generan codigos secuenciales (BR000001); aqui un slug del
    // nombre es mas legible para un catalogo tan chico y de tan baja
    // frecuencia de creacion.
    private function generateCode(int $companyId, string $name): string
    {
        $base = (string) Str::of($name)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', '-')->trim('-');
        $base = $base === '' ? 'TIPO' : Str::limit($base, 20, '');

        $candidate = $base;
        $suffix = 2;

        while (DB::table('price_lists')->where('company_id', $companyId)->where('code', $candidate)->exists()) {
            $candidate = Str::limit($base, 20 - strlen("-{$suffix}"), '') . "-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
