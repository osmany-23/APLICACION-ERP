<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;

        $products = $this->productsQuery($companyId)
            ->orderBy('products.short_name')
            ->orderBy('products.code')
            ->get()
            ->map(fn ($product) => $this->productPayload($product))
            ->values();

        $activeCount = $products->where('status', 1)->count();
        $lowStockCount = $products
            ->filter(fn ($product) => $product['status'] === 1 && $product['stock'] <= $product['minimum_stock'])
            ->count();
        $inventoryValue = $products->sum(fn ($product) => $product['stock'] * $product['cost']);
        $marginProducts = $products->filter(fn ($product) => $product['sale_price'] > 0);
        $averageMargin = $marginProducts->isEmpty()
            ? 0
            : $marginProducts->avg(fn ($product) => (($product['sale_price'] - $product['cost']) / $product['sale_price']) * 100);

        return response()->json([
            'data' => $products,
            'meta' => [
                'total' => $products->count(),
                'active' => $activeCount,
                'low_stock' => $lowStockCount,
                'inventory_value' => round($inventoryValue, 2),
                'average_margin' => round($averageMargin, 2),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;
        $validated = $this->validatedProduct($request);

        $product = DB::transaction(function () use ($companyId, $user, $validated) {
            $productId = DB::table('products')->insertGetId($this->productData($validated, $companyId));

            $this->persistStock(
                $productId,
                $companyId,
                (int) ($validated['warehouse_id'] ?? 0),
                (int) ($validated['branch_id'] ?? ($user->branch_id ?: 0)),
                (float) $validated['stock'],
                (float) $validated['cost'],
            );

            return $this->findProductPayload($companyId, $productId);
        });

        return response()->json([
            'message' => 'Producto guardado correctamente.',
            'product' => $product,
        ], 201);
    }

    public function update(Request $request, int $product): JsonResponse
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;
        $this->ensureProductExists($companyId, $product);
        $validated = $this->validatedProduct($request, $product);

        $updatedProduct = DB::transaction(function () use ($companyId, $product, $user, $validated) {
            DB::table('products')
                ->where('id', $product)
                ->where('company_id', $companyId)
                ->update($this->productData($validated, $companyId, false));

            $this->persistStock(
                $product,
                $companyId,
                (int) ($validated['warehouse_id'] ?? 0),
                (int) ($validated['branch_id'] ?? ($user->branch_id ?: 0)),
                (float) $validated['stock'],
                (float) $validated['cost'],
            );

            return $this->findProductPayload($companyId, $product);
        });

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'product' => $updatedProduct,
        ]);
    }

    public function destroy(Request $request, int $product): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $this->ensureProductExists($companyId, $product);

        try {
            DB::transaction(function () use ($companyId, $product) {
                DB::table('inventory_stock')
                    ->where('company_id', $companyId)
                    ->where('product_id', $product)
                    ->delete();

                DB::table('products')
                    ->where('id', $product)
                    ->where('company_id', $companyId)
                    ->delete();
            });

            return response()->json([
                'message' => 'Producto eliminado correctamente.',
                'deleted' => true,
            ]);
        } catch (QueryException) {
            DB::table('products')
                ->where('id', $product)
                ->where('company_id', $companyId)
                ->update(['status' => 0]);

            return response()->json([
                'message' => 'El producto tiene movimientos relacionados y fue marcado como inactivo.',
                'deleted' => false,
            ]);
        }
    }

    public function movements(Request $request, int $product): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $this->ensureProductExists($companyId, $product);

        $movements = DB::table('inventory_movements')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'inventory_movements.warehouse_id')
            ->where('inventory_movements.company_id', $companyId)
            ->where('inventory_movements.product_id', $product)
            ->orderByDesc('inventory_movements.movement_date')
            ->orderByDesc('inventory_movements.id')
            ->limit(100)
            ->get([
                'inventory_movements.id',
                'inventory_movements.movement_type',
                'inventory_movements.reference_table',
                'inventory_movements.quantity',
                'inventory_movements.stock_after',
                'inventory_movements.movement_date',
                'inventory_movements.created_at',
                'warehouses.name as warehouse_name',
            ])
            ->map(function ($movement) {
                $quantity = (float) ($movement->quantity ?? 0);
                $type = (string) ($movement->movement_type ?? '');
                $isInput = in_array($type, ['ENTRY', 'ADJUSTMENT'], true);

                return [
                    'id' => (int) $movement->id,
                    'date' => $movement->movement_date ?: $movement->created_at,
                    'detail' => trim(($type ?: 'MOVIMIENTO').' '.($movement->reference_table ?: '')),
                    'type' => $type,
                    'warehouse' => $movement->warehouse_name,
                    'input' => $isInput ? $quantity : 0,
                    'output' => $isInput ? 0 : $quantity,
                    'balance' => (float) ($movement->stock_after ?? 0),
                ];
            })
            ->values();

        return response()->json(['data' => $movements]);
    }

    private function productsQuery(int $companyId)
    {
        $stock = DB::table('inventory_stock')
            ->select(
                'product_id',
                DB::raw('COALESCE(SUM(quantity), 0) as current_stock'),
                DB::raw('MIN(warehouse_id) as primary_warehouse_id'),
            )
            ->groupBy('product_id');

        return DB::table('products')
            ->join('companies', 'companies.id', '=', 'products.company_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'products.supplier_id')
            ->leftJoin('categories as selected_categories', 'selected_categories.id', '=', 'products.category_id')
            ->leftJoin('categories as parent_categories', 'parent_categories.id', '=', 'selected_categories.parent_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->leftJoinSub($stock, 'stock', fn ($join) => $join->on('stock.product_id', '=', 'products.id'))
            ->leftJoin('warehouses as primary_warehouses', 'primary_warehouses.id', '=', 'stock.primary_warehouse_id')
            ->leftJoin('branches as primary_branches', 'primary_branches.id', '=', 'primary_warehouses.branch_id')
            ->where('products.company_id', $companyId)
            ->select([
                'products.id',
                'products.company_id',
                'companies.name as company_name',
                'products.supplier_id',
                'products.code',
                'products.barcode',
                'products.short_name',
                'products.long_name',
                'products.model',
                'products.cost',
                'products.price',
                'products.minimum_stock',
                'products.image_url',
                'products.status',
                'selected_categories.id as selected_category_id',
                'selected_categories.name as selected_category_name',
                'selected_categories.parent_id as selected_category_parent_id',
                'parent_categories.id as parent_category_id',
                'parent_categories.name as parent_category_name',
                'brands.id as brand_id',
                'brands.name as brand_name',
                'units.id as unit_id',
                'units.name as unit_name',
                'units.short_name as unit_short_name',
                'suppliers.name as supplier_name',
                'primary_warehouses.id as warehouse_id',
                'primary_warehouses.name as warehouse_name',
                'primary_branches.id as branch_id',
                'primary_branches.name as branch_name',
                DB::raw('COALESCE(stock.current_stock, 0) as current_stock'),
            ]);
    }

    private function findProductPayload(int $companyId, int $productId): array
    {
        $product = $this->productsQuery($companyId)
            ->where('products.id', $productId)
            ->first();

        abort_unless($product, 404, 'Producto no encontrado.');

        return $this->productPayload($product);
    }

    private function productPayload(object $product): array
    {
        $name = trim((string) ($product->short_name ?: $product->long_name ?: 'Producto sin nombre'));
        $description = trim((string) ($product->long_name ?? ''));

        if ($description === $name) {
            $description = '';
        }

        $status = (int) ($product->status ?? 0) === 1 ? 1 : 0;

        $hasParentCategory = (bool) ($product->selected_category_parent_id ?? null);
        $categoryId = $hasParentCategory
            ? (int) $product->parent_category_id
            : ($product->selected_category_id ? (int) $product->selected_category_id : null);
        $subcategoryId = $hasParentCategory ? (int) $product->selected_category_id : null;
        $categoryName = $hasParentCategory
            ? $product->parent_category_name
            : $product->selected_category_name;
        $subcategoryName = $hasParentCategory ? $product->selected_category_name : null;

        return [
            'id' => (int) $product->id,
            'company_id' => (int) $product->company_id,
            'company' => $product->company_name,
            'name' => $name,
            'code' => (string) ($product->code ?? ''),
            'barcode' => $product->barcode,
            'supplier_id' => $product->supplier_id ? (int) $product->supplier_id : null,
            'supplier' => $product->supplier_name,
            'category_id' => $categoryId,
            'category' => $categoryName ?: 'General',
            'subcategory_id' => $subcategoryId,
            'subcategory' => $subcategoryName,
            'brand_id' => $product->brand_id ? (int) $product->brand_id : null,
            'brand' => $product->brand_name ?: 'Generica',
            'unit_id' => $product->unit_id ? (int) $product->unit_id : null,
            'unit' => $product->unit_short_name ?: ($product->unit_name ?: 'UND'),
            'sale_price' => (float) ($product->price ?? 0),
            'cost' => (float) ($product->cost ?? 0),
            'stock' => (float) ($product->current_stock ?? 0),
            'minimum_stock' => (float) ($product->minimum_stock ?? 0),
            'warehouse_id' => $product->warehouse_id ? (int) $product->warehouse_id : null,
            'warehouse' => $product->warehouse_name,
            'branch_id' => $product->branch_id ? (int) $product->branch_id : null,
            'branch' => $product->branch_name,
            'status' => $status,
            'status_label' => $status === 1 ? 'Activo' : 'Inactivo',
            'description' => $description,
            'model' => $product->model,
            'image_url' => $product->image_url,
        ];
    }

    private function validatedProduct(Request $request, ?int $productId = null): array
    {
        $companyId = (int) $request->user()->company_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company_id' => ['nullable', 'integer', Rule::in([$companyId])],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'code')->ignore($productId),
            ],
            'barcode' => ['nullable', 'string', 'max:120'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('parent_id')),
            ],
            'category' => ['nullable', 'string', 'max:120'],
            'subcategory_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNotNull('parent_id')),
            ],
            'subcategory' => ['nullable', 'string', 'max:120'],
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'brand' => ['nullable', 'string', 'max:120'],
            'unit_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'unit' => ['nullable', 'string', 'max:50'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'cost' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['Activo', 'Inactivo', 'activo', 'inactivo', 1, 0, '1', '0', true, false])],
            'description' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:120'],
            'image_url' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Ingresa el nombre del producto.',
            'code.required' => 'Ingresa el codigo del producto.',
            'code.unique' => 'Ya existe un producto con ese codigo.',
            'sale_price.required' => 'Ingresa el precio de venta.',
            'cost.required' => 'Ingresa el costo.',
            'supplier_id.exists' => 'El proveedor seleccionado no existe.',
            'warehouse_id.exists' => 'El almacen seleccionado no existe.',
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
        ]);
    }

    private function productData(array $validated, int $companyId, bool $withCreatedAt = true): array
    {
        $data = [
            'company_id' => $companyId,
            'supplier_id' => (int) ($validated['supplier_id'] ?? 0) ?: null,
            'category_id' => $this->resolvedCategoryId($companyId, $validated),
            'brand_id' => $this->brandId(
                $companyId,
                $validated['brand'] ?? null,
                (int) ($validated['brand_id'] ?? 0),
            ),
            'unit_id' => $this->unitId(
                $companyId,
                $validated['unit'] ?? null,
                (int) ($validated['unit_id'] ?? 0),
            ),
            'code' => trim((string) $validated['code']),
            'barcode' => $this->nullableText($validated['barcode'] ?? null),
            'short_name' => trim((string) $validated['name']),
            'long_name' => $this->nullableText($validated['description'] ?? null),
            'model' => $this->nullableText($validated['model'] ?? null),
            'cost' => (float) $validated['cost'],
            'price' => (float) $validated['sale_price'],
            'minimum_stock' => (float) $validated['minimum_stock'],
            'image_url' => $this->nullableText($validated['image_url'] ?? null),
            'status' => $this->statusValue($validated['status']),
        ];

        if ($withCreatedAt) {
            $data['created_at'] = now();
        }

        return $data;
    }

    private function resolvedCategoryId(int $companyId, array $validated): ?int
    {
        $subcategoryId = (int) ($validated['subcategory_id'] ?? 0);

        if ($subcategoryId > 0) {
            $this->existingCategoryId($companyId, $subcategoryId, false);

            $categoryId = (int) ($validated['category_id'] ?? 0);

            if ($categoryId > 0) {
                $matchesParent = DB::table('categories')
                    ->where('company_id', $companyId)
                    ->where('id', $subcategoryId)
                    ->where('parent_id', $categoryId)
                    ->exists();

                abort_unless($matchesParent, 422, 'La subcategoria no pertenece a la categoria seleccionada.');
            }

            return $subcategoryId;
        }

        $categoryId = (int) ($validated['category_id'] ?? 0);

        if ($categoryId > 0) {
            $categoryId = $this->existingCategoryId($companyId, $categoryId, true);
        } else {
            $categoryId = $this->categoryId($companyId, $validated['category'] ?? null) ?? 0;
        }

        $subcategoryName = $this->nullableText($validated['subcategory'] ?? null);

        if ($categoryId > 0 && $subcategoryName) {
            return $this->subcategoryId($companyId, $categoryId, $subcategoryName);
        }

        return $categoryId > 0 ? $categoryId : null;
    }

    private function categoryId(int $companyId, ?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $category = DB::table('categories')
            ->where('company_id', $companyId)
            ->whereNull('parent_id')
            ->where('name', $name)
            ->first();

        if ($category) {
            return (int) $category->id;
        }

        return DB::table('categories')->insertGetId([
            'company_id' => $companyId,
            'parent_id' => null,
            'name' => $name,
            'margin_percent' => 0,
            'created_at' => now(),
        ]);
    }

    private function subcategoryId(int $companyId, int $categoryId, string $name): int
    {
        $subcategory = DB::table('categories')
            ->where('company_id', $companyId)
            ->where('parent_id', $categoryId)
            ->where('name', $name)
            ->first();

        if ($subcategory) {
            return (int) $subcategory->id;
        }

        return DB::table('categories')->insertGetId([
            'company_id' => $companyId,
            'parent_id' => $categoryId,
            'name' => $name,
            'margin_percent' => 0,
            'created_at' => now(),
        ]);
    }

    private function existingCategoryId(int $companyId, int $categoryId, bool $topLevel): int
    {
        $query = DB::table('categories')
            ->where('company_id', $companyId)
            ->where('id', $categoryId);

        if ($topLevel) {
            $query->whereNull('parent_id');
        } else {
            $query->whereNotNull('parent_id');
        }

        abort_unless($query->exists(), 422, 'La categoria seleccionada no existe.');

        return $categoryId;
    }

    private function brandId(int $companyId, ?string $name, int $brandId = 0): ?int
    {
        if ($brandId > 0) {
            $exists = DB::table('brands')
                ->where('company_id', $companyId)
                ->where('id', $brandId)
                ->exists();

            abort_unless($exists, 422, 'La marca seleccionada no existe.');

            return $brandId;
        }

        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $brand = DB::table('brands')
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->first();

        if ($brand) {
            return (int) $brand->id;
        }

        return DB::table('brands')->insertGetId([
            'company_id' => $companyId,
            'name' => $name,
        ]);
    }

    private function unitId(int $companyId, ?string $name, int $unitId = 0): ?int
    {
        if ($unitId > 0) {
            $exists = DB::table('units')
                ->where('company_id', $companyId)
                ->where('id', $unitId)
                ->exists();

            abort_unless($exists, 422, 'La unidad seleccionada no existe.');

            return $unitId;
        }

        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $unit = DB::table('units')
            ->where('company_id', $companyId)
            ->where(function ($query) use ($name) {
                $query->where('name', $name)->orWhere('short_name', $name);
            })
            ->first();

        if ($unit) {
            return (int) $unit->id;
        }

        return DB::table('units')->insertGetId([
            'company_id' => $companyId,
            'name' => $name,
            'short_name' => strtoupper(substr($name, 0, 20)),
        ]);
    }

    private function persistStock(
        int $productId,
        int $companyId,
        int $warehouseId,
        int $branchId,
        float $quantity,
        float $cost,
    ): void
    {
        $warehouseId = $this->warehouseId($companyId, $warehouseId, $branchId);
        $currentRows = DB::table('inventory_stock')
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->get(['id', 'warehouse_id']);

        if ($currentRows->count() === 1) {
            DB::table('inventory_stock')
                ->where('id', $currentRows->first()->id)
                ->update([
                    'warehouse_id' => $warehouseId,
                    'quantity' => $quantity,
                    'average_cost' => $cost,
                ]);

            return;
        }

        DB::table('inventory_stock')->updateOrInsert(
            [
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
            ],
            [
                'company_id' => $companyId,
                'quantity' => $quantity,
                'average_cost' => $cost,
            ],
        );
    }

    private function warehouseId(int $companyId, int $warehouseId, int $branchId): int
    {
        if ($warehouseId > 0) {
            $warehouse = DB::table('warehouses')
                ->where('company_id', $companyId)
                ->where('id', $warehouseId)
                ->first();

            abort_unless($warehouse, 422, 'El almacen seleccionado no existe.');

            if ($branchId > 0 && $warehouse->branch_id && (int) $warehouse->branch_id !== $branchId) {
                abort(422, 'El almacen no pertenece a la sucursal seleccionada.');
            }

            return $warehouseId;
        }

        $query = DB::table('warehouses')->where('company_id', $companyId);

        if ($branchId > 0) {
            $query->where('branch_id', $branchId);
        }

        $warehouse = $query->orderBy('id')->first()
            ?: DB::table('warehouses')->where('company_id', $companyId)->orderBy('id')->first();

        if ($warehouse) {
            return (int) $warehouse->id;
        }

        return DB::table('warehouses')->insertGetId([
            'company_id' => $companyId,
            'branch_id' => $branchId > 0 ? $branchId : null,
            'name' => 'Bodega Principal',
            'address' => null,
            'manager_name' => null,
            'created_at' => now(),
        ]);
    }

    private function ensureProductExists(int $companyId, int $productId): void
    {
        $exists = DB::table('products')
            ->where('id', $productId)
            ->where('company_id', $companyId)
            ->exists();

        abort_unless($exists, 404, 'Producto no encontrado.');
    }

    private function statusValue(mixed $status): int
    {
        return in_array($status, ['Activo', 'activo', 1, '1', true], true) ? 1 : 0;
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
