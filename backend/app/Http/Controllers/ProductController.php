<?php

namespace App\Http\Controllers;

use App\Services\CompanySettingsService;
use App\Services\ImageLibraryService;
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

    // Regla de negocio explicita: solo Producto admite una galeria (hasta
    // 5 fotos); el resto de entidades con imagen (categoria, marca, logos
    // de empresa) solo permiten una.
    private const MAX_PRODUCT_IMAGES = 5;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeProducts($request);
        $companyId = (int) $request->user()->company_id;

        $rows = $this->productsQuery($companyId)
            ->orderBy('products.short_name')
            ->orderBy('products.code')
            ->get();

        // Se resuelven las portadas de todos los productos en una sola
        // consulta (en vez de una por producto) para no convertir el
        // listado en un N+1 sobre la tabla "images".
        $productIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $imageUrls = $this->imageLibrary()->primaryUrlsFor('product', $productIds);

        // Solo tiers activos (de tipos de precio tambien activos): esto
        // alimenta el buscador de POS/Ventas, que solo necesita precios
        // realmente aplicables hoy.
        $priceTiers = $this->loadPriceTiers($companyId, $productIds, true);

        $products = $rows
            ->map(fn ($product) => $this->productPayload(
                $product,
                $imageUrls[(int) $product->id] ?? null,
                null,
                $priceTiers[(int) $product->id] ?? [],
            ))
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
                // Por defecto sigue siendo un solo almacen (el de siempre).
                // Si el usuario tiene el permiso 'products'/'administrar'
                // (o su rol es "Administrador") y selecciono varias
                // sucursales, el mismo stock inicial se registra en el
                // almacen de CADA una — el producto queda dado de alta con
                // existencia (y su propio movimiento de kardex) en todas
                // las sucursales elegidas desde el momento de crearlo, no
                // solo en la que se puso en "Almacen inicial".
                $targetWarehouseIds = $this->resolveTargetWarehouseIds($request, $companyId, $user, $validated);

                foreach ($targetWarehouseIds as $targetWarehouseId) {
                    $this->createInventoryMovement(
                        $companyId,
                        $productId,
                        $targetWarehouseId,
                        'INITIAL_INVENTORY',
                        (float) ($validated['initial_stock'] ?? $validated['stock'] ?? 0),
                        (float) $data['cost'],
                        (int) $user->id,
                        $validated,
                    );
                    $this->syncInventoryLot($companyId, $productId, $targetWarehouseId, $validated, (float) $data['cost']);
                }

                // syncInventoryStock() ya recalcula inventory_stock para
                // TODOS los almacenes que tengan movimientos de este
                // producto (agrupa por warehouse_id) — una sola llamada
                // alcanza sin importar cuantos almacenes se hayan tocado
                // arriba.
                $this->syncInventoryStock($companyId, $productId, (float) $data['cost']);
            }

            $this->syncPriceTiers($companyId, $productId, $validated['price_tiers'] ?? []);
            $this->syncKitItems($companyId, $productId, $data['is_kit'] ? ($validated['kit_items'] ?? []) : []);

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
                $this->syncInventoryLot($companyId, $product, $warehouseId, $validated, (float) $data['cost']);
            }

            $this->syncPriceTiers($companyId, $product, $validated['price_tiers'] ?? []);
            $this->syncKitItems($companyId, $product, $data['is_kit'] ? ($validated['kit_items'] ?? []) : []);

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

                // Las imagenes del producto no tienen FK hacia "products"
                // (la tabla "images" es generica/polimorfica), asi que hay
                // que limpiarlas a mano para no dejar BLOBs huerfanos.
                DB::table('images')
                    ->where('imageable_type', 'product')
                    ->where('imageable_id', $product)
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

    public function storeImage(Request $request, int $product): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeProducts($request);
        $companyId = (int) $user->company_id;
        $this->findProductRecord($companyId, $product);

        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'image.required' => 'Selecciona una imagen.',
            'image.mimes' => 'La imagen debe ser JPG, JPEG, PNG o WEBP.',
            'image.max' => 'La imagen no puede superar los 10 MB.',
        ]);

        $image = $this->imageLibrary()->storeMultiple(
            $request->file('image'),
            'product',
            $product,
            $companyId,
            (int) $user->id,
            self::MAX_PRODUCT_IMAGES,
        );

        $this->writeAudit($request, 'INSERT', $product, null, ['image_added' => $image->uuid]);

        return response()->json([
            'message' => 'Imagen agregada correctamente.',
            'image' => $image->toPublicArray(),
            'images' => $this->imageLibrary()->listFor('product', $product),
        ], 201);
    }

    public function destroyImage(Request $request, int $product, int $image): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeProducts($request);
        $companyId = (int) $user->company_id;
        $this->findProductRecord($companyId, $product);

        $this->imageLibrary()->delete('product', $product, $image, $companyId);
        $this->writeAudit($request, 'DELETE', $product, ['image_id' => $image], null);

        return response()->json([
            'message' => 'Imagen eliminada correctamente.',
            'images' => $this->imageLibrary()->listFor('product', $product),
        ]);
    }

    public function setPrimaryImage(Request $request, int $product, int $image): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeProducts($request);
        $companyId = (int) $user->company_id;
        $this->findProductRecord($companyId, $product);

        $this->imageLibrary()->setPrimary('product', $product, $image, $companyId);
        $this->writeAudit($request, 'UPDATE', $product, null, ['primary_image_id' => $image]);

        return response()->json([
            'message' => 'Imagen principal actualizada.',
            'images' => $this->imageLibrary()->listFor('product', $product),
        ]);
    }

    public function movements(Request $request, int $product): JsonResponse
    {
        $this->authorizeProducts($request);
        $companyId = (int) $request->user()->company_id;
        $this->findProductRecord($companyId, $product);

        $movements = DB::table('inventory_movements')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'inventory_movements.warehouse_id')
            ->leftJoin('inventory_lots', 'inventory_lots.id', '=', 'inventory_movements.lot_id')
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
                'inventory_lots.lot_number as lot_number',
                'inventory_lots.expiration_date as expiration_date',
                'inventory_movements.movement_date',
                'inventory_movements.created_at',
                'warehouses.name as warehouse_name',
            ])
            ->map(function ($movement) {
                $quantity = (float) ($movement->quantity ?? 0);
                $type = (string) ($movement->movement_type ?? '');
                $isOutput = in_array($type, ['SALE_EXIT', 'TRANSFER_OUT', 'RETURN_OUT'], true)
                    || ($type === 'ADJUSTMENT' && $quantity < 0);
                $detail = $this->movementLabel($type);

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
                DB::raw('MIN(manufacturing_date) as manufacturing_date'),
                DB::raw('MIN(expiration_date) as expiration_date'),
                DB::raw('MIN(purchase_price) as lot_purchase_price'),
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
                'products.measurement',
                'products.cost',
                'products.sale_price',
                'products.tax_type',
                'products.tax_percentage',
                'products.sale_price_with_tax',
                'products.max_discount_type',
                'products.max_discount_value',
                'products.minimum_stock',
                'products.maximum_stock',
                'products.allow_negative_stock',
                'products.manages_lots',
                'products.manages_expiration',
                'products.expiration_alert_days',
                'products.physical_location',
                'products.notes',
                'products.observations',
                'products.status',
                'products.is_inventory',
                'products.is_service',
                'products.is_kit',
                'products.allow_sale',
                'products.allow_purchase',
                'products.is_favorite',
                'products.has_warranty',
                'products.warranty_days',
                'products.warranty_period_unit',
                'products.warranty_type',
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
                'lots.manufacturing_date',
                'lots.expiration_date',
                'lots.lot_purchase_price',
                DB::raw('COALESCE(stock.current_stock, 0) as current_stock'),
            ]);
    }

    private function findProductPayload(int $companyId, int $productId): array
    {
        $product = $this->productsQuery($companyId)
            ->where('products.id', $productId)
            ->first();

        abort_unless($product, 404, 'Producto no encontrado.');

        // A diferencia del listado, la ficha de un solo producto si incluye
        // la galeria completa (hasta 5 imagenes): la necesita el formulario
        // de edicion para poder administrarlas.
        $images = $this->imageLibrary()->listFor('product', $productId);
        $primaryImage = collect($images)->firstWhere('is_primary', true) ?? ($images[0] ?? null);

        // A diferencia del listado, aqui se incluyen TODOS los tiers (aunque
        // el tipo de precio o la fila esten desactivados) para que el
        // formulario de edicion pueda mostrarlos/corregirlos.
        $priceTiers = $this->loadPriceTiers($companyId, [$productId], false)[$productId] ?? [];

        // Solo en la ficha de un solo producto (no en el listado, para no
        // volverlo un N+1): disponibilidad por sucursal, sumando todos los
        // almacenes de cada una — pedido para poder responder "hay stock de
        // este producto en tal sucursal" sin tener que ir almacen por
        // almacen.
        $stockByBranch = $this->stockByBranch($companyId, $productId);
        $kitItems = (bool) $product->is_kit ? $this->loadKitItems($companyId, $productId) : [];

        return $this->productPayload($product, $primaryImage['url'] ?? null, $images, $priceTiers, $stockByBranch, $kitItems);
    }

    /**
     * Lista de materiales (BOM) de un kit: que productos lo componen y en
     * que cantidad. Solo se carga para la ficha de un solo producto (igual
     * que stockByBranch) — el listado no la necesita.
     *
     * @return array<int, array{id:int, product_id:int, product_name:string, quantity:float}>
     */
    private function loadKitItems(int $companyId, int $kitProductId): array
    {
        return DB::table('product_kit_items')
            ->join('products as components', 'components.id', '=', 'product_kit_items.component_product_id')
            ->where('product_kit_items.company_id', $companyId)
            ->where('product_kit_items.kit_product_id', $kitProductId)
            ->orderBy('components.short_name')
            ->select([
                'product_kit_items.id',
                'product_kit_items.component_product_id as product_id',
                'components.short_name as product_name',
                'product_kit_items.quantity',
            ])
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'product_id' => (int) $row->product_id,
                'product_name' => (string) $row->product_name,
                'quantity' => (float) $row->quantity,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{branch_id:int|null, branch_name:string, quantity:float, available_quantity:float}>
     */
    private function stockByBranch(int $companyId, int $productId): array
    {
        return DB::table('branches')
            ->where('branches.company_id', $companyId)
            ->where('branches.status', 1)
            ->leftJoin('warehouses', 'warehouses.branch_id', '=', 'branches.id')
            ->leftJoin('inventory_stock', function ($join) use ($productId) {
                $join->on('inventory_stock.warehouse_id', '=', 'warehouses.id')
                    ->where('inventory_stock.product_id', '=', $productId);
            })
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('branches.name')
            ->select([
                'branches.id as branch_id',
                'branches.name as branch_name',
                DB::raw('COALESCE(SUM(inventory_stock.quantity), 0) as quantity'),
                DB::raw('COALESCE(SUM(inventory_stock.available_quantity), 0) as available_quantity'),
            ])
            ->get()
            ->map(fn ($row) => [
                'branch_id' => $row->branch_id ? (int) $row->branch_id : null,
                'branch_name' => $row->branch_name,
                'quantity' => (float) $row->quantity,
                'available_quantity' => (float) $row->available_quantity,
            ])
            ->values()
            ->all();
    }

    private function imageLibrary(): ImageLibraryService
    {
        return app(ImageLibraryService::class);
    }

    /**
     * Carga los precios por volumen (product_price_list_items) de varios
     * productos en un solo query, agrupados por product_id — igual que
     * $imageUrls arriba, para no convertir el listado en un N+1. Se hace
     * aparte de productsQuery() (en vez de un leftJoinSub agregado) porque
     * un producto puede tener varias filas de tiers, no un solo valor.
     *
     * @param  int[]  $productIds
     * @return array<int, array<int, array{id:int,price_list_id:int,price_list_name:string,min_quantity:float,price:float,is_active:bool}>>
     */
    private function loadPriceTiers(int $companyId, array $productIds, bool $onlySellable): array
    {
        if (empty($productIds)) {
            return [];
        }

        $query = DB::table('product_price_list_items')
            ->join('price_lists', 'price_lists.id', '=', 'product_price_list_items.price_list_id')
            ->where('price_lists.company_id', $companyId)
            ->whereIn('product_price_list_items.product_id', $productIds)
            ->whereNull('product_price_list_items.variant_id');

        if ($onlySellable) {
            $query->where('product_price_list_items.is_active', true)
                ->where('price_lists.is_active', true);
        }

        return $query
            ->select([
                'product_price_list_items.id',
                'product_price_list_items.product_id',
                'product_price_list_items.price_list_id',
                'price_lists.name as price_list_name',
                'product_price_list_items.min_quantity',
                'product_price_list_items.price',
                'product_price_list_items.is_active',
            ])
            ->orderByDesc('product_price_list_items.min_quantity')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'price_list_id' => (int) $row->price_list_id,
                'price_list_name' => (string) $row->price_list_name,
                'min_quantity' => (float) $row->min_quantity,
                'price' => (float) $row->price,
                'is_active' => (bool) $row->is_active,
            ])->values()->all())
            ->all();
    }

    private function productPayload(object $product, ?string $imageUrl = null, ?array $images = null, ?array $priceTiers = null, ?array $stockByBranch = null, ?array $kitItems = null): array
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
            'max_discount_type' => $product->max_discount_type ?: null,
            'max_discount_value' => $product->max_discount_value !== null ? (float) $product->max_discount_value : null,
            'cost' => (float) ($product->cost ?? 0),
            'stock' => (float) ($product->current_stock ?? 0),
            'initial_stock' => (float) ($product->current_stock ?? 0),
            'minimum_stock' => (float) ($product->minimum_stock ?? 0),
            'maximum_stock' => $product->maximum_stock !== null ? (float) $product->maximum_stock : null,
            'allow_negative_stock' => (bool) $product->allow_negative_stock,
            'manages_lots' => (bool) $product->manages_lots,
            'manages_expiration' => (bool) $product->manages_expiration,
            'expiration_alert_days' => $product->expiration_alert_days !== null ? (int) $product->expiration_alert_days : null,
            'lot_number' => $product->lot_number,
            'manufacturing_date' => $product->manufacturing_date,
            'expiration_date' => $product->expiration_date,
            'lot_purchase_price' => $product->lot_purchase_price !== null ? (float) $product->lot_purchase_price : null,
            'warehouse_id' => $product->warehouse_id ? (int) $product->warehouse_id : null,
            'warehouse' => $product->warehouse_name,
            'branch_id' => $product->branch_id ? (int) $product->branch_id : null,
            'branch' => $product->branch_name,
            'status' => $status,
            'status_label' => $status === 1 ? 'Activo' : 'Inactivo',
            'description' => $product->description,
            'model' => $product->model,
            'measurement' => $product->measurement,
            'physical_location' => $product->physical_location,
            'is_inventory' => (bool) $product->is_inventory,
            'is_service' => (bool) $product->is_service,
            'is_kit' => (bool) $product->is_kit,
            'allow_sale' => (bool) $product->allow_sale,
            'allow_purchase' => (bool) $product->allow_purchase,
            'is_favorite' => (bool) $product->is_favorite,
            'notes' => $product->notes,
            'observations' => $product->observations,
            'has_warranty' => (bool) ($product->has_warranty ?? false),
            'warranty_period_unit' => $product->warranty_period_unit ?: 'DAYS',
            'warranty_duration' => $this->warrantyDaysToDuration(
                $product->warranty_days !== null ? (int) $product->warranty_days : null,
                $product->warranty_period_unit ?: 'DAYS',
            ),
            'warranty_type' => $product->warranty_type,
            'image_url' => $imageUrl,
            'images' => $images ?? [],
            'price_tiers' => $priceTiers ?? [],
            'stock_by_branch' => $stockByBranch ?? [],
            'kit_items' => $kitItems ?? [],
        ];
    }

    private function validatedProduct(Request $request, ?int $productId = null): array
    {
        $companyId = (int) $request->user()->company_id;
        $taxType = $this->taxType($request->input('tax_type', 'EXEMPT'));
        $managesLots = $this->boolValue($request->input('manages_lots', false));

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
            // Solo lo usa resolveTargetWarehouseIds() (dar de alta el
            // producto en varias sucursales a la vez) y solo si quien
            // manda la peticion tiene el permiso 'products'/'administrar'
            // — un usuario sin ese permiso puede mandarlo, pero se ignora.
            'warehouse_ids' => ['sometimes', 'array'],
            'warehouse_ids.*' => [
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
            // Limite de descuento propio del producto: si se manda un tipo,
            // el valor se vuelve obligatorio (y viceversa). Ver
            // SalesService::resolveMaxLineDiscount(), que lo usa al facturar
            // en vez del tope general de Configuracion General.
            'max_discount_type' => ['nullable', Rule::in(['PERCENTAGE', 'FIXED'])],
            'max_discount_value' => [
                'nullable',
                'numeric',
                'min:0',
                Rule::requiredIf(fn () => filled($request->input('max_discount_type'))),
            ],
            'initial_stock' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'inventory_status' => ['required', Rule::in(array_keys(self::INVENTORY_STATUSES))],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
            'maximum_stock' => ['nullable', 'numeric', 'min:0'],
            'allow_negative_stock' => ['sometimes', 'boolean'],
            // "Maneja lotes" es el unico interruptor: al activarlo se
            // despliegan todos los campos de trazabilidad de lote (numero,
            // fechas, costo de compra, alerta de vencimiento). No hay un
            // toggle aparte para "maneja vencimiento" — la fecha de
            // vencimiento es uno mas de esos campos (opcional, porque no
            // todo lo que se lotea caduca), pensado para que farmacias y
            // empresas de alimentos puedan registrar todo el detalle de un
            // lote desde el alta del producto. No se pide un proveedor
            // propio del lote: ya existe "Proveedor principal" del
            // producto, y duplicarlo aqui solo confundiria cual es la
            // fuente de verdad.
            'manages_lots' => ['sometimes', 'boolean'],
            'lot_number' => [
                Rule::requiredIf($managesLots),
                'nullable',
                'string',
                'max:100',
            ],
            'manufacturing_date' => ['nullable', 'date'],
            'expiration_date' => ['nullable', 'date'],
            'lot_purchase_price' => ['nullable', 'numeric', 'min:0'],
            'expiration_alert_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'status' => ['required', Rule::in(['Activo', 'Inactivo', 'activo', 'inactivo', 1, 0, '1', '0', true, false])],
            'description' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:120'],
            // Formato totalmente libre a proposito (sin regex/patron): un
            // producto se puede medir "30x34x12", otro como "Altura: 30cm,
            // Largo: 66cm, Grosor: 5cm" — no tiene sentido forzar un solo
            // formato para todo el catalogo.
            'measurement' => ['nullable', 'string', 'max:255'],
            'physical_location' => ['nullable', 'string', 'max:255'],
            'is_inventory' => ['sometimes', 'boolean'],
            'is_kit' => ['sometimes', 'boolean'],
            'allow_purchase' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            // "Combo/Kit": un producto sin existencia propia, armado con
            // otros productos de la empresa en cantidades fijas. Al
            // venderlo se descuenta el inventario de cada componente (ver
            // SalesService::moveInventoryForKitComponents()), nunca el del
            // kit — por eso is_kit fuerza is_inventory=false (mismo
            // criterio que ya existia para "servicio", ver productData()).
            'kit_items' => ['sometimes', 'array'],
            'kit_items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'kit_items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            // Garantia del producto: se captura como "duracion + unidad"
            // (mas natural para el usuario que forzarlo a pensar en dias),
            // y se convierte a dias en productData() para guardarla en la
            // columna existente products.warranty_days.
            'has_warranty' => ['sometimes', 'boolean'],
            'warranty_duration' => [
                Rule::requiredIf(fn () => $this->boolValue($request->input('has_warranty', false))),
                'nullable',
                'numeric',
                'min:1',
            ],
            'warranty_period_unit' => ['nullable', Rule::in(['DAYS', 'MONTHS', 'YEARS'])],
            'warranty_type' => ['nullable', 'string', 'max:120'],
            'price_tiers' => ['sometimes', 'array'],
            // El usuario nombra el tipo de precio libremente (no elige de
            // un catalogo cerrado) — ver resolvePriceListId(), que busca un
            // "price_lists" existente con ese nombre (sin importar
            // mayusculas/acentos) para esta empresa, o crea uno nuevo al
            // vuelo si no existe. Asi la proxima vez que este mismo usuario
            // (o cualquier otro de la empresa) vaya a escribir un tipo de
            // precio, ya le aparece como sugerencia (GET /settings/price-types
            // sigue devolviendo el catalogo completo, ahora alimentado por
            // esto ademas de por Configuracion > Tipos de precio).
            'price_tiers.*.price_list_name' => ['required', 'string', 'max:100'],
            'price_tiers.*.min_quantity' => ['required', 'numeric', 'min:1'],
            'price_tiers.*.price' => ['required', 'numeric', 'min:0'],
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
            'max_discount_value.required' => 'Ingresa el valor del descuento maximo permitido.',
            'minimum_stock.required' => 'Ingresa la alerta de existencia minima.',
            'lot_number.required' => 'Ingresa el numero de lote.',
            'warranty_duration.required' => 'Ingresa la duracion de la garantia.',
            'price_tiers.*.price_list_name.required' => 'Escribe el nombre del tipo de precio.',
            'price_tiers.*.min_quantity.required' => 'Ingresa la cantidad minima para ese precio.',
            'price_tiers.*.min_quantity.min' => 'La cantidad minima debe ser al menos 1.',
            'price_tiers.*.price.required' => 'Ingresa el precio para esa cantidad.',
            'kit_items.*.product_id.required' => 'Selecciona el producto que compone el kit.',
            'kit_items.*.product_id.exists' => 'Uno de los productos del kit no existe.',
            'kit_items.*.quantity.required' => 'Ingresa la cantidad de ese producto en el kit.',
            'kit_items.*.quantity.min' => 'La cantidad debe ser mayor que cero.',
        ]);

        $this->validateProductRules($companyId, $validated, $productId);

        return $validated;
    }

    private function validateProductRules(int $companyId, array $validated, ?int $productId = null): void
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

        // "Permitir venta" ya no es un campo que el usuario apague: todo
        // producto creado aqui queda habilitado para venta, asi que su
        // precio siempre debe ser mayor que cero.
        if ((float) $validated['sale_price'] <= 0) {
            abort(422, 'El precio de venta debe ser mayor que cero.');
        }

        if (($validated['max_discount_type'] ?? null) === 'PERCENTAGE' && (float) ($validated['max_discount_value'] ?? 0) > 100) {
            abort(422, 'El descuento maximo por porcentaje no puede ser mayor a 100.');
        }

        // "Servicio" ya no es un toggle propio: se deduce de is_inventory
        // (ver productData()) — no tiene sentido pedirle al usuario que
        // marque dos campos mutuamente excluyentes por separado.
        if ($this->boolValue($validated['is_kit'] ?? false)) {
            // El default es false (no true) aqui a proposito: is_kit ya
            // fuerza is_inventory=false en productData() sin que el
            // usuario tenga que mandarlo, asi que omitir el campo no debe
            // contar como un conflicto — solo mandarlo explicitamente en
            // true junto con is_kit=true es lo que se rechaza.
            if ($this->boolValue($validated['is_inventory'] ?? false)) {
                abort(422, 'Un combo/kit no puede marcarse tambien como producto inventariable: su existencia depende de la de sus componentes.');
            }

            $kitItems = $validated['kit_items'] ?? [];
            abort_if(empty($kitItems), 422, 'Selecciona al menos un producto para armar el combo/kit.');

            $componentIds = collect($kitItems)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->unique();

            abort_if($productId && $componentIds->contains($productId), 422, 'Un combo/kit no puede tener a si mismo como componente.');

            $hasKitComponent = DB::table('products')
                ->where('company_id', $companyId)
                ->whereIn('id', $componentIds)
                ->where('is_kit', true)
                ->exists();

            abort_if($hasKitComponent, 422, 'Un combo/kit no puede tener a otro combo/kit como componente.');
        }

        $manufacturingDate = $this->nullableText($validated['manufacturing_date'] ?? null);
        $expirationDate = $this->nullableText($validated['expiration_date'] ?? null);

        if ($manufacturingDate && $expirationDate && $expirationDate < $manufacturingDate) {
            abort(422, 'La fecha de vencimiento del lote no puede ser anterior a la fecha de fabricacion.');
        }

        $warehouse = DB::table('warehouses')
            ->where('company_id', $companyId)
            ->where('id', (int) $validated['warehouse_id'])
            ->first();

        if ($warehouse && isset($validated['branch_id']) && (int) $validated['branch_id'] > 0 && $warehouse->branch_id && (int) $warehouse->branch_id !== (int) $validated['branch_id']) {
            abort(422, 'El almacen no pertenece a la sucursal seleccionada.');
        }

        // Cada tipo de precio aporta una sola cantidad minima + precio por
        // producto (asi es como lo consume SalesService al vender), asi que
        // no tiene sentido repetir el mismo tipo en dos filas del mismo
        // producto. Se compara por nombre normalizado (sin mayusculas ni
        // espacios de sobra) porque ahora el usuario lo escribe libre —
        // "Mayorista" y "mayorista " deben contar como el mismo tipo.
        $priceListNames = collect($validated['price_tiers'] ?? [])
            ->pluck('price_list_name')
            ->map(fn ($name) => Str::lower(trim((string) $name)));
        if ($priceListNames->duplicates()->isNotEmpty()) {
            abort(422, 'No puedes repetir el mismo tipo de precio en varias filas de precios por volumen.');
        }
    }

    private function productData(array $validated, int $companyId, int $userId, bool $withCreatedAt = true): array
    {
        $salePrice = (float) $validated['sale_price'];
        $taxType = $this->taxType($validated['tax_type']);
        $taxPercentage = $taxType === 'TAXABLE' ? (float) ($validated['tax_percentage'] ?? 0) : 0;
        // La naturaleza del producto es un solo interruptor, no varios
        // campos independientes que el usuario tenga que mantener
        // consistentes a mano: "Combo/Kit" gana si esta activo (no tiene
        // existencia propia); si no, es "Producto inventariable" o, si ese
        // esta apagado, un servicio (is_service ya no se pide aparte).
        $isKit = $this->boolValue($validated['is_kit'] ?? false);
        $isInventory = $isKit ? false : $this->boolValue($validated['is_inventory'] ?? true);
        $isService = ! $isKit && ! $isInventory;
        $hasWarranty = $this->boolValue($validated['has_warranty'] ?? false);
        $warrantyPeriodUnit = $hasWarranty ? ($validated['warranty_period_unit'] ?? 'DAYS') : 'DAYS';
        $warrantyDays = $hasWarranty
            ? $this->warrantyDurationToDays((float) ($validated['warranty_duration'] ?? 0), $warrantyPeriodUnit)
            : null;
        // "Maneja vencimiento" ya no es un interruptor que llene el usuario:
        // se deduce solo de si el lote trae fecha de vencimiento cargada.
        $managesLots = $this->boolValue($validated['manages_lots'] ?? false);
        $managesExpiration = $managesLots && $this->nullableText($validated['expiration_date'] ?? null) !== null;

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
            'measurement' => $this->nullableText($validated['measurement'] ?? null),
            'cost' => (float) $validated['cost'],
            'sale_price' => $salePrice,
            'tax_type' => $taxType,
            'tax_percentage' => $taxPercentage,
            'max_discount_type' => $this->nullableText($validated['max_discount_type'] ?? null),
            'max_discount_value' => $this->nullableNumber($validated['max_discount_value'] ?? null),
            'sale_price_with_tax' => round($salePrice + ($salePrice * $taxPercentage / 100), 4),
            'minimum_stock' => (float) $validated['minimum_stock'],
            'maximum_stock' => $this->nullableNumber($validated['maximum_stock'] ?? null),
            'allow_negative_stock' => $this->boolValue($validated['allow_negative_stock'] ?? false),
            'manages_lots' => $managesLots,
            'manages_expiration' => $managesExpiration,
            'expiration_alert_days' => ($managesLots && filled($validated['expiration_alert_days'] ?? null))
                ? (int) $validated['expiration_alert_days']
                : null,
            'physical_location' => $this->nullableText($validated['physical_location'] ?? null),
            'status' => $this->statusValue($validated['status']),
            'is_inventory' => $isInventory,
            'is_service' => $isService,
            'is_kit' => $isKit,
            // Ya no son campos que el usuario active/desactive desde el
            // formulario: todo producto creado aqui queda habilitado para
            // venta, y "favorito" no se usa en ninguna otra parte del
            // sistema (no hay filtro de favoritos en POS/reportes), asi que
            // se elimino del formulario en vez de dejar un campo muerto.
            'allow_sale' => true,
            'allow_purchase' => $this->boolValue($validated['allow_purchase'] ?? true),
            'is_favorite' => false,
            'notes' => $this->nullableText($validated['notes'] ?? null),
            'observations' => $this->nullableText($validated['observations'] ?? null),
            'has_warranty' => $hasWarranty,
            'warranty_days' => $warrantyDays,
            'warranty_period_unit' => $warrantyPeriodUnit,
            'warranty_type' => $hasWarranty ? $this->nullableText($validated['warranty_type'] ?? null) : null,
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
            'reference_table' => 'products',
            'reference_id' => $productId,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'movement_date' => now(),
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }

    /**
     * Reemplaza por completo los precios por volumen de un producto: borra
     * todas sus filas y reinserta las que vengan en el payload. Mismo
     * patron "borrar todo y reinsertar" que syncInventoryStock()/
     * syncInventoryLot() de aqui abajo. No depende de is_inventory (a
     * diferencia de esas dos) porque los precios por volumen aplican
     * tambien a servicios/productos no inventariables.
     *
     * @param  array<int, array{price_list_name:string, min_quantity:float|string, price:float|string}>  $tiers
     */
    private function syncPriceTiers(int $companyId, int $productId, array $tiers): void
    {
        DB::table('product_price_list_items')->where('product_id', $productId)->delete();

        foreach ($tiers as $tier) {
            DB::table('product_price_list_items')->insert([
                'product_id' => $productId,
                'variant_id' => null,
                'price_list_id' => $this->resolvePriceListId($companyId, (string) $tier['price_list_name']),
                'min_quantity' => (float) $tier['min_quantity'],
                'price' => (float) $tier['price'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reemplaza por completo la lista de materiales (BOM) de un combo/kit:
     * mismo patron "borrar todo y reinsertar" que syncPriceTiers(). Se
     * llama con un arreglo vacio cuando el producto no es (o dejo de ser)
     * un kit, asi que editar un producto para quitarle "Combo/Kit" tambien
     * limpia sus componentes.
     *
     * @param  array<int, array{product_id:int|string, quantity:float|string}>  $kitItems
     */
    private function syncKitItems(int $companyId, int $kitProductId, array $kitItems): void
    {
        DB::table('product_kit_items')->where('kit_product_id', $kitProductId)->delete();

        foreach ($kitItems as $item) {
            DB::table('product_kit_items')->insert([
                'company_id' => $companyId,
                'kit_product_id' => $kitProductId,
                'component_product_id' => (int) $item['product_id'],
                'quantity' => (float) $item['quantity'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * El usuario escribe el tipo de precio libremente (no elige de un
     * catalogo cerrado): se busca un "price_lists" existente con ese
     * nombre para la empresa (sin importar mayusculas ni espacios de
     * sobra) y se reutiliza; si no existe, se crea uno nuevo al vuelo. Asi
     * la proxima vez que cualquier usuario de la empresa vaya a escribir
     * un tipo de precio, ese nombre ya aparece como sugerencia (mismo
     * catalogo que administra Configuracion > Tipos de precio).
     */
    private function resolvePriceListId(int $companyId, string $name): int
    {
        $trimmedName = trim($name);

        $existing = DB::table('price_lists')
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($trimmedName)])
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        return DB::table('price_lists')->insertGetId([
            'company_id' => $companyId,
            'code' => $this->generatePriceListCode($companyId, $trimmedName),
            'name' => $trimmedName,
            'is_default' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Mismo criterio de PriceListController::generateCode(): slug legible
    // del nombre, con sufijo numerico si ya existe.
    private function generatePriceListCode(int $companyId, string $name): string
    {
        $base = (string) Str::of($name)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', '-')->trim('-');
        $base = $base === '' ? 'TIPO' : Str::limit($base, 20, '');

        $candidate = $base;
        $suffix = 2;

        while (DB::table('price_lists')->where('company_id', $companyId)->where('code', $candidate)->exists()) {
            $candidate = Str::limit($base, 20 - strlen("-{$suffix}"), '').'-'.$suffix;
            $suffix++;
        }

        return $candidate;
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

    /**
     * Ficha de trazabilidad del lote (numero, fechas de fabricacion/
     * vencimiento y costo de compra propio de ese lote) pensada para
     * farmacias y empresas de alimentos. Sigue siendo un modelo
     * simplificado de "un lote por producto+almacen" (se actualiza en el
     * mismo registro, no se apilan lotes historicos) — mismo criterio
     * "borrar/sincronizar en el lugar" que syncInventoryStock(). No se pide
     * un proveedor propio del lote (se reutiliza el proveedor principal del
     * producto): tener dos campos de proveedor en el mismo formulario solo
     * genera confusion sobre cual es el real.
     */
    private function syncInventoryLot(int $companyId, int $productId, int $warehouseId, array $validated, float $cost): void
    {
        if (! $this->boolValue($validated['manages_lots'] ?? false)) {
            return;
        }

        $quantity = (float) ($validated['initial_stock'] ?? $validated['stock'] ?? 0);

        $data = [
            'company_id' => $companyId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'lot_number' => $this->nullableText($validated['lot_number'] ?? null),
            'manufacturing_date' => $this->nullableText($validated['manufacturing_date'] ?? null),
            'expiration_date' => $this->nullableText($validated['expiration_date'] ?? null),
            'initial_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'cost' => $cost,
            'purchase_price' => $this->nullableNumber($validated['lot_purchase_price'] ?? null),
            'supplier_id' => isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            'updated_at' => now(),
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

        $data['status'] = 'ACTIVE';
        $data['created_at'] = now();

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

    /**
     * Uno o varios almacenes destino para el stock inicial de un producto
     * nuevo. Sin el permiso extra (o si no mando "warehouse_ids"), es el
     * comportamiento de siempre: un solo almacen ("Almacen inicial" +
     * "Sucursal" del formulario). Con el permiso y una seleccion multiple,
     * se valida que cada almacen pertenezca a la empresa y se devuelven
     * todos — el llamador crea un movimiento de inventario por cada uno.
     */
    private function resolveTargetWarehouseIds(Request $request, int $companyId, object $user, array $validated): array
    {
        $requestedIds = $validated['warehouse_ids'] ?? [];

        if (! empty($requestedIds) && $this->userCanManageMultipleBranches($request)) {
            $validIds = DB::table('warehouses')
                ->where('company_id', $companyId)
                ->whereIn('id', collect($requestedIds)->map(fn ($id) => (int) $id)->unique()->values())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            abort_if(empty($validIds), 422, 'Ninguno de los almacenes seleccionados es valido.');

            return $validIds;
        }

        return [
            $this->warehouseId(
                $companyId,
                (int) $validated['warehouse_id'],
                (int) ($validated['branch_id'] ?? ($user->branch_id ?: 0)),
            ),
        ];
    }

    /**
     * Gate para "dar de alta un producto en varias sucursales a la vez":
     * el rol "Administrador" siempre lo tiene (mismo criterio que
     * GeneralSettingsController::authorizeGeneralSettings()); cualquier
     * otro rol necesita el permiso explicito 'products'/'administrar'
     * (ya sembrado por SecuritySeeder junto al resto de acciones del
     * modulo — solo falta que un admin se lo asigne a un rol desde
     * Administracion > Roles).
     */
    private function userCanManageMultipleBranches(Request $request): bool
    {
        $user = $request->user();

        if (! $user || ! $user->role_id) {
            return false;
        }

        $role = DB::table('roles')->where('id', $user->role_id)->first();

        if ($role && strcasecmp((string) $role->name, 'Administrador') === 0) {
            return true;
        }

        return DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $user->role_id)
            ->where('permissions.module_name', 'products')
            ->where('permissions.action_name', 'administrar')
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
        $companyId = (int) ($request->user()?->company_id ?? 0);

        if ($companyId > 0 && ! (bool) app(CompanySettingsService::class)->get($companyId, 'system', 'audit_enabled', true)) {
            return;
        }

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
            WHEN movement_type IN ('INITIAL_INVENTORY', 'PURCHASE_ENTRY', 'TRANSFER_IN', 'RETURN_IN') THEN COALESCE(quantity, 0)
            WHEN movement_type IN ('SALE_EXIT', 'TRANSFER_OUT', 'RETURN_OUT') THEN -COALESCE(quantity, 0)
            WHEN movement_type = 'ADJUSTMENT' THEN COALESCE(quantity, 0)
            ELSE 0
        END), 0)";
    }

    private function movementLabel(string $type): string
    {
        return match ($type) {
            'INITIAL_INVENTORY' => 'Inventario inicial',
            'PURCHASE_ENTRY' => 'Entrada por compra',
            'SALE_EXIT' => 'Salida por venta',
            'TRANSFER_IN' => 'Entrada por transferencia',
            'TRANSFER_OUT' => 'Salida por transferencia',
            'ADJUSTMENT' => 'Ajuste',
            'RETURN_IN' => 'Entrada por devolucion',
            'RETURN_OUT' => 'Salida por devolucion',
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

    /**
     * Convierte la duracion de garantia capturada por el usuario (en dias,
     * meses o anos) al total en dias que realmente guarda
     * products.warranty_days — asi SalesService solo necesita sumar dias a
     * la fecha de venta sin preocuparse por la unidad original.
     */
    private function warrantyDurationToDays(float $duration, string $unit): int
    {
        return (int) round($duration * match ($unit) {
            'YEARS' => 365,
            'MONTHS' => 30,
            default => 1,
        });
    }

    /**
     * Inverso de warrantyDurationToDays(): reconstruye la duracion "en la
     * unidad en la que se capturo" para que el formulario de edicion
     * muestre "6" + "Meses" en vez de forzar al usuario a leer "180 dias".
     */
    private function warrantyDaysToDuration(?int $days, string $unit): ?float
    {
        if ($days === null) {
            return null;
        }

        return match ($unit) {
            'YEARS' => round($days / 365, 2),
            'MONTHS' => round($days / 30, 2),
            default => (float) $days,
        };
    }
}
