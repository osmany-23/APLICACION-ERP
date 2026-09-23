<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_can_be_created_with_generated_code_and_metadata(): void
    {
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'USD',
            'name' => 'Dólar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_base' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empresa Test',
            'currency_id' => $currencyId,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'company_id' => $companyId,
            'uuid' => (string) Str::uuid(),
            'username' => 'usuario-test',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Usuario Test',
            'email' => 'test@example.com',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_api_tokens')->insert([
            'user_id' => $userId,
            'name' => 'Test token',
            'token_hash' => hash('sha256', 'test-token'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/catalogs/categories', [
                'name' => 'Electrónica',
                'description' => 'Categoría de prueba',
                'level' => 1,
                'is_active' => false,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('item.name', 'Electrónica');
        $response->assertJsonPath('item.description', 'Categoría de prueba');
        // Sin imagen subida todavia, image_url se resuelve vacio (ya no es
        // un campo de texto libre: ahora solo se llena subiendo un archivo
        // real via POST /catalogs/categories/{id}/image, ver el test de abajo).
        $response->assertJsonPath('item.image_url', '');
        $response->assertJsonPath('item.level', 1);
        $this->assertNotEmpty($response->json('item.code'));
        $this->assertMatchesRegularExpression('/^CAT\d{6}$/', $response->json('item.code'));

        $this->assertDatabaseHas('categories', [
            'company_id' => $companyId,
            'name' => 'Electrónica',
            'description' => 'Categoría de prueba',
            'level' => 1,
            'is_active' => 0,
        ]);
    }

    public function test_category_image_upload_converts_to_webp_and_can_be_removed(): void
    {
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'USD',
            'name' => 'Dólar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_base' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empresa Test',
            'currency_id' => $currencyId,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'company_id' => $companyId,
            'uuid' => (string) Str::uuid(),
            'username' => 'usuario-test-2',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Usuario Test',
            'email' => 'test2@example.com',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_api_tokens')->insert([
            'user_id' => $userId,
            'name' => 'Test token',
            'token_hash' => hash('sha256', 'test-token-2'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = DB::table('categories')->insertGetId([
            'company_id' => $companyId,
            'code' => 'CAT000001',
            'name' => 'Herramientas',
            'level' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auth = ['Authorization' => 'Bearer test-token-2'];

        // Imagen mas grande que el maximo (2000x2000): debe quedar
        // redimensionada a 1600x1600 y convertida a webp.
        $image = imagecreatetruecolor(2000, 2000);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 50, 50));
        ob_start();
        imagepng($image);
        $pngBinary = ob_get_clean();
        imagedestroy($image);

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('foto.png', $pngBinary);

        $uploadResponse = $this->withHeaders($auth)
            ->post("/api/catalogs/categories/{$categoryId}/image", ['image' => $file]);

        $uploadResponse->assertCreated();
        $imageUrl = $uploadResponse->json('item.image_url');
        $this->assertNotEmpty($imageUrl);

        $imageRow = DB::table('images')
            ->where('imageable_type', 'category')
            ->where('imageable_id', $categoryId)
            ->first();

        $this->assertNotNull($imageRow);
        $this->assertSame('image/webp', $imageRow->mime_type);
        $this->assertLessThanOrEqual(1600, $imageRow->width);
        $this->assertLessThanOrEqual(1600, $imageRow->height);
        $this->assertLessThan(strlen($pngBinary), $imageRow->size_bytes, 'El binario optimizado deberia pesar menos que el PNG original.');

        // La imagen se sirve por su propia ruta publica, sin auth.
        $streamResponse = $this->get("/api/images/{$imageRow->uuid}");
        $streamResponse->assertOk();
        $streamResponse->assertHeader('Content-Type', 'image/webp');

        // Subir una nueva imagen reemplaza la anterior (una sola imagen por categoria).
        $file2 = \Illuminate\Http\UploadedFile::fake()->createWithContent('foto2.png', $pngBinary);
        $this->withHeaders($auth)->post("/api/catalogs/categories/{$categoryId}/image", ['image' => $file2]);
        $this->assertSame(1, DB::table('images')->where('imageable_type', 'category')->where('imageable_id', $categoryId)->count());

        $deleteResponse = $this->withHeaders($auth)->delete("/api/catalogs/categories/{$categoryId}/image");
        $deleteResponse->assertOk();
        $deleteResponse->assertJsonPath('item.image_url', '');
        $this->assertDatabaseCount('images', 0);
    }

    public function test_category_image_rejects_non_image_file(): void
    {
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'USD',
            'name' => 'Dólar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_base' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empresa Test',
            'currency_id' => $currencyId,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'company_id' => $companyId,
            'uuid' => (string) Str::uuid(),
            'username' => 'usuario-test-3',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Usuario Test',
            'email' => 'test3@example.com',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_api_tokens')->insert([
            'user_id' => $userId,
            'name' => 'Test token',
            'token_hash' => hash('sha256', 'test-token-3'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = DB::table('categories')->insertGetId([
            'company_id' => $companyId,
            'code' => 'CAT000002',
            'name' => 'Plomeria',
            'level' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Un archivo de texto disfrazado con extension .png: la validacion
        // debe rechazarlo por su contenido real, no confiar en la extension.
        $fakeFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('no-es-imagen.png', 'esto no es una imagen, es texto plano');

        $response = $this->withHeaders(['Authorization' => 'Bearer test-token-3', 'Accept' => 'application/json'])
            ->post("/api/catalogs/categories/{$categoryId}/image", ['image' => $fakeFile]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('images', 0);
    }
}
