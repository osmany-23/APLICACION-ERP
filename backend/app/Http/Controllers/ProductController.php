<?php

namespace App\Http\Controllers;

use App\Traits\StatusUpdateable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use StatusUpdateable;
    
    private const INVENTORY_STATUSES = [
        'RECEIVED' => 'Recibido',
        'PENDING_RECEIPT' => 'Pendiente por recibir',
        'IN_TRANSIT' => 'En transito',
        'RESERVED' => 'Reservado',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeProducts($request);
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
        $user = $this->authenticatedUser($request);
        $this->authorizeProducts($request);

        $companyId = (int) $user->company_id;
        $validated = $this->validatedProduct($request);

        $product = DB::transaction(function () use ($request, $companyId, $user, $validated) {
            $data = $this->productData($validated, $companyId, (int) $user->id);
            $productId = DB::table('products')->insertGetId($data);

            if ($this->boolValue($validated['is_inventory'] ?? true)) {
                $warehouseId = $this->warehouseId(
                    $companyId,
                    (int) $validated['warehouse_id'],
                    (int) ($validated['branch_id'] ?? ($user->branch_id ?: 0)),
                );

                $this->createInventoryMovement(
                    $companyId,
                    $productId,
                    $warehouseId,
                    'INITIAL_INVENTORY',
                    (float) ($validated['initial_stock'] ?? $validated['stock'] ?? 0),
                    (float) $data['cost'],
                    (int) $user->id,
                    $validated,
                );
                $this->syncInventoryStock($companyId, $productId, (float) $data['cost']);
                $this->syncInventoryLot($productId, $warehouseId, $validated, (float) $data['cost']);
            }

            $this->writeAudit($request, 'INSERT', $productId, null, $data);

            return $this->findProductPayload($companyId, $productId);
        });

        return response()->json([
            'message' => 'Producto guardado correctamente.',
            'product' => $product,
        ], 201);
    }

    public function show(Request $request, int $product): JsonResponse
    {
        $this->authorizeProducts($request);
        $companyId = (int) $request->user()->company_id;

        return response()->json([
            'product' => $this->findProductPayload($companyId, $product),
        ]);
    }

    public function update(Request $request, int $product): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeProducts($request);

        $companyId = (int) $user->company_id;
        $existing = $this->findProductRecord($companyId, $product);
        $validated = $this->validatedProduct($request, $product);

        $updatedProduct = DB::transaction(function () use ($request, $companyId, $product, $user, $validated, $existing) {
            $data = $this->productData($validated, $companyId, (int) $user->id, false);

            DB::table('products')
                ->where('id', $product)
                ->where('company_id', $companyId)
                ->update($data);

            if ($this->boolValue($validated['is_inventory'] ?? true)) {
                $warehouseId = $this->warehouseId(
                    $companyId,
                    (int) $validated['warehouse_id'],
                    (int) ($validated['branch_id'] ?? ($user->branch_id ?: 0)),
                );
                $targetStock = (float) ($validated['initial_stock'] ?? $validated['stock'] ?? 0);
                $currentStock = $this->currentStock($companyId, $product, $warehouseId);

                if (! $this->hasInventoryMovements($companyId, $product)) {
                    $this->createInventoryMovement(
                        $companyId,
                        $product,
                        $warehouseId,
                        'INITIAL_INVENTORY',
                        $targetStock,
                        (float) $data['cost'],
                        (int) $user->id,
                        $validated,
                    );
                } elseif (abs($targetStock - $currentStock) > 0.0001) {
                    $this->createInventoryMovement(
                        $companyId,
                        $product,
                        $warehouseId,
                        'ADJUSTMENT',
                        $targetStock - $currentStock,
                        (float) $data['cost'],
                        (int) $user->id,
                        $validated,
                    );
                }

                $this->syncInventoryStock($companyId, $product, (float) $data['cost']);
                $this->syncInventoryLot($product, $warehouseId, $validated, (float) $data['cost']);
            }

            $this->writeAudit($request, 'UPDATE', $product, (array) $existing, $data);

            return $this->findProductPayload($companyId, $product);
        });

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'product' => $updatedProduct,
        ]);
    }

    public function destroy(Request $request, int $product): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeProducts($request);

        $companyId = (int) $user->company_id;
        $existing = $this->findProductRecord($companyId, $product);

        try {
            DB::transaction(function () use ($request, $companyId, $product, $existing) {
                DB::table('inventory_lots')
                    ->where('product_id', $product)
                    ->delete();

                DB::table('inventory_stock')
                    ->where('company_id', $companyId)
                    ->where('product_id', $product)
                    ->delete();

                DB::table('products')
                    ->where('id', $product)
                    ->where('company_id', $companyId)
                    ->delete();

                $this->writeAudit($request, 'DELETE', $product, (array) $existing, null);
            });

            return response()->json([
                'message' => 'Producto eliminado correctamente.',
                'deleted' => true,
            ]);
        } catch (QueryException) {
            DB::table('products')
                ->where('id', $product)
                ->where('company_id', $companyId)
                ->update([
                    'status' => 0,
                    'updated_by' => $user->id,
                    'updated_at' => now(),
                ]);

            $this->writeAudit($request, 'UPDATE', $product, (array) $existing, [
                'status' => 0,
                'deleted_fallback' => 'marked_inactive',
            ]);

            return response()->json([
                'message' => 'El producto tiene movimientos relacionados y fue marcado como inactivo.',
                'deleted' => false,
            ]);
        }
    }

    public function updateStatus(Request $request, int $product): JsonResponse
    {
        $this->authorizeProducts($request);
        $user = $this->authenticatedUser($request);
        $companyId = (int) $user->company_id;
        $validated = $this->validateStatusUpdate($request);

        $existing = $this->findProductRecord($companyId, $product);

        DB::table('products')
            ->where('id', $product)
            ->where('company_id', $companyId)
            ->update([
                'status' => $validated['status'] ? 1 : 0,
                'updated_by' => $user->id,
                'updated_at' => now(),
            ]);

        $item = DB::table('products')
            ->where('id', $product)
            ->where('company_id', $companyId)
            ->first();

        $this->writeAudit($request, 'UPDATE', $product, (array) $existing, [
            'status' => $validated['status'] ? 1 : 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado correctamente.',
            'data' => [
                'id' => (int) $item->id,
                'status' => (bool) $item->status,
                'status_label' => $item->status ? 'Activo' : 'Inactivo',
            ],
        ], 200);
    }

    public function movements(Request $request, int $product): JsonResponse
    {
        $this->authorizeProducts($request);
        $companyId = (int) $request->user()->company_id;
        $this->findProductRecord($companyId, $product);

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
                'inventory_movements.inventory_status',
                'inventory_movements.reference_table',
                'inventory_movements.quantity',
                'inventory_movements.stock_after',
                'inventory_movements.lot_number',
                'inventory_movements.expiration_date',
                'inventory_movements.movement_date',
                'inventory_movements.created_at',
                'warehouses.name as warehouse_name',
            ])
            ->map(function ($movement) {
                $quantity = (float) ($movement->quantity ?? 0);
                $type = (string) ($movement->movement_type ?? '');
                $isOutput = $type === 'EXIT' || ($type === 'ADJUSTMENT' && $quantity < 0);
                $detail = $this->movementLabel($type);

                if ($movement->inventory_status) {
                    $detail .= ' - '.(self::INVENTORY_STATUSES[$movement->inventory_status] ?? $movement->inventory_status);
                }

                if ($movement->lot_number) {
                    $detail .= ' - Lote '.$movement->lot_number;
                }

                return [
                    'id' => (int) $movement->id,
                    'date' => $movement->movement_date ?: $movement->created_at,
                    'detail' => $detail,
                    'type' => $type,
                    'warehouse' => $movement->warehouse_name,
                    'input' => $isOutput ? 0 : abs($quantity),
                    'output' => $isOutput ? abs($quantity) : 0,
                    'balance' => (float) ($movement->stock_after ?? 0),
                    'expiration_date' => $movement->expiration_date,
                ];
            })
            ->values();

        return response()->json(['data' => $movements]);
    }

    private function productsQuery(int $companyId)
    {
        $stock = DB::table('inventory_movements')
            ->select(
                'product_id',
                DB::raw($this->stockSql().' as current_stock'),
                DB::raw('MIN(warehouse_id) as primary_warehouse_id'),
            )
            ->where('company_id', $companyId)
            ->groupBy('product_id');

        $lots = DB::table('inventory_lots')
            ->select(
                'product_id',
                DB::raw('MIN(lot_number) as lot_number'),
                DB::raw('MIN(expiration_date) as expiration_date'),
            )
            ->groupBy('product_id');

        return DB::table('products')
            ->join('companies', 'companies.id', '=', 'products.company_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'products.supplier_id')
            ->leftJoin('categories as selected_categories', 'selected_categories.id', '=', 'products.category_id')
            ->leftJoin('categories as parent_categories', 'parent_categories.id', '=', 'selected_categories.parent_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('units as purchase_units', 'purchase_units.id', '=', 'products.purchase_unit_id')
            ->leftJoin('units as sale_units', 'sale_units.id', '=', 'products.sale_unit_id')
            ->leftJoinSub($stock, 'stock', fn ($join) => $join->on('stock.product_id', '=', 'products.id'))
            ->leftJoinSub($lots, 'lots', fn ($join) => $join->on('lots.product_id', '=', 'products.id'))
            ->leftJoin('warehouses as primary_warehouses', 'primary_warehouses.id', '=', 'stock.primary_warehouse_id')
            ->leftJoin('branches as primary_branches', 'primary_branches.id', '=', 'primary_warehouses.branch_id')
            ->where('products.company_id', $companyId)
            ->select([
                'products.id',
                'products.company_id',
                'companies.name as company_name',
                'products.supplier_id',
                'products.purchase_unit_id',
                'products.sale_unit_id',
                'products.conversion_factor',
                'products.code',
                'products.barcode',
                'products.short_name',
                'products.long_name',
                'products.description',
                'products.model',
                'products.cost',
                'products.sale_price',
                'products.tax_type',
                'products.tax_percentage',
                'products.sale_price_with_tax',
                'products.minimum_stock',
                'products.maximum_stock',
                'products.allow_negative_stock',
                'products.manages_lots',
                'products.manages_expiration',
                'products.physical_location',
                'products.notes',
                'products.observations',
                'products.image_url',
                'products.status',
                'products.is_inventory',
                'products.is_service',
                'products.is_kit',
                'products.allow_sale',
                'products.allow_purchase',
                'products.is_favorite',
                'selected_categories.id as selected_category_id',
                'selected_categories.name as selected_category_name',
                'selected_categories.parent_id as selected_category_parent_id',
                'parent_categories.id as parent_category_id',
                'parent_categories.name as parent_category_name',
                'brands.id as brand_id',
                'brands.name as brand_name',
                'purchase_units.name as purchase_unit_name',
                'purchase_units.short_name as purchase_unit_short_name',
                'sale_units.name as sale_unit_name',
                'sale_units.short_name as sale_unit_short_name',
                'suppliers.name as supplier_name',
                'primary_warehouses.id as warehouse_id',
                'primary_warehouses.name as warehouse_name',
                'primary_branches.id as branch_id',
                'primary_branches.name as branch_name',
                'lots.lot_number',
                'lots.expiration_date',
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
        $purchaseUnit = $product->purchase_unit_short_name ?: $product->purchase_unit_name;
        $saleUnit = $product->sale_unit_short_name ?: $product->sale_unit_name;

        return [
            'id' => (int) $product->id,
            'company_id' => (int) $product->company_id,
            'company' => $product->company_name,
            'name' => $name,
            'short_name' => $name,
            'full_name' => $product->long_name,
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
            'unit_id' => $product->sale_unit_id ? (int) $product->sale_unit_id : null,
            'unit' => $saleUnit ?: 'UND',
            'purchase_unit_id' => $product->purchase_unit_id ? (int) $product->purchase_unit_id : null,
            'purchase_unit' => $purchaseUnit ?: 'UND',
            'sale_unit_id' => $product->sale_unit_id ? (int) $product->sale_unit_id : null,
            'sale_unit' => $saleUnit ?: 'UND',
            'conversion_factor' => (float) ($product->conversion_factor ?? 1),
            'sale_price' => (float) ($product->sale_price ?? 0),
            'sale_price_with_tax' => (float) ($product->sale_price_with_tax ?? 0),
            'tax_type' => $product->tax_type ?: 'EXEMPT',
            'tax_percentage' => (float) ($product->tax_percentage ?? 0),
            'cost' => (float) ($product->cost ?? 0),
            'stock' => (float) ($product->current_stock ?? 0),
            'initial_stock' => (float) ($product->current_stock ?? 0),
            'minimum_stock' => (float) ($product->minimum_stock ?? 0),
            'maximum_stock' => $product->maximum_stock !== null ? (float) $product->maximum_stock : null,
            'allow_negative_stock' => (bool) $product->allow_negative_stock,
            'manages_lots' => (bool) $product->manages_lots,
            'manages_expiration' => (bool) $product->manages_expiration,
            'lot_number' => $product->lot_number,
            'expiration_date' => $product->expiration_date,
            'warehouse_id' => $product->warehouse_id ? (int) $product->warehouse_id : null,
            'warehouse' => $product->warehouse_name,
            'branch_id' => $product->branch_id ? (int) $product->branch_id : null,
            'branch' => $product->branch_name,
            'status' => $status,
            'status_label' => $status === 1 ? 'Activo' : 'Inactivo',
            'description' => $product->description,
            'model' => $product->model,
            'physical_location' => $product->physical_location,
            'is_inventory' => (bool) $product->is_inventory,
            'is_service' => (bool) $product->is_service,
            'is_kit' => (bool) $product->is_kit,
            'allow_sale' => (bool) $product->allow_sale,
            'allow_purchase' => (bool) $product->allow_purchase,
            'is_favorite' => (bool) $product->is_favorite,
            'notes' => $product->notes,
            'observations' => $product->observations,
            'image_url' => $product->image_url,
        ];
    }

    private function validatedProduct(Request $request, ?int $productId = null): array
    {
        $companyId = (int) $request->user()->company_id;
        $taxType = $this->taxType($request->input('tax_type', 'EXEMPT'));
        $managesLots = $this->boolValue($request->input('manages_lots', false));
        $managesExpiration = $this->boolValue($request->input('manages_expiration', false));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', Rule::in([$companyId])],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'code')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($productId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('products', 'barcode')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($productId),
            ],
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('parent_id')),
            ],
            'subcategory_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNotNull('parent_id')),
            ],
            'brand_id' => [
                'required',
                'integer',
                Rule::exists('brands', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'purchase_unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'sale_unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'conversion_factor' => ['required', 'numeric', 'gt:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'cost' => ['required', 'numeric', 'min:0'],
            'tax_type' => ['required', Rule::in(['EXEMPT', 'TAXABLE', 'Exento', 'Gravado', 'exento', 'gravado'])],
            'tax_percentage' => [
                Rule::requiredIf($taxType === 'TAXABLE'),
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'initial_stock' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'inventory_status' => ['required', Rule::in(array_keys(self::INVENTORY_STATUSES))],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
            'maximum_stock' => ['nullable', 'numeric', 'min:0'],
            'allow_negative_stock' => ['sometimes', 'boolean'],
            'manages_lots' => ['sometimes', 'boolean'],
            'manages_expiration' => ['sometimes', 'boolean'],
            'lot_number' => [
                Rule::requiredIf($managesLots),
                'nullable',
                'string',
                'max:100',
            ],
            'expiration_date' => [
                Rule::requiredIf($managesExpiration),
                'nullable',
                'date',
            ],
            'status' => ['required', Rule::in(['Activo', 'Inactivo', 'activo', 'inactivo', 1, 0, '1', '0', true, false])],
            'description' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:120'],
            'physical_location' => ['nullable', 'string', 'max:255'],
            'is_inventory' => ['sometimes', 'boolean'],
            'is_service' => ['sometimes', 'boolean'],
            'is_kit' => ['sometimes', 'boolean'],
            'allow_sale' => ['sometimes', 'boolean'],
            'allow_purchase' => ['sometimes', 'boolean'],
            'is_favorite' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Ingresa el nombre corto del producto.',
            'code.required' => 'Ingresa el codigo interno del producto.',
            'code.unique' => 'Ya existe un producto con ese codigo interno.',
            'barcode.unique' => 'Ya existe un producto con ese codigo de barras.',
            'supplier_id.required' => 'Selecciona el proveedor principal.',
            'supplier_id.exists' => 'El proveedor seleccionado no existe.',
            'warehouse_id.required' => 'Selecciona el almacen inicial.',
            'warehouse_id.exists' => 'El almacen seleccionado no existe.',
            'category_id.required' => 'Selecciona la categoria.',
            'brand_id.required' => 'Selecciona la marca.',
            'purchase_unit_id.required' => 'Selecciona la unidad de compra.',
            'sale_unit_id.required' => 'Selecciona la unidad de venta.',
            'conversion_factor.gt' => 'El factor de conversion debe ser mayor que cero.',
            'tax_percentage.required' => 'Ingresa el porcentaje de impuesto.',
            'tax_percentage.max' => 'El porcentaje de impuesto no puede ser mayor a 100.',
            'minimum_stock.required' => 'Ingresa la alerta de existencia minima.',
            'lot_number.required' => 'Ingresa el numero de lote.',
            'expiration_date.required' => 'Ingresa la fecha de vencimiento.',
        ]);

        $this->validateProductRules($companyId, $validated);

        return $validated;
    }

    private function validateProductRules(int $companyId, array $validated): void
    {
        $categoryId = (int) $validated['category_id'];
        $subcategoryId = (int) ($validated['subcategory_id'] ?? 0);

        if ($subcategoryId > 0) {
            $matchesParent = DB::table('categories')
                ->where('company_id', $companyId)
                ->where('id', $subcategoryId)
                ->where('parent_id', $categoryId)
                ->exists();

            abort_unless($matchesParent, 422, 'La subcategoria no pertenece a la categoria seleccionada.');
        }

        $maximumStock = $this->nullableNumber($validated['maximum_stock'] ?? null);
        $minimumStock = (float) $validated['minimum_stock'];

        if ($maximumStock !== null && $minimumStock > $maximumStock) {
            abort(422, 'La existencia minima no puede ser mayor que la existencia maxima.');
        }

        if ($this->boolValue($validated['allow_sale'] ?? true) && (float) $validated['sale_price'] <= 0) {
            abort(422, 'El precio de venta debe ser mayor que cero para productos permitidos para venta.');
        }

        if ($this->boolValue($validated['is_inventory'] ?? true) && $this->boolValue($validated['is_service'] ?? false)) {
            abort(422, 'Un servicio no puede marcarse tambien como producto inventariable.');
        }

        $warehouse = DB::table('warehouses')
            ->where('company_id', $companyId)
            ->where('id', (int) $validated['warehouse_id'])
            ->first();

        if ($warehouse && isset($validated['branch_id']) && (int) $validated['branch_id'] > 0 && $warehouse->branch_id && (int) $warehouse->branch_id !== (int) $validated['branch_id']) {
            abort(422, 'El almacen no pertenece a la sucursal seleccionada.');
        }
    }

    private function productData(array $validated, int $companyId, int $userId, bool $withCreatedAt = true): array
    {
        $salePrice = (float) $validated['sale_price'];
        $taxType = $this->taxType($validated['tax_type']);
        $taxPercentage = $taxType === 'TAXABLE' ? (float) ($validated['tax_percentage'] ?? 0) : 0;
        $isService = $this->boolValue($validated['is_service'] ?? false);
        $isInventory = $isService ? false : $this->boolValue($validated['is_inventory'] ?? true);

        $data = [
            'company_id' => $companyId,
            'supplier_id' => (int) $validated['supplier_id'],
            'purchase_unit_id' => (int) $validated['purchase_unit_id'],
            'sale_unit_id' => (int) $validated['sale_unit_id'],
            'conversion_factor' => (float) $validated['conversion_factor'],
            'category_id' => (int) ($validated['subcategory_id'] ?? 0) ?: (int) $validated['category_id'],
            'brand_id' => (int) $validated['brand_id'],
            'code' => trim((string) $validated['code']),
            'barcode' => $this->nullableText($validated['barcode'] ?? null),
            'short_name' => trim((string) $validated['name']),
            'long_name' => $this->nullableText($validated['full_name'] ?? null),
            'description' => $this->nullableText($validated['description'] ?? null),
            'model' => $this->nullableText($validated['model'] ?? null),
            'cost' => (float) $validated['cost'],
            'sale_price' => $salePrice,
            'tax_type' => $taxType,
            'tax_percentage' => $taxPercentage,
            'sale_price_with_tax' => round($salePrice + ($salePrice * $taxPercentage / 100), 4),
            'minimum_stock' => (float) $validated['minimum_stock'],
            'maximum_stock' => $this->nullableNumber($validated['maximum_stock'] ?? null),
            'allow_negative_stock' => $this->boolValue($validated['allow_negative_stock'] ?? false),
            'manages_lots' => $this->boolValue($validated['manages_lots'] ?? false),
            'manages_expiration' => $this->boolValue($validated['manages_expiration'] ?? false),
            'physical_location' => $this->nullableText($validated['physical_location'] ?? null),
            'image_url' => $this->nullableText($validated['image_url'] ?? null),
            'status' => $this->statusValue($validated['status']),
            'is_inventory' => $isInventory,
            'is_service' => $isService,
            'is_kit' => $this->boolValue($validated['is_kit'] ?? false),
            'allow_sale' => $this->boolValue($validated['allow_sale'] ?? true),
            'allow_purchase' => $this->boolValue($validated['allow_purchase'] ?? true),
            'is_favorite' => $this->boolValue($validated['is_favorite'] ?? false),
            'notes' => $this->nullableText($validated['notes'] ?? null),
            'observations' => $this->nullableText($validated['observations'] ?? null),
            'updated_by' => $userId,
            'updated_at' => now(),
        ];

        if ($withCreatedAt) {
            $data['created_by'] = $userId;
            $data['created_at'] = now();
        }

        return $data;
    }

    private function createInventoryMovement(
        int $companyId,
        int $productId,
        int $warehouseId,
        string $movementType,
        float $quantity,
        float $unitCost,
        int $userId,
        array $validated,
    ): void {
        $stockBefore = $this->currentStock($companyId, $productId, $warehouseId);
        $stockAfter = $stockBefore + $quantity;

        DB::table('inventory_movements')->insert([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_type' => $movementType,
            'inventory_status' => $validated['inventory_status'] ?? 'RECEIVED',
            'reference_table' => 'products',
            'reference_id' => $productId,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'lot_number' => $this->nullableText($validated['lot_number'] ?? null),
            'expiration_date' => $this->nullableText($validated['expiration_date'] ?? null),
            'movement_date' => now(),
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }

    private function syncInventoryStock(int $companyId, int $productId, float $cost): void
    {
        $rows = DB::table('inventory_movements')
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->whereNotNull('warehouse_id')
            ->select('warehouse_id', DB::raw($this->stockSql().' as quantity'))
            ->groupBy('warehouse_id')
            ->get();

        DB::table('inventory_stock')
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->delete();

        foreach ($rows as $row) {
            DB::table('inventory_stock')->insert([
                'company_id' => $companyId,
                'warehouse_id' => (int) $row->warehouse_id,
                'product_id' => $productId,
                'quantity' => (float) $row->quantity,
                'average_cost' => $cost,
            ]);
        }
    }

    private function syncInventoryLot(int $productId, int $warehouseId, array $validated, float $cost): void
    {
        if (! $this->boolValue($validated['manages_lots'] ?? false) && ! $this->boolValue($validated['manages_expiration'] ?? false)) {
            return;
        }

        $quantity = (float) ($validated['initial_stock'] ?? $validated['stock'] ?? 0);
        $data = [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'lot_number' => $this->nullableText($validated['lot_number'] ?? null),
            'expiration_date' => $this->nullableText($validated['expiration_date'] ?? null),
            'quantity' => $quantity,
            'cost' => $cost,
        ];
        $existingLot = DB::table('inventory_lots')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->orderBy('id')
            ->first();

        if ($existingLot) {
            DB::table('inventory_lots')->where('id', $existingLot->id)->update($data);
            return;
        }

        DB::table('inventory_lots')->insert($data);
    }

    private function currentStock(int $companyId, int $productId, ?int $warehouseId = null): float
    {
        $query = DB::table('inventory_movements')
            ->where('company_id', $companyId)
            ->where('product_id', $productId);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return (float) ($query->value(DB::raw($this->stockSql())) ?? 0);
    }

    private function hasInventoryMovements(int $companyId, int $productId): bool
    {
        return DB::table('inventory_movements')
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->exists();
    }

    private function warehouseId(int $companyId, int $warehouseId, int $branchId): int
    {
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

    private function findProductRecord(int $companyId, int $productId): object
    {
        $product = DB::table('products')
            ->where('id', $productId)
            ->where('company_id', $companyId)
            ->first();

        abort_unless($product, 404, 'Producto no encontrado.');

        return $product;
    }

    private function authorizeProducts(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && $user->company_id, 403, 'No hay empresa asociada al usuario autenticado.');

        if (! $user->role_id) {
            abort(403, 'No tienes un rol con permisos para productos.');
        }

        $allowed = DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $user->role_id)
            ->where('permissions.module_name', 'products')
            ->whereIn('permissions.action_name', ['manage', 'view'])
            ->exists();

        abort_unless($allowed, 403, 'No tienes permiso para administrar productos.');
    }

    private function authenticatedUser(Request $request): object
    {
        $user = $request->user();

        abort_unless($user && $user->id && $user->company_id, 403, 'No hay usuario autenticado o empresa asociada.');

        return $user;
    }

    private function writeAudit(Request $request, string $action, int $recordId, ?array $oldValues, ?array $newValues): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()?->id,
            'table_name' => 'products',
            'action_type' => $action,
            'record_id' => $recordId,
            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'created_at' => now(),
        ]);
    }

    private function stockSql(): string
    {
        return "COALESCE(SUM(CASE
            WHEN movement_type IN ('INITIAL_INVENTORY', 'ENTRY') THEN COALESCE(quantity, 0)
            WHEN movement_type = 'EXIT' THEN -COALESCE(quantity, 0)
            WHEN movement_type = 'ADJUSTMENT' THEN COALESCE(quantity, 0)
            ELSE 0
        END), 0)";
    }

    private function movementLabel(string $type): string
    {
        return match ($type) {
            'INITIAL_INVENTORY' => 'Inventario inicial',
            'ENTRY' => 'Entrada',
            'EXIT' => 'Salida',
            'TRANSFER' => 'Transferencia',
            'ADJUSTMENT' => 'Ajuste',
            default => 'Movimiento',
        };
    }

    private function taxType(mixed $value): string
    {
        $value = Str::lower(trim((string) $value));

        return in_array($value, ['taxable', 'gravado'], true) ? 'TAXABLE' : 'EXEMPT';
    }

    private function statusValue(mixed $status): int
    {
        return in_array($status, ['Activo', 'activo', 1, '1', true], true) ? 1 : 0;
    }

    private function boolValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private function nullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
