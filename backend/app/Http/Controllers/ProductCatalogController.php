<?php

namespace App\Http\Controllers;

use App\Traits\StatusUpdateable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductCatalogController extends Controller
{
    use StatusUpdateable;
    
    private const CATALOGS = ['brands', 'categories', 'subcategories', 'units'];

    public function index(Request $request, string $catalog): JsonResponse
    {
        $this->ensureCatalog($catalog);
        $companyId = (int) $request->user()->company_id;

        return response()->json([
            'data' => match ($catalog) {
                'brands' => $this->brands($companyId),
                'categories' => $this->categories($companyId),
                'subcategories' => $this->subcategories($companyId),
                'units' => $this->units($companyId),
            },
        ]);
    }

    public function store(Request $request, string $catalog): JsonResponse
    {
        $this->ensureCatalog($catalog);
        $companyId = (int) $request->user()->company_id;

        $id = match ($catalog) {
            'brands' => $this->storeBrand($request, $companyId),
            'categories' => $this->storeCategory($request, $companyId),
            'subcategories' => $this->storeSubcategory($request, $companyId),
            'units' => $this->storeUnit($request, $companyId),
        };

        return response()->json([
            'message' => $this->savedMessage($catalog),
            'item' => $this->findItem($companyId, $catalog, $id),
        ], 201);
    }

    public function update(Request $request, string $catalog, int $id): JsonResponse
    {
        $this->ensureCatalog($catalog);
        $companyId = (int) $request->user()->company_id;
        $this->ensureItemExists($companyId, $catalog, $id);

        match ($catalog) {
            'brands' => $this->updateBrand($request, $companyId, $id),
            'categories' => $this->updateCategory($request, $companyId, $id),
            'subcategories' => $this->updateSubcategory($request, $companyId, $id),
            'units' => $this->updateUnit($request, $companyId, $id),
        };

        return response()->json([
            'message' => $this->savedMessage($catalog),
            'item' => $this->findItem($companyId, $catalog, $id),
        ]);
    }

    public function destroy(Request $request, string $catalog, int $id): JsonResponse
    {
        $this->ensureCatalog($catalog);
        $companyId = (int) $request->user()->company_id;
        $this->ensureItemExists($companyId, $catalog, $id);

        $blockedMessage = $this->deleteBlockMessage($companyId, $catalog, $id);

        if ($blockedMessage) {
            return response()->json(['message' => $blockedMessage], 409);
        }

        try {
            match ($catalog) {
                'brands' => DB::table('brands')->where('id', $id)->where('company_id', $companyId)->delete(),
                'categories', 'subcategories' => DB::table('categories')->where('id', $id)->where('company_id', $companyId)->delete(),
                'units' => DB::table('units')->where('id', $id)->where('company_id', $companyId)->delete(),
            };
        } catch (QueryException) {
            return response()->json([
                'message' => 'No se puede eliminar porque tiene registros relacionados.',
            ], 409);
        }

        return response()->json([
            'message' => $this->deletedMessage($catalog),
            'deleted' => true,
        ]);
    }

    public function updateStatus(Request $request, string $catalog, int $id): JsonResponse
    {
        $this->ensureCatalog($catalog);
        $companyId = (int) $request->user()->company_id;
        $this->ensureItemExists($companyId, $catalog, $id);
        
        $validated = $this->validateStatusUpdate($request);

        $table = match ($catalog) {
            'brands' => 'brands',
            'categories', 'subcategories' => 'categories',
            'units' => 'units',
        };

        DB::table($table)
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->update(['is_active' => $validated['status']]);

        $item = DB::table($table)
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado correctamente.',
            'data' => [
                'id' => (int) $item->id,
                'is_active' => (bool) $item->is_active,
                'status_label' => $item->is_active ? 'Activo' : 'Inactivo',
            ],
        ], 200);
    }

    private function brands(int $companyId)
    {
        return DB::table('brands')
            ->where('brands.company_id', $companyId)
            ->select(['brands.id', 'brands.name', 'brands.description', 'brands.image_url', 'brands.is_active'])
            ->selectSub(function ($query) use ($companyId) {
                $query->from('products')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('products.brand_id', 'brands.id')
                    ->where('products.company_id', $companyId);
            }, 'products_count')
            ->orderBy('brands.name')
            ->get()
            ->map(fn ($brand) => [
                'id' => (int) $brand->id,
                'name' => (string) $brand->name,
                'description' => (string) ($brand->description ?? ''),
                'image_url' => (string) ($brand->image_url ?? ''),
                'is_active' => (bool) ($brand->is_active ?? true),
                'products_count' => (int) $brand->products_count,
            ])
            ->values();
    }

    private function categories(int $companyId)
    {
        return DB::table('categories')
            ->where('categories.company_id', $companyId)
            ->whereNull('categories.parent_id')
            ->select([
                'categories.id',
                'categories.code',
                'categories.name',
                'categories.description',
                'categories.image_url',
                'categories.level',
                'categories.margin_percent',
                'categories.is_active',
            ])
            ->selectSub(function ($query) use ($companyId) {
                $query->from('categories as children')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('children.parent_id', 'categories.id')
                    ->where('children.company_id', $companyId);
            }, 'subcategories_count')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('products')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('products.category_id', 'categories.id')
                    ->where('products.company_id', $companyId);
            }, 'products_count')
            ->orderBy('categories.name')
            ->get()
            ->map(fn ($category) => [
                'id' => (int) $category->id,
                'code' => (string) ($category->code ?? ''),
                'name' => (string) $category->name,
                'description' => (string) ($category->description ?? ''),
                'image_url' => (string) ($category->image_url ?? ''),
                'level' => (int) ($category->level ?? 1),
                'margin_percent' => (float) ($category->margin_percent ?? 0),
                'is_active' => (bool) ($category->is_active ?? true),
                'products_count' => (int) $category->products_count,
                'subcategories_count' => (int) $category->subcategories_count,
            ])
            ->values();
    }

    private function subcategories(int $companyId)
    {
        return DB::table('categories as subcategories')
            ->join('categories as parents', 'parents.id', '=', 'subcategories.parent_id')
            ->where('subcategories.company_id', $companyId)
            ->where('parents.company_id', $companyId)
            ->whereNotNull('subcategories.parent_id')
            ->select([
                'subcategories.id',
                'subcategories.code',
                'subcategories.name',
                'subcategories.description',
                'subcategories.image_url',
                'subcategories.level',
                'subcategories.parent_id',
                'subcategories.margin_percent',
                'subcategories.is_active',
                'parents.name as parent_name',
            ])
            ->selectSub(function ($query) use ($companyId) {
                $query->from('products')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('products.category_id', 'subcategories.id')
                    ->where('products.company_id', $companyId);
            }, 'products_count')
            ->orderBy('parents.name')
            ->orderBy('subcategories.name')
            ->get()
            ->map(fn ($subcategory) => [
                'id' => (int) $subcategory->id,
                'code' => (string) ($subcategory->code ?? ''),
                'name' => (string) $subcategory->name,
                'description' => (string) ($subcategory->description ?? ''),
                'image_url' => (string) ($subcategory->image_url ?? ''),
                'level' => (int) ($subcategory->level ?? 1),
                'parent_id' => (int) $subcategory->parent_id,
                'parent_name' => (string) $subcategory->parent_name,
                'margin_percent' => (float) ($subcategory->margin_percent ?? 0),
                'is_active' => (bool) ($subcategory->is_active ?? true),
                'products_count' => (int) $subcategory->products_count,
            ])
            ->values();
    }

    private function units(int $companyId)
    {
        return DB::table('units')
            ->where('units.company_id', $companyId)
            ->select([
                'units.id',
                'units.code',
                'units.name',
                'units.short_name',
                'units.category',
                'units.decimal_places',
                'units.is_active',
            ])
            ->selectSub(function ($query) use ($companyId) {
                $query->from('products')
                    ->selectRaw('COUNT(*)')
                    ->where(function ($query) {
                        $query->whereColumn('products.purchase_unit_id', 'units.id')
                            ->orWhereColumn('products.sale_unit_id', 'units.id');
                    })
                    ->where('products.company_id', $companyId);
            }, 'products_count')
            ->orderBy('units.name')
            ->get()
            ->map(fn ($unit) => [
                'id' => (int) $unit->id,
                'code' => (string) ($unit->code ?? ''),
                'name' => (string) $unit->name,
                'short_name' => (string) $unit->short_name,
                'category' => (string) ($unit->category ?? 'UNIDAD'),
                'decimal_places' => (int) ($unit->decimal_places ?? 0),
                'is_active' => (bool) ($unit->is_active ?? true),
                'products_count' => (int) $unit->products_count,
            ])
            ->values();
    }

    private function storeBrand(Request $request, int $companyId): int
    {
        $validated = $this->brandValidation($request, $companyId);

        return DB::transaction(function () use ($companyId, $validated) {
            return DB::table('brands')->insertGetId([
                'company_id' => $companyId,
                'code' => $this->generateBrandCode($companyId),
                'name' => $this->cleanName($validated['name']),
                'description' => $this->nullableText($validated['description'] ?? null),
                'image_url' => $this->nullableText($validated['image_url'] ?? null),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);
        });
    }

    private function updateBrand(Request $request, int $companyId, int $id): void
    {
        $validated = $this->brandValidation($request, $companyId, $id);

        DB::table('brands')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->update([
                'name' => $this->cleanName($validated['name']),
                'description' => $this->nullableText($validated['description'] ?? null),
                'image_url' => $this->nullableText($validated['image_url'] ?? null),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);
    }

    private function storeCategory(Request $request, int $companyId): int
    {
        $validated = $this->categoryValidation($request, $companyId);

        return DB::table('categories')->insertGetId([
            'company_id' => $companyId,
            'parent_id' => null,
            'code' => $this->generateCategoryCode($companyId),
            'name' => $this->cleanName($validated['name']),
            'description' => $this->nullableText($validated['description'] ?? null),
            'image_url' => $this->nullableText($validated['image_url'] ?? null),
            'level' => $this->resolveCategoryLevel($companyId, null, $validated['level'] ?? null),
            'margin_percent' => (float) ($validated['margin_percent'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function updateCategory(Request $request, int $companyId, int $id): void
    {
        $validated = $this->categoryValidation($request, $companyId, $id);

        DB::table('categories')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('parent_id')
            ->update([
                'name' => $this->cleanName($validated['name']),
                'description' => $this->nullableText($validated['description'] ?? null),
                'image_url' => $this->nullableText($validated['image_url'] ?? null),
                'level' => $this->resolveCategoryLevel($companyId, null, $validated['level'] ?? null),
                'margin_percent' => (float) ($validated['margin_percent'] ?? 0),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'updated_at' => now(),
            ]);
    }

    private function storeSubcategory(Request $request, int $companyId): int
    {
        $validated = $this->subcategoryValidation($request, $companyId);

        return DB::table('categories')->insertGetId([
            'company_id' => $companyId,
            'parent_id' => (int) $validated['parent_id'],
            'code' => $this->generateCategoryCode($companyId),
            'name' => $this->cleanName($validated['name']),
            'description' => $this->nullableText($validated['description'] ?? null),
            'image_url' => $this->nullableText($validated['image_url'] ?? null),
            'level' => $this->resolveCategoryLevel($companyId, (int) $validated['parent_id'], $validated['level'] ?? null),
            'margin_percent' => (float) ($validated['margin_percent'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function updateSubcategory(Request $request, int $companyId, int $id): void
    {
        $validated = $this->subcategoryValidation($request, $companyId, $id);

        DB::table('categories')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNotNull('parent_id')
            ->update([
                'parent_id' => (int) $validated['parent_id'],
                'name' => $this->cleanName($validated['name']),
                'description' => $this->nullableText($validated['description'] ?? null),
                'image_url' => $this->nullableText($validated['image_url'] ?? null),
                'level' => $this->resolveCategoryLevel($companyId, (int) $validated['parent_id'], $validated['level'] ?? null),
                'margin_percent' => (float) ($validated['margin_percent'] ?? 0),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'updated_at' => now(),
            ]);
    }

    private function storeUnit(Request $request, int $companyId): int
    {
        $validated = $this->unitValidation($request, $companyId);

        return DB::table('units')->insertGetId([
            'company_id' => $companyId,
            'code' => $this->generateUnitCode($companyId),
            'name' => $this->cleanName($validated['name']),
            'short_name' => strtoupper($this->cleanName($validated['short_name'])),
            'category' => strtoupper((string) ($validated['category'] ?? 'UNIDAD')),
            'decimal_places' => (int) ($validated['decimal_places'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function updateUnit(Request $request, int $companyId, int $id): void
    {
        $validated = $this->unitValidation($request, $companyId, $id);

        DB::table('units')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->update([
                'name' => $this->cleanName($validated['name']),
                'short_name' => strtoupper($this->cleanName($validated['short_name'])),
                'category' => strtoupper((string) ($validated['category'] ?? 'UNIDAD')),
                'decimal_places' => (int) ($validated['decimal_places'] ?? 0),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'updated_at' => now(),
            ]);
    }

    private function brandValidation(Request $request, int $companyId, ?int $id = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('brands', 'name')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ], $this->messages());
    }

    private function generateCategoryCode(int $companyId): string
    {
        $latestCode = DB::table('categories')
            ->where('company_id', $companyId)
            ->where('code', 'like', 'CAT%')
            ->orderByDesc('code')
            ->value('code');

        if (!$latestCode) {
            return 'CAT000001';
        }

        $numeric = (int) preg_replace('/^CAT0*/', '', $latestCode);
        $next = $numeric > 0 ? $numeric + 1 : 1;

        do {
            $candidate = sprintf('CAT%06d', $next);
            $exists = DB::table('categories')
                ->where('company_id', $companyId)
                ->where('code', $candidate)
                ->exists();
            $next += 1;
        } while ($exists);

        return $candidate;
    }

    private function generateUnitCode(int $companyId): string
    {
        $latestCode = DB::table('units')
            ->where('company_id', $companyId)
            ->where('code', 'like', 'UNT%')
            ->orderByDesc('code')
            ->value('code');

        if (!$latestCode) {
            return 'UNT000001';
        }

        $numeric = (int) preg_replace('/^UNT0*/', '', $latestCode);
        $next = $numeric > 0 ? $numeric + 1 : 1;

        do {
            $candidate = sprintf('UNT%06d', $next);
            $exists = DB::table('units')
                ->where('company_id', $companyId)
                ->where('code', $candidate)
                ->exists();
            $next += 1;
        } while ($exists);

        return $candidate;
    }

    private function resolveCategoryLevel(int $companyId, ?int $parentId, ?int $level): int
    {
        if ($level !== null) {
            return max(1, (int) $level);
        }

        if ($parentId) {
            $parentLevel = (int) DB::table('categories')
                ->where('id', $parentId)
                ->where('company_id', $companyId)
                ->value('level');

            return max(1, $parentLevel + 1);
        }

        return 1;
    }

    private function generateBrandCode(int $companyId): string
    {
        $latestCode = DB::table('brands')
            ->where('company_id', $companyId)
            ->where('code', 'like', 'BR%')
            ->orderByDesc('code')
            ->lockForUpdate()
            ->value('code');

        if (!$latestCode) {
            return 'BR000001';
        }

        $numeric = (int) preg_replace('/^BR0*/', '', $latestCode);
        $next = $numeric > 0 ? $numeric + 1 : 1;

        do {
            $candidate = sprintf('BR%06d', $next);
            $exists = DB::table('brands')
                ->where('company_id', $companyId)
                ->where('code', $candidate)
                ->exists();
            $next += 1;
        } while ($exists);

        return $candidate;
    }

    private function categoryValidation(Request $request, int $companyId, ?int $id = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('categories', 'name')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('parent_id'))
                    ->ignore($id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'margin_percent' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ], $this->messages());
    }

    private function subcategoryValidation(Request $request, int $companyId, ?int $id = null): array
    {
        $parentId = (int) $request->input('parent_id');

        return $request->validate([
            'parent_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('parent_id')),
            ],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('categories', 'name')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->where('parent_id', $parentId))
                    ->ignore($id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'margin_percent' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ], $this->messages());
    }

    private function unitValidation(Request $request, int $companyId, ?int $id = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('units', 'name')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($id),
            ],
            'short_name' => [
                'required',
                'string',
                'max:20',
                Rule::unique('units', 'short_name')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($id),
            ],
            'category' => ['nullable', 'string', 'max:20', 'in:UNIDAD,PESO,VOLUMEN,LONGITUD,OTRO'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
            'is_active' => ['nullable', 'boolean'],
        ], $this->messages());
    }

    private function findItem(int $companyId, string $catalog, int $id): array
    {
        $item = match ($catalog) {
            'brands' => $this->brands($companyId)->firstWhere('id', $id),
            'categories' => $this->categories($companyId)->firstWhere('id', $id),
            'subcategories' => $this->subcategories($companyId)->firstWhere('id', $id),
            'units' => $this->units($companyId)->firstWhere('id', $id),
        };

        abort_unless($item, 404, 'Registro no encontrado.');

        return $item;
    }

    private function ensureItemExists(int $companyId, string $catalog, int $id): void
    {
        $exists = match ($catalog) {
            'brands' => DB::table('brands')->where('id', $id)->where('company_id', $companyId)->exists(),
            'categories' => DB::table('categories')->where('id', $id)->where('company_id', $companyId)->whereNull('parent_id')->exists(),
            'subcategories' => DB::table('categories')->where('id', $id)->where('company_id', $companyId)->whereNotNull('parent_id')->exists(),
            'units' => DB::table('units')->where('id', $id)->where('company_id', $companyId)->exists(),
        };

        abort_unless($exists, 404, 'Registro no encontrado.');
    }

    private function deleteBlockMessage(int $companyId, string $catalog, int $id): ?string
    {
        if ($catalog === 'brands' && $this->productsUsing($companyId, 'brand_id', $id) > 0) {
            return 'No se puede eliminar la marca porque tiene productos relacionados.';
        }

        if ($catalog === 'units' && $this->productsUsingUnit($companyId, $id) > 0) {
            return 'No se puede eliminar la unidad porque tiene productos relacionados.';
        }

        if ($catalog === 'categories') {
            $subcategories = DB::table('categories')
                ->where('company_id', $companyId)
                ->where('parent_id', $id)
                ->count();

            if ($subcategories > 0) {
                return 'No se puede eliminar la categoria porque tiene subcategorias.';
            }

            if ($this->productsUsing($companyId, 'category_id', $id) > 0) {
                return 'No se puede eliminar la categoria porque tiene productos relacionados.';
            }
        }

        if ($catalog === 'subcategories' && $this->productsUsing($companyId, 'category_id', $id) > 0) {
            return 'No se puede eliminar la subcategoria porque tiene productos relacionados.';
        }

        return null;
    }

    private function productsUsing(int $companyId, string $field, int $id): int
    {
        return DB::table('products')
            ->where('company_id', $companyId)
            ->where($field, $id)
            ->count();
    }

    private function productsUsingUnit(int $companyId, int $id): int
    {
        return DB::table('products')
            ->where('company_id', $companyId)
            ->where(function ($query) use ($id) {
                $query->where('purchase_unit_id', $id)
                    ->orWhere('sale_unit_id', $id);
            })
            ->count();
    }

    private function ensureCatalog(string $catalog): void
    {
        abort_unless(in_array($catalog, self::CATALOGS, true), 404, 'Catalogo no encontrado.');
    }

    private function savedMessage(string $catalog): string
    {
        return match ($catalog) {
            'brands' => 'Marca guardada correctamente.',
            'categories' => 'Categoria guardada correctamente.',
            'subcategories' => 'Subcategoria guardada correctamente.',
            'units' => 'Unidad de medida guardada correctamente.',
        };
    }

    private function deletedMessage(string $catalog): string
    {
        return match ($catalog) {
            'brands' => 'Marca eliminada correctamente.',
            'categories' => 'Categoria eliminada correctamente.',
            'subcategories' => 'Subcategoria eliminada correctamente.',
            'units' => 'Unidad de medida eliminada correctamente.',
        };
    }

    private function cleanName(string $value): string
    {
        return trim($value);
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Ingresa el nombre.',
            'name.unique' => 'Ya existe un registro con ese nombre.',
            'parent_id.required' => 'Selecciona una categoria.',
            'parent_id.exists' => 'La categoria seleccionada no existe.',
            'short_name.required' => 'Ingresa la abreviatura.',
            'short_name.unique' => 'Ya existe una unidad con esa abreviatura.',
            'category.in' => 'La categoria de la unidad no es válida.',
            'decimal_places.integer' => 'Las posiciones decimales deben ser un número entero.',
            'level.integer' => 'El nivel debe ser un número entero.',
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
