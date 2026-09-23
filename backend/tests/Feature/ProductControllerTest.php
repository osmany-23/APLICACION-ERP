<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $warehouseId;

    private int $categoryId;

    private int $brandId;

    private int $unitId;

    private int $supplierId;

    /**
     * Regresion del bug confirmado: ProductController::createInventoryMovement()
     * y movements() referenciaban columnas inexistentes en inventory_movements
     * (inventory_status, lot_number, expiration_date) y valores de enum
     * ('ENTRY'/'EXIT') que no existen, lo cual hacia fallar con SQL error
     * cualquier alta de producto con stock inicial.
     */
    private function authenticateUser(): string
    {
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'USD', 'name' => 'Dolar', 'symbol' => '$', 'decimal_places' => 2,
            'is_base' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => 'Empresa Test', 'currency_id' => $currencyId,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId, 'name' => 'Tester', 'description' => 'Rol de prueba',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->insertGetId([
            'module_name' => 'products', 'action_name' => 'manage', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('role_permissions')->insert([
            'role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'company_id' => $this->companyId, 'role_id' => $roleId, 'uuid' => (string) Str::uuid(),
            'username' => 'tester', 'password_hash' => bcrypt('password'), 'full_name' => 'Tester',
            'email' => 'tester@example.com', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('user_api_tokens')->insert([
            'user_id' => $userId, 'name' => 'Test token', 'token_hash' => hash('sha256', 'test-token'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'BOD-01', 'name' => 'Bodega Central',
            'type' => 'PRINCIPAL', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->categoryId = DB::table('categories')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'CAT001', 'name' => 'General',
            'level' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->brandId = DB::table('brands')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'BR001', 'name' => 'Generica',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->unitId = DB::table('units')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'UND', 'name' => 'Unidad', 'short_name' => 'UND',
            'category' => 'UNIDAD', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->supplierId = DB::table('suppliers')->insertGetId([
            'company_id' => $this->companyId, 'code' => 'PRV-000001', 'name' => 'Proveedor Test',
            'currency_id' => $currencyId, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return 'test-token';
    }

    public function test_creating_product_with_initial_stock_registers_inventory_movement(): void
    {
        $token = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Producto de Prueba',
                'code' => 'PROD-001',
                'supplier_id' => $this->supplierId,
                'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId,
                'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId,
                'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1,
                'sale_price' => 50,
                'cost' => 30,
                'tax_type' => 'EXEMPT',
                'initial_stock' => 20,
                'inventory_status' => 'RECEIVED',
                'minimum_stock' => 5,
                'status' => 'Activo',
            ]);

        $response->assertCreated();
        $productId = $response->json('product.id');

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $productId,
            'movement_type' => 'INITIAL_INVENTORY',
            'quantity' => 20,
        ]);
        $this->assertDatabaseHas('inventory_stock', [
            'product_id' => $productId,
            'warehouse_id' => $this->warehouseId,
            'quantity' => 20,
        ]);

        $movements = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/products/{$productId}/movements");

        $movements->assertOk();
        $movements->assertJsonCount(1, 'data');
        $movements->assertJsonPath('data.0.type', 'INITIAL_INVENTORY');
        $movements->assertJsonPath('data.0.input', 20);
    }

    private function createProduct(string $token, string $code): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Producto '.$code,
                'code' => $code,
                'supplier_id' => $this->supplierId,
                'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId,
                'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId,
                'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1,
                'sale_price' => 50,
                'cost' => 30,
                'tax_type' => 'EXEMPT',
                'initial_stock' => 5,
                'inventory_status' => 'RECEIVED',
                'minimum_stock' => 1,
                'status' => 'Activo',
            ]);

        $response->assertCreated();

        return (int) $response->json('product.id');
    }

    private function fakeImageFile(string $name = 'foto.png'): \Illuminate\Http\UploadedFile
    {
        $image = imagecreatetruecolor(2000, 1200);
        imagefill($image, 0, 0, imagecolorallocate($image, 10, 120, 200));
        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        return \Illuminate\Http\UploadedFile::fake()->createWithContent($name, $binary);
    }

    public function test_product_image_upload_resizes_and_converts_to_webp(): void
    {
        $token = $this->authenticateUser();
        $productId = $this->createProduct($token, 'PROD-IMG-001');

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])
            ->post("/api/products/{$productId}/images", ['image' => $this->fakeImageFile()]);

        $response->assertCreated();
        $response->assertJsonPath('image.is_primary', true);

        $image = DB::table('images')
            ->where('imageable_type', 'product')
            ->where('imageable_id', $productId)
            ->first();

        $this->assertNotNull($image);
        $this->assertSame('image/webp', $image->mime_type);
        // Original es 2000x1200: debe quedar redimensionada a 1600x960 (misma proporcion).
        $this->assertSame(1600, (int) $image->width);
        $this->assertSame(960, (int) $image->height);
    }

    public function test_product_images_are_capped_at_five(): void
    {
        $token = $this->authenticateUser();
        $productId = $this->createProduct($token, 'PROD-IMG-002');

        for ($i = 0; $i < 5; $i++) {
            $response = $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])
                ->post("/api/products/{$productId}/images", ['image' => $this->fakeImageFile("foto{$i}.png")]);
            $response->assertCreated();
        }

        $this->assertSame(5, DB::table('images')->where('imageable_type', 'product')->where('imageable_id', $productId)->count());

        $sixth = $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])
            ->post("/api/products/{$productId}/images", ['image' => $this->fakeImageFile('foto6.png')]);

        $sixth->assertStatus(422);
        $this->assertSame(5, DB::table('images')->where('imageable_type', 'product')->where('imageable_id', $productId)->count());
    }

    public function test_deleting_primary_product_image_promotes_the_next_one(): void
    {
        $token = $this->authenticateUser();
        $productId = $this->createProduct($token, 'PROD-IMG-003');
        $auth = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        $first = $this->withHeaders($auth)->post("/api/products/{$productId}/images", ['image' => $this->fakeImageFile('a.png')]);
        $second = $this->withHeaders($auth)->post("/api/products/{$productId}/images", ['image' => $this->fakeImageFile('b.png')]);

        $firstId = $first->json('image.id');
        $secondId = $second->json('image.id');

        $this->assertTrue($first->json('image.is_primary'));
        $this->assertFalse($second->json('image.is_primary'));

        $delete = $this->withHeaders($auth)->delete("/api/products/{$productId}/images/{$firstId}");
        $delete->assertOk();

        $this->assertDatabaseHas('images', ['id' => $secondId, 'is_primary' => 1]);
    }

    public function test_set_primary_product_image_endpoint(): void
    {
        $token = $this->authenticateUser();
        $productId = $this->createProduct($token, 'PROD-IMG-004');
        $auth = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        $first = $this->withHeaders($auth)->post("/api/products/{$productId}/images", ['image' => $this->fakeImageFile('a.png')]);
        $second = $this->withHeaders($auth)->post("/api/products/{$productId}/images", ['image' => $this->fakeImageFile('b.png')]);
        $secondId = $second->json('image.id');

        $setPrimary = $this->withHeaders($auth)->post("/api/products/{$productId}/images/{$secondId}/primary");
        $setPrimary->assertOk();

        $this->assertDatabaseHas('images', ['id' => $secondId, 'is_primary' => 1]);
        $this->assertDatabaseHas('images', ['id' => $first->json('image.id'), 'is_primary' => 0]);
    }

    public function test_product_image_upload_rejects_file_over_ten_megabytes(): void
    {
        $token = $this->authenticateUser();
        $productId = $this->createProduct($token, 'PROD-IMG-005');

        $oversized = \Illuminate\Http\UploadedFile::fake()->create('grande.jpg', 10241, 'image/jpeg');

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])
            ->post("/api/products/{$productId}/images", ['image' => $oversized]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('images', 0);
    }

    private function createPriceList(string $code = 'MAYORISTA', string $name = 'Mayorista'): int
    {
        return DB::table('price_lists')->insertGetId([
            'company_id' => $this->companyId, 'code' => $code, 'name' => $name,
            'is_default' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_creating_product_with_price_tiers_reuses_an_existing_price_list_by_name(): void
    {
        $token = $this->authenticateUser();
        $priceListId = $this->createPriceList('MAYORISTA', 'Mayorista');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Producto Mayoreo',
                'code' => 'PROD-TIER-001',
                'supplier_id' => $this->supplierId,
                'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId,
                'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId,
                'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1,
                'sale_price' => 25,
                'cost' => 15,
                'tax_type' => 'EXEMPT',
                'initial_stock' => 50,
                'inventory_status' => 'RECEIVED',
                'minimum_stock' => 5,
                'status' => 'Activo',
                // Mismo nombre que el price_list ya existente, pero con
                // mayusculas/espacios distintos — debe resolverse al mismo
                // registro, no crear uno duplicado.
                'price_tiers' => [
                    ['price_list_name' => '  mayorista  ', 'min_quantity' => 10, 'price' => 20],
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('product.price_tiers.0.price_list_id', $priceListId);
        $response->assertJsonPath('product.price_tiers.0.price_list_name', 'Mayorista');
        // json_encode() de PHP no preserva el ".0" de floats enteros por
        // defecto (JSON_PRESERVE_ZERO_FRACTION no esta activado en las
        // respuestas de este proyecto), asi que 10.0/20.0 llegan como int
        // 10/20 al decodificar — mismo criterio que ya usan otras
        // assertJsonPath de este suite (ej. "item.shipping" en SaleControllerTest).
        $response->assertJsonPath('product.price_tiers.0.min_quantity', 10);
        $response->assertJsonPath('product.price_tiers.0.price', 20);

        $this->assertDatabaseHas('product_price_list_items', [
            'product_id' => $response->json('product.id'),
            'price_list_id' => $priceListId,
            'min_quantity' => 10,
            'price' => 20,
        ]);
        $this->assertDatabaseCount('price_lists', 1);
    }

    public function test_creating_product_with_a_brand_new_price_tier_name_creates_the_price_list(): void
    {
        $token = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Producto Mayoreo', 'code' => 'PROD-TIER-NEW',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 25, 'cost' => 15, 'tax_type' => 'EXEMPT',
                'initial_stock' => 50, 'inventory_status' => 'RECEIVED', 'minimum_stock' => 5, 'status' => 'Activo',
                'price_tiers' => [['price_list_name' => 'Distribuidor VIP', 'min_quantity' => 10, 'price' => 20]],
            ]);

        $response->assertCreated();
        $newPriceListId = $response->json('product.price_tiers.0.price_list_id');

        $this->assertDatabaseHas('price_lists', [
            'id' => $newPriceListId,
            'company_id' => $this->companyId,
            'name' => 'Distribuidor VIP',
        ]);

        // La proxima vez que este usuario (o cualquiera de la empresa) vaya
        // a escribir un tipo de precio, "Distribuidor VIP" ya aparece como
        // sugerencia — mismo catalogo que consume el <datalist> del
        // formulario.
        $priceTypes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/settings/price-types');
        $priceTypes->assertOk();
        $this->assertContains('Distribuidor VIP', collect($priceTypes->json('data'))->pluck('name')->all());
    }

    public function test_updating_a_products_price_tiers_replaces_the_previous_set(): void
    {
        $token = $this->authenticateUser();
        $priceListB = $this->createPriceList('TIPO-B', 'Tipo B');

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Producto Mayoreo', 'code' => 'PROD-TIER-002',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 25, 'cost' => 15, 'tax_type' => 'EXEMPT',
                'initial_stock' => 50, 'inventory_status' => 'RECEIVED', 'minimum_stock' => 5, 'status' => 'Activo',
                'price_tiers' => [['price_list_name' => 'Tipo A', 'min_quantity' => 10, 'price' => 20]],
            ]);
        $create->assertCreated();
        $productId = $create->json('product.id');
        $priceListA = DB::table('price_lists')->where('company_id', $this->companyId)->where('name', 'Tipo A')->value('id');

        $update = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/products/{$productId}", [
                'name' => 'Producto Mayoreo', 'code' => 'PROD-TIER-002',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 25, 'cost' => 15, 'tax_type' => 'EXEMPT',
                'initial_stock' => 50, 'inventory_status' => 'RECEIVED', 'minimum_stock' => 5, 'status' => 'Activo',
                'price_tiers' => [['price_list_name' => 'Tipo B', 'min_quantity' => 20, 'price' => 18]],
            ]);
        $update->assertOk();

        $this->assertDatabaseCount('product_price_list_items', 1);
        $this->assertDatabaseHas('product_price_list_items', [
            'product_id' => $productId, 'price_list_id' => $priceListB, 'min_quantity' => 20, 'price' => 18,
        ]);
        $this->assertDatabaseMissing('product_price_list_items', ['price_list_id' => $priceListA]);
    }

    public function test_price_tiers_reject_duplicate_price_type_name_in_the_same_payload(): void
    {
        $token = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Producto Mayoreo', 'code' => 'PROD-TIER-003',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 25, 'cost' => 15, 'tax_type' => 'EXEMPT',
                'initial_stock' => 50, 'inventory_status' => 'RECEIVED', 'minimum_stock' => 5, 'status' => 'Activo',
                // Mismo nombre, distinta mayuscula/espacios — igual debe
                // contar como repetido.
                'price_tiers' => [
                    ['price_list_name' => 'Mayorista', 'min_quantity' => 10, 'price' => 20],
                    ['price_list_name' => ' MAYORISTA ', 'min_quantity' => 50, 'price' => 18],
                ],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('product_price_list_items', 0);
    }

    public function test_price_tier_name_matching_another_companys_price_list_creates_a_scoped_copy(): void
    {
        $token = $this->authenticateUser();

        $otherCompanyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => 'Otra Empresa',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $foreignPriceListId = DB::table('price_lists')->insertGetId([
            'company_id' => $otherCompanyId, 'code' => 'AJENO', 'name' => 'Ajeno',
            'is_default' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Producto Mayoreo', 'code' => 'PROD-TIER-004',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 25, 'cost' => 15, 'tax_type' => 'EXEMPT',
                'initial_stock' => 50, 'inventory_status' => 'RECEIVED', 'minimum_stock' => 5, 'status' => 'Activo',
                'price_tiers' => [['price_list_name' => 'Ajeno', 'min_quantity' => 10, 'price' => 20]],
            ]);

        // Coincidir en nombre con el tipo de precio de otra empresa no
        // reutiliza esa fila ajena: se crea una nueva, propia de esta
        // empresa (mismo aislamiento por company_id que ya tenian los
        // demas catalogos).
        $response->assertCreated();
        $ownPriceListId = $response->json('product.price_tiers.0.price_list_id');
        $this->assertNotEquals($foreignPriceListId, $ownPriceListId);
        $this->assertDatabaseHas('price_lists', [
            'id' => $ownPriceListId, 'company_id' => $this->companyId, 'name' => 'Ajeno',
        ]);
    }

    public function test_measurement_accepts_free_text_without_a_fixed_format(): void
    {
        $token = $this->authenticateUser();
        $freeTextMeasurement = 'Altura: 30cm, Largo: 66cm, Grosor: 5cm';

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Producto con medida',
                'code' => 'PROD-MED-001',
                'supplier_id' => $this->supplierId,
                'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId,
                'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId,
                'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1,
                'sale_price' => 50,
                'cost' => 30,
                'tax_type' => 'EXEMPT',
                'minimum_stock' => 5,
                'inventory_status' => 'RECEIVED',
                'status' => 'Activo',
                'measurement' => $freeTextMeasurement,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('product.measurement', $freeTextMeasurement);
        $this->assertDatabaseHas('products', [
            'id' => $response->json('product.id'),
            'measurement' => $freeTextMeasurement,
        ]);
    }

    public function test_show_endpoint_returns_the_measurement_field(): void
    {
        $token = $this->authenticateUser();

        $productId = DB::table('products')->insertGetId([
            'company_id' => $this->companyId,
            'code' => 'PROD-MED-SHOW',
            'short_name' => 'Producto con medida (show)',
            'type' => 'PRODUCTO',
            'status' => 1,
            'is_inventory' => true,
            'allow_sale' => true,
            'allow_purchase' => true,
            'cost' => 10,
            'sale_price' => 20,
            'tax_type' => 'EXEMPT',
            'measurement' => '30x34x12',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/products/{$productId}");

        $response->assertOk();
        $response->assertJsonPath('product.measurement', '30x34x12');
    }

    public function test_admin_role_can_stock_a_new_product_in_multiple_branches_at_once(): void
    {
        $token = $this->authenticateUser();

        // authenticateUser() deja al usuario con un rol "Tester" (solo
        // 'products'/'manage') — se renombra a "Administrador" para
        // probar el bypass por nombre de rol, igual criterio que
        // GeneralSettingsController::authorizeGeneralSettings().
        DB::table('roles')->where('company_id', $this->companyId)->where('name', 'Tester')->update(['name' => 'Administrador']);

        $branchB = DB::table('branches')->insertGetId([
            'company_id' => $this->companyId, 'uuid' => (string) Str::uuid(), 'code' => 'SUC-B', 'name' => 'Sucursal B',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $warehouseB = DB::table('warehouses')->insertGetId([
            'company_id' => $this->companyId, 'branch_id' => $branchB, 'code' => 'BOD-02', 'name' => 'Bodega Sucursal B',
            'type' => 'PRINCIPAL', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Producto Multi Sucursal', 'code' => 'PROD-MULTI-001',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 50, 'cost' => 30, 'tax_type' => 'EXEMPT',
                'initial_stock' => 20, 'inventory_status' => 'RECEIVED', 'minimum_stock' => 5, 'status' => 'Activo',
                'warehouse_ids' => [$this->warehouseId, $warehouseB],
            ]);

        $response->assertCreated();
        $productId = $response->json('product.id');

        // El mismo stock inicial (20) queda en cada sucursal seleccionada,
        // con su propio movimiento de kardex — no dividido entre ambas.
        foreach ([$this->warehouseId, $warehouseB] as $warehouseId) {
            $this->assertDatabaseHas('inventory_movements', [
                'product_id' => $productId, 'warehouse_id' => $warehouseId,
                'movement_type' => 'INITIAL_INVENTORY', 'quantity' => 20,
            ]);
            $this->assertDatabaseHas('inventory_stock', [
                'product_id' => $productId, 'warehouse_id' => $warehouseId, 'quantity' => 20,
            ]);
        }

        $detail = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/products/{$productId}");
        $detail->assertOk();
        $branchNames = collect($detail->json('product.stock_by_branch'))->pluck('branch_name');
        $this->assertTrue($branchNames->contains('Sucursal B'));
    }

    public function test_warehouse_ids_are_ignored_without_the_multi_branch_permission(): void
    {
        $token = $this->authenticateUser();

        $branchB = DB::table('branches')->insertGetId([
            'company_id' => $this->companyId, 'uuid' => (string) Str::uuid(), 'code' => 'SUC-B', 'name' => 'Sucursal B',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $warehouseB = DB::table('warehouses')->insertGetId([
            'company_id' => $this->companyId, 'branch_id' => $branchB, 'code' => 'BOD-02', 'name' => 'Bodega Sucursal B',
            'type' => 'PRINCIPAL', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Rol "Tester" de siempre: solo 'products'/'manage', sin
        // 'administrar'. Aunque mande warehouse_ids en el payload, el
        // backend debe ignorarlo y usar solo el almacen unico de siempre.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Producto Un Solo Almacen', 'code' => 'PROD-MULTI-002',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 50, 'cost' => 30, 'tax_type' => 'EXEMPT',
                'initial_stock' => 20, 'inventory_status' => 'RECEIVED', 'minimum_stock' => 5, 'status' => 'Activo',
                'warehouse_ids' => [$this->warehouseId, $warehouseB],
            ]);

        $response->assertCreated();
        $productId = $response->json('product.id');

        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $productId, 'warehouse_id' => $this->warehouseId, 'quantity' => 20,
        ]);
        $this->assertDatabaseMissing('inventory_movements', ['product_id' => $productId, 'warehouse_id' => $warehouseB]);
    }

    /**
     * Regresion del bug confirmado en syncInventoryLot(): insertaba en
     * inventory_lots sin company_id (columna NOT NULL) y con una columna
     * "quantity" que no existe en esa tabla (son initial_quantity/
     * remaining_quantity) — cualquier alta con "Maneja lotes" activo fallaba
     * con un SQL error. Tambien cubre los campos nuevos pedidos para
     * farmacias/alimentos: fecha de fabricacion, costo de compra propio del
     * lote y la alerta de vencimiento. No hay un proveedor propio del lote
     * en el formulario (se reutiliza el proveedor principal del producto),
     * pero syncInventoryLot() igual lo snapshotea en inventory_lots.supplier_id.
     */
    public function test_creating_a_product_with_lot_tracking_stores_the_full_lot_record(): void
    {
        $token = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Jarabe para la tos', 'code' => 'PROD-LOT-001',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 50, 'cost' => 30, 'tax_type' => 'EXEMPT',
                'initial_stock' => 40, 'inventory_status' => 'RECEIVED', 'minimum_stock' => 5, 'status' => 'Activo',
                'manages_lots' => true,
                'lot_number' => 'L-2026-045',
                'manufacturing_date' => '2026-01-10',
                'expiration_date' => '2027-01-10',
                'expiration_alert_days' => 30,
                'lot_purchase_price' => 28.5,
            ]);

        $response->assertCreated();
        $productId = $response->json('product.id');

        $this->assertDatabaseHas('inventory_lots', [
            'company_id' => $this->companyId,
            'product_id' => $productId,
            'warehouse_id' => $this->warehouseId,
            'lot_number' => 'L-2026-045',
            'manufacturing_date' => '2026-01-10',
            'expiration_date' => '2027-01-10',
            'initial_quantity' => 40,
            'remaining_quantity' => 40,
            'purchase_price' => 28.5,
            'supplier_id' => $this->supplierId,
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'manages_lots' => 1,
            'manages_expiration' => 1,
            'expiration_alert_days' => 30,
        ]);

        $detail = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/products/{$productId}");
        $detail->assertOk();
        $detail->assertJsonPath('product.lot_number', 'L-2026-045');
        $detail->assertJsonPath('product.manufacturing_date', '2026-01-10');
        $detail->assertJsonPath('product.expiration_date', '2027-01-10');
        $detail->assertJsonPath('product.expiration_alert_days', 30);
        $detail->assertJsonPath('product.lot_purchase_price', 28.5);
    }

    /**
     * Sin fecha de vencimiento cargada, manages_expiration se deduce en
     * false aunque el producto si maneje lotes — ya no hay un toggle aparte
     * que el usuario pueda marcar por separado.
     */
    public function test_a_lot_without_expiration_date_does_not_mark_manages_expiration(): void
    {
        $token = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Tornillos por caja', 'code' => 'PROD-LOT-002',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 50, 'cost' => 30, 'tax_type' => 'EXEMPT',
                'initial_stock' => 100, 'inventory_status' => 'RECEIVED', 'minimum_stock' => 5, 'status' => 'Activo',
                'manages_lots' => true,
                'lot_number' => 'CAJA-08',
            ]);

        $response->assertCreated();
        $productId = $response->json('product.id');

        $this->assertDatabaseHas('products', [
            'id' => $productId, 'manages_lots' => 1, 'manages_expiration' => 0,
        ]);
        $this->assertDatabaseHas('inventory_lots', [
            'product_id' => $productId, 'lot_number' => 'CAJA-08', 'expiration_date' => null,
        ]);
    }

    public function test_expiration_date_before_manufacturing_date_is_rejected(): void
    {
        $token = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Producto con fechas invertidas', 'code' => 'PROD-LOT-003',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 50, 'cost' => 30, 'tax_type' => 'EXEMPT',
                'initial_stock' => 10, 'inventory_status' => 'RECEIVED', 'minimum_stock' => 5, 'status' => 'Activo',
                'manages_lots' => true,
                'lot_number' => 'L-BAD-01',
                'manufacturing_date' => '2026-06-01',
                'expiration_date' => '2026-01-01',
            ]);

        $response->assertStatus(422);
    }

    /**
     * Al editar, syncInventoryLot() actualiza el mismo registro de lote en
     * vez de crear uno nuevo (modelo simplificado "un lote por producto +
     * almacen") — igual que syncInventoryStock() recalcula en el lugar.
     */
    public function test_updating_a_product_replaces_its_lot_record_in_place(): void
    {
        $token = $this->authenticateUser();

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Vitaminas', 'code' => 'PROD-LOT-004',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 50, 'cost' => 30, 'tax_type' => 'EXEMPT',
                'initial_stock' => 15, 'inventory_status' => 'RECEIVED', 'minimum_stock' => 5, 'status' => 'Activo',
                'manages_lots' => true, 'lot_number' => 'L-OLD', 'expiration_date' => '2026-12-01',
            ]);
        $create->assertCreated();
        $productId = $create->json('product.id');

        $update = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/products/{$productId}", [
                'name' => 'Vitaminas', 'code' => 'PROD-LOT-004',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 50, 'cost' => 30, 'tax_type' => 'EXEMPT',
                'initial_stock' => 15, 'inventory_status' => 'RECEIVED', 'minimum_stock' => 5, 'status' => 'Activo',
                'manages_lots' => true, 'lot_number' => 'L-NEW', 'expiration_date' => '2027-06-01',
            ]);
        $update->assertOk();

        $this->assertDatabaseCount('inventory_lots', 1);
        $this->assertDatabaseHas('inventory_lots', [
            'product_id' => $productId, 'lot_number' => 'L-NEW', 'expiration_date' => '2027-06-01',
        ]);
    }

    /**
     * "Combo/Kit": un producto sin existencia propia (is_inventory se
     * fuerza a false), compuesto por otros productos de la empresa en
     * cantidades fijas (product_kit_items). Cubre el alta completa: se
     * guardan los componentes, is_service/is_inventory quedan derivados
     * correctamente, y la ficha del producto devuelve los componentes con
     * su nombre resuelto.
     */
    public function test_creating_a_kit_product_stores_its_components(): void
    {
        $token = $this->authenticateUser();
        $componentA = $this->createProduct($token, 'COMP-A');
        $componentB = $this->createProduct($token, 'COMP-B');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Combo Desayuno', 'code' => 'KIT-001',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 99, 'cost' => 0, 'tax_type' => 'EXEMPT',
                'inventory_status' => 'RECEIVED', 'minimum_stock' => 0, 'status' => 'Activo',
                'is_kit' => true,
                'kit_items' => [
                    ['product_id' => $componentA, 'quantity' => 2],
                    ['product_id' => $componentB, 'quantity' => 1],
                ],
            ]);

        $response->assertCreated();
        $productId = $response->json('product.id');

        $this->assertDatabaseHas('products', [
            'id' => $productId, 'is_kit' => 1, 'is_inventory' => 0, 'is_service' => 0,
        ]);
        $this->assertDatabaseHas('product_kit_items', [
            'kit_product_id' => $productId, 'component_product_id' => $componentA, 'quantity' => 2,
        ]);
        $this->assertDatabaseHas('product_kit_items', [
            'kit_product_id' => $productId, 'component_product_id' => $componentB, 'quantity' => 1,
        ]);

        $detail = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/products/{$productId}");
        $detail->assertOk();
        $kitItems = collect($detail->json('product.kit_items'));
        $this->assertSame(2, $kitItems->count());
        $this->assertTrue($kitItems->contains(fn ($item) => $item['product_id'] === $componentA && $item['product_name'] === 'Producto COMP-A' && (float) $item['quantity'] === 2.0));
    }

    public function test_a_kit_without_components_is_rejected(): void
    {
        $token = $this->authenticateUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Combo vacio', 'code' => 'KIT-002',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 99, 'cost' => 0, 'tax_type' => 'EXEMPT',
                'inventory_status' => 'RECEIVED', 'minimum_stock' => 0, 'status' => 'Activo',
                'is_kit' => true,
            ]);

        $response->assertStatus(422);
    }

    public function test_a_kit_cannot_have_another_kit_as_a_component(): void
    {
        $token = $this->authenticateUser();
        $componentA = $this->createProduct($token, 'COMP-C');

        $innerKit = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Kit interno', 'code' => 'KIT-INNER',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 50, 'cost' => 0, 'tax_type' => 'EXEMPT',
                'inventory_status' => 'RECEIVED', 'minimum_stock' => 0, 'status' => 'Activo',
                'is_kit' => true,
                'kit_items' => [['product_id' => $componentA, 'quantity' => 1]],
            ])->json('product.id');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Kit anidado', 'code' => 'KIT-OUTER',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 50, 'cost' => 0, 'tax_type' => 'EXEMPT',
                'inventory_status' => 'RECEIVED', 'minimum_stock' => 0, 'status' => 'Activo',
                'is_kit' => true,
                'kit_items' => [['product_id' => $innerKit, 'quantity' => 1]],
            ]);

        $response->assertStatus(422);
    }

    public function test_updating_a_kit_to_reference_itself_is_rejected(): void
    {
        $token = $this->authenticateUser();
        $componentA = $this->createProduct($token, 'COMP-D');

        $kitId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Combo autoreferencia', 'code' => 'KIT-003',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 50, 'cost' => 0, 'tax_type' => 'EXEMPT',
                'inventory_status' => 'RECEIVED', 'minimum_stock' => 0, 'status' => 'Activo',
                'is_kit' => true,
                'kit_items' => [['product_id' => $componentA, 'quantity' => 1]],
            ])->json('product.id');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/products/{$kitId}", [
                'name' => 'Combo autoreferencia', 'code' => 'KIT-003',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 50, 'cost' => 0, 'tax_type' => 'EXEMPT',
                'inventory_status' => 'RECEIVED', 'minimum_stock' => 0, 'status' => 'Activo',
                'is_kit' => true,
                'kit_items' => [['product_id' => $kitId, 'quantity' => 1]],
            ]);

        $response->assertStatus(422);
    }

    public function test_a_kit_cannot_also_be_marked_as_inventoriable(): void
    {
        $token = $this->authenticateUser();
        $componentA = $this->createProduct($token, 'COMP-E');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Kit invalido', 'code' => 'KIT-004',
                'supplier_id' => $this->supplierId, 'warehouse_id' => $this->warehouseId,
                'category_id' => $this->categoryId, 'brand_id' => $this->brandId,
                'purchase_unit_id' => $this->unitId, 'sale_unit_id' => $this->unitId,
                'conversion_factor' => 1, 'sale_price' => 50, 'cost' => 0, 'tax_type' => 'EXEMPT',
                'inventory_status' => 'RECEIVED', 'minimum_stock' => 0, 'status' => 'Activo',
                'is_kit' => true,
                'is_inventory' => true,
                'kit_items' => [['product_id' => $componentA, 'quantity' => 1]],
            ]);

        $response->assertStatus(422);
    }
}
