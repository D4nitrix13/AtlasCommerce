<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Services\Assets\ProductImageUploader;
use App\Services\Assets\StorageKeyDeriver;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del upload de imágenes de producto por binario.
 *
 * Cubre:
 *  - Auth (401, 403 sin `catalog.manage`, 404 fuera del tenant).
 *  - Happy path: 4 anchos, 4 binarios WebP reales en el disk, 4 filas, 201.
 *  - Los bytes guardados SON WebP y respetan el aspect ratio del original.
 *  - La `storage_key` sale del `StorageKeyDeriver` y es servible por el
 *    `AssetController` (mismo criterio que usa `validate()`).
 *  - `is_primary` sólo queda en la fila del ancho mayor.
 *  - No-upscale: un original angosto no genera filas de anchos mayores.
 *  - MIME fuera de la allow-list (PDF renombrado a .jpg) → 422.
 *  - Archivo sobre `assets.upload.max_kilobytes` → 422.
 *  - Archivo vacío → 422.
 *  - Contenido que no es una imagen → 422.
 *  - Archivo ausente → 422.
 *  - `image_url` en este endpoint → 422 (fuentes mutuamente excluyentes).
 *  - El endpoint legacy con `image_url` sigue intacto.
 */
#[Group('admin')]
#[Group('catalog')]
final class ProductImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'http://panda-estampados.com/api/v1/admin/products/';

    private User $admin;

    private User $staff;

    private Store $store;

    private Store $otherStore;

    private Product $product;

    private Product $otherProduct;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $staffRole = Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail();

        $currency = Currency::factory()->primary()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        // El conversor escribe en `config('assets.disk')`; el fake aísla la
        // suite del FTP real y del `local` de desarrollo.
        $this->disk = (string) config(key: 'assets.disk');
        Storage::fake(disk: $this->disk);

        $this->store = $this->makeStore(slugPrefix: 'panda', domain: 'panda-estampados.com', currency: $currency);
        $this->otherStore = $this->makeStore(slugPrefix: 'kitsune', domain: 'kitsune.com', currency: $currency);

        $this->admin = $this->makeUser(email: 'admin@panda-estampados.com', role: $adminRole, store: $this->store);
        $this->staff = $this->makeUser(email: 'staff@panda-estampados.com', role: $staffRole, store: $this->store);

        $this->product = $this->makeProduct(store: $this->store, category: $category, brand: $brand);
        $this->otherProduct = $this->makeProduct(store: $this->otherStore, category: $category, brand: $brand);
    }

    private function makeStore(string $slugPrefix, string $domain, Currency $currency): Store
    {
        $store = Store::factory()->for($currency, 'currency')->create([
            'slug' => $slugPrefix.'-'.Str::uuid()->toString(),
        ]);

        StoreDomain::query()->create(attributes: [
            'store_id' => $store->id,
            'domain' => $domain,
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        return $store;
    }

    private function makeUser(string $email, Role $role, Store $store): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'is_active' => true,
            'role_id' => $role->id,
        ]);
        $user->stores()->syncWithoutDetaching(ids: [$store->id]);

        return $user;
    }

    private function makeProduct(Store $store, Category $category, Brand $brand): Product
    {
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'currency_id' => Currency::factory()->primary()->create()->id,
            'is_active' => true,
            'published_at' => now(),
        ]);

        ProductStore::query()->create(attributes: [
            'product_id' => $product->id,
            'store_id' => $store->id,
            'is_visible' => true,
        ]);

        return $product;
    }

    private function asAdmin(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);
    }

    /**
     * JPEG sintético del ancho pedido.
     *
     * Requiere `ext-gd`: la construye el propio `UploadedFile::fake()`.
     * Reservada para los tests que ejercitan la CONVERSIÓN.
     */
    private function fakeImage(int $width = 1600, int $height = 1200): UploadedFile
    {
        return UploadedFile::fake()->image(name: 'guitarra.jpg', width: $width, height: $height);
    }

    /**
     * Archivo que pasa la validación sin necesidad de GD.
     *
     * Para los tests que ejercitan auth, tenancy o límites de tamaño: ninguno
     * de esos casos necesita una imagen decodificable, y atarlos a
     * `ext-gd` los haría fallar en runtimes sin la extensión por una razón
     * que no es la que están probando. El MIME va explícito porque
     * `UploadedFile::fake()` lo deduce del nombre del archivo.
     */
    private function fakeAllowedFile(int $kilobytes = 64): UploadedFile
    {
        return UploadedFile::fake()->create(
            name: 'guitarra.jpg',
            kilobytes: $kilobytes,
            mimeType: 'image/jpeg',
        );
    }

    /**
     * @return array<int, int> Anchos de las filas, ordenadas.
     */
    private function storedWidths(): array
    {
        return ProductImage::query()
            ->ordered()
            ->get()
            ->map(fn (ProductImage $row): int => (int) $row->width)
            ->all();
    }

    #[Test]
    public function upload_requires_authentication(): void
    {
        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeAllowedFile(),
            ]);

        $response->assertStatus(status: 401);
    }

    #[Test]
    public function upload_denies_a_user_without_catalog_manage_permission(): void
    {
        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeAllowedFile(),
            ]);

        $response->assertStatus(status: 403);
    }

    #[Test]
    public function upload_stores_one_webp_per_width_and_returns_201(): void
    {
        $this->asAdmin();

        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(width: 1600, height: 1200),
                'alt_text' => 'SG Standard - vista front',
                'is_primary' => true,
            ]);

        $response->assertCreated();

        $this->assertSame(
            expected: ProductImageUploader::WIDTHS,
            actual: $this->storedWidths(),
        );

        $deriver = new StorageKeyDeriver;
        $expectedPrefix = 'tenants/'.$this->store->slug.'/products/'.$this->product->slug.'/';

        foreach (ProductImage::query()->get() as $row) {
            // Misma convención de claves que el seeder y el auditor: si
            // el `AssetController` no acepta el path, el storefront 403.
            $this->assertTrue($deriver->validate(path: $row->image_url));
            $this->assertStringStartsWith(prefix: $expectedPrefix, string: $row->image_url);
            $this->assertSame(expected: 'webp', actual: pathinfo($row->image_url, PATHINFO_EXTENSION));
            $this->assertTrue(Storage::disk(name: $this->disk)->exists(path: $row->image_url));
        }

        $this->assertCount(expectedCount: 4, haystack: $response->json(key: 'data'));
        $this->assertSame(expected: 'SG Standard - vista front', actual: $response->json(key: 'data.0.alt_text'));
    }

    #[Test]
    public function upload_writes_real_webp_binaries_that_keep_the_source_aspect_ratio(): void
    {
        $this->asAdmin();

        $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(width: 1600, height: 1200),
            ])->assertCreated();

        foreach (ProductImage::query()->get() as $row) {
            $bytes = Storage::disk(name: $this->disk)->get(path: $row->image_url);

            // La firma RIFF/WEBP prueba que lo guardado es una recodificación
            // y no el JPEG original renombrado.
            $this->assertSame(expected: 'RIFF', actual: substr($bytes, 0, 4));
            $this->assertSame(expected: 'WEBP', actual: substr(string: $bytes, offset: 8, length: 4));

            $dimensions = getimagesizefromstring(string: $bytes);
            $this->assertIsArray($dimensions);
            $this->assertSame(expected: 'image/webp', actual: $dimensions['mime']);
            $this->assertSame(expected: $row->width, actual: (int) $dimensions[0]);
            // 1600x1200 → 4:3, conservado en los cuatro anchos.
            $this->assertEqualsWithDelta(expected: 4 / 3, actual: $dimensions[0] / $dimensions[1], delta: 0.02);
        }
    }

    #[Test]
    public function upload_marks_is_primary_only_on_the_widest_row(): void
    {
        $this->asAdmin();

        $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(),
                'is_primary' => true,
            ])->assertCreated();

        $primaries = ProductImage::query()
            ->where(column: 'is_primary', operator: '=', value: true)
            ->get();

        // Una sola primaria por producto — la de 1600, como el seeder.
        $this->assertCount(expectedCount: 1, haystack: $primaries);
        $this->assertSame(expected: 1600, actual: (int) $primaries->first()->width);
    }

    #[Test]
    public function upload_does_not_upscale_a_source_narrower_than_the_smallest_width(): void
    {
        $this->asAdmin();

        // 500 px de ancho sólo habilita el WebP de 400.
        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(width: 500, height: 400),
            ]);

        $response->assertCreated();
        $this->assertSame(expected: [400], actual: $this->storedWidths());
        $this->assertCount(expectedCount: 1, haystack: $response->json(key: 'data'));
    }

    #[Test]
    public function upload_rejects_a_source_narrower_than_the_smallest_width(): void
    {
        $this->asAdmin();

        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(width: 320, height: 240),
            ]);

        $response->assertStatus(status: 422);
        $this->assertSame(expected: 0, actual: ProductImage::query()->count());
    }

    #[Test]
    public function upload_rejects_a_mime_type_outside_the_allow_list(): void
    {
        $this->asAdmin();

        // Un PDF con extensión .jpg. El MIME se declara explícitamente
        // porque `UploadedFile::fake()` lo deduce del NOMBRE del archivo —
        // el double de test no mira el contenido, así que sin esto el caso
        // probaría el nombre y no la allow-list.
        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => UploadedFile::fake()->create(
                    name: 'catalogo.jpg',
                    kilobytes: 10,
                    mimeType: 'application/pdf',
                ),
            ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['image']);
        $this->assertStringContainsString(
            needle: 'El formato de la imagen no está permitido',
            haystack: (string) $response->json(key: 'errors.image.0'),
        );
    }

    #[Test]
    public function upload_rejects_a_file_over_the_size_limit(): void
    {
        $this->asAdmin();
        config()->set(key: 'assets.upload.max_kilobytes', value: 10);

        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeAllowedFile(),
            ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['image']);
        $this->assertStringContainsString(
            needle: 'La imagen no puede superar los 10 KB',
            haystack: (string) $response->json(key: 'errors.image.0'),
        );
    }

    #[Test]
    public function upload_rejects_an_empty_file(): void
    {
        $this->asAdmin();

        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => UploadedFile::fake()->create(name: 'vacia.png', kilobytes: 0),
            ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['image']);
        $this->assertStringContainsString(
            needle: 'La imagen está vacía',
            haystack: (string) $response->json(key: 'errors.image.0'),
        );
    }

    #[Test]
    public function upload_rejects_a_file_whose_mime_is_not_an_image(): void
    {
        $this->asAdmin();

        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                // >1 KB a propósito: por debajo del piso de `min:1` el request responde
                // "la imagen está vacía", que es el mensaje correcto para un
                // archivo de 20 bytes — acá se quiere probar el rechazo por
                // MIME, así que el contenido tiene que pasar el piso.
                'image' => UploadedFile::fake()->createWithContent(
                    name: 'roto.png',
                    content: str_repeat('esto no es una imagen', 200),
                )->mimeType(mimeType: 'text/plain'),
            ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['image']);
        $this->assertStringContainsString(
            needle: 'El formato de la imagen no está permitido',
            haystack: (string) $response->json(key: 'errors.image.0'),
        );
    }

    #[Test]
    public function upload_rejects_a_request_without_a_file(): void
    {
        $this->asAdmin();

        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', []);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['image']);
        $this->assertStringContainsString(
            needle: 'Tenés que adjuntar una imagen',
            haystack: (string) $response->json(key: 'errors.image.0'),
        );
    }

    #[Test]
    public function upload_rejects_image_url_because_the_two_sources_are_mutually_exclusive(): void
    {
        $this->asAdmin();

        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image_url' => 'https://cdn.example.com/externa.jpg',
            ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['image']);
        $this->assertSame(expected: 0, actual: ProductImage::query()->count());
    }

    #[Test]
    public function upload_blocks_a_product_outside_the_tenant(): void
    {
        $this->asAdmin();

        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->otherProduct->id.'/images/upload', [
                'image' => $this->fakeAllowedFile(),
            ]);

        $response->assertStatus(status: 404);
        $this->assertSame(expected: 0, actual: ProductImage::query()->count());
    }

    #[Test]
    public function the_url_based_store_endpoint_is_preserved(): void
    {
        $this->asAdmin();

        $response = $this->postJson(self::ENDPOINT.$this->product->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/legado.jpg',
            'alt_text' => 'Legado',
        ]);

        $response->assertCreated();

        $row = ProductImage::query()->firstOrFail();

        // El endpoint por URL sigue guardando la referencia tal cual,
        // sin pasar por el conversor y sin escribir en el disk.
        $this->assertSame(expected: 'https://cdn.example.com/legado.jpg', actual: $row->image_url);
        $this->assertNull($row->width);
        $this->assertSame(expected: [], actual: Storage::disk(name: $this->disk)->allFiles());
    }
}
