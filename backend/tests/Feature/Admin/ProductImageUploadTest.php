<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Services\Assets\StorageKeyDeriver;
use App\Services\Assets\UploadWidthResolver;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
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
 *  - `is_hover` se replica en las cuatro filas de la subida, con el
 *    mismo `sort_order`, limpia la foto hover previa del producto, y no
 *    convive con `is_primary` en la misma petición (422).
 *  - No-upscale: un original angosto no genera filas de anchos mayores.
 *  - Anchos configurables (`assets.upload.widths`): default intacto,
 *    lista propia respetada, no-upscale con lista propia, y lista
 *    inválida rechazada con error explícito en vez de un 201 mudo.
 *  - `variant_id`: se persiste y vuelve en la respuesta, tiene que ser
 *    una variante DEL producto de la ruta (422 si es de otro), y su
 *    ausencia sigue siendo válida.
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
     * Variante con `finish_slug` real: es lo que el PDP usa para elegir
     * acabado, así que es la que la galería tiene que poder referenciar.
     */
    private function makeVariant(Product $product, string $finish): ProductVariant
    {
        return ProductVariant::factory()->for($product, 'product')->create(attributes: [
            'finish_slug' => $finish,
            'finish_label' => ucfirst($finish),
            'is_active' => true,
        ]);
    }

    /**
     * @return Collection<int, ProductImage>
     */
    private function primaries(): Collection
    {
        return ProductImage::query()
            ->where(column: 'is_primary', operator: '=', value: true)
            ->get();
    }

    /**
     * `variant_id` de cada fila, en el mismo orden que `storedWidths()`.
     *
     * @return array<int, int|null>
     */
    private function storedVariantIds(): array
    {
        return ProductImage::query()
            ->ordered()
            ->get()
            ->map(fn (ProductImage $row): ?int => $row->variant_id === null ? null : (int) $row->variant_id)
            ->all();
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
            expected: UploadWidthResolver::DEFAULT_WIDTHS,
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

    // -----------------------------------------------------------------------
    // Anchos configurables (assets.upload.widths)
    // -----------------------------------------------------------------------

    #[Test]
    public function upload_uses_the_default_widths_when_the_config_key_is_absent(): void
    {
        config()->offsetUnset(UploadWidthResolver::CONFIG_KEY);

        $this->asAdmin();

        $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(width: 1600, height: 1200),
            ])->assertCreated();

        // Sin la clave, el comportamiento es el de antes del slice 1.2.
        $this->assertSame(
            expected: [400, 800, 1200, 1600],
            actual: $this->storedWidths(),
        );
    }

    #[Test]
    public function upload_honours_a_configured_width_list(): void
    {
        config()->set(key: UploadWidthResolver::CONFIG_KEY, value: [300, 900]);

        $this->asAdmin();

        $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(width: 1600, height: 1200),
                'is_primary' => true,
            ])->assertCreated();

        $this->assertSame(expected: [300, 900], actual: $this->storedWidths());

        // La primaria sigue siendo la del ancho MAYOR configurado, no 1600.
        $this->assertSame(expected: 900, actual: (int) $this->primaries()->first()?->width);
    }

    #[Test]
    public function upload_does_not_upscale_with_a_configured_width_list(): void
    {
        config()->set(key: UploadWidthResolver::CONFIG_KEY, value: [600, 1200]);

        $this->asAdmin();

        // 500 px de original: con la lista [600, 1200] no entra ninguno.
        $rejected = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(width: 500, height: 400),
            ]);

        // El mensaje del piso mínimo ya no dice "400": dice el de la lista.
        $rejected->assertStatus(status: 422);
        $this->assertStringContainsString(
            needle: 'tamaño mínimo soportado (600 px)',
            haystack: (string) $rejected->json(key: 'message'),
        );
        $this->assertSame(expected: 0, actual: ProductImage::query()->count());

        // 800 px sí habilita el de 600 pero no el de 1200: no hay upscale.
        $accepted = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(width: 800, height: 600),
            ]);

        $accepted->assertCreated();
        $this->assertSame(expected: [600], actual: $this->storedWidths());
    }

    #[Test]
    public function upload_fails_loudly_when_the_width_list_is_misconfigured(): void
    {
        config()->set(key: UploadWidthResolver::CONFIG_KEY, value: []);

        $this->asAdmin();

        // Sin lista de anchos no hay ni archivos ni filas: un 201 acá
        // sería un "subí la foto" que no subió nada.
        $this->withoutExceptionHandling();

        try {
            $this->withHeaders(headers: ['Accept' => 'application/json'])
                ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                    'image' => $this->fakeImage(width: 1600, height: 1200),
                ]);
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString(
                needle: UploadWidthResolver::CONFIG_KEY,
                haystack: $exception->getMessage(),
            );
            $this->assertSame(expected: 0, actual: ProductImage::query()->count());
            $this->assertSame(expected: [], actual: Storage::disk(name: $this->disk)->allFiles());

            return;
        }

        $this->fail('Una lista de anchos vacía tiene que fallar, no responder 201.');
    }

    #[Test]
    public function upload_fails_loudly_when_the_width_list_is_not_ascending(): void
    {
        config()->set(key: UploadWidthResolver::CONFIG_KEY, value: [1600, 400]);

        $this->asAdmin();
        $this->withoutExceptionHandling();

        $this->expectException(InvalidArgumentException::class);

        $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(width: 1600, height: 1200),
            ]);
    }

    // -----------------------------------------------------------------------
    // is_hover
    // -----------------------------------------------------------------------

    #[Test]
    public function it_replicates_is_hover_across_every_resolution_of_the_upload(): void
    {
        $this->asAdmin();

        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(),
                'is_hover' => true,
                'sort_order' => 7,
            ]);

        $response->assertCreated();

        $rows = ProductImage::query()->get();

        // La marca describe la FOTO, no una fila: si sólo la de 1600
        // quedara marcada, una lectura que no colapse por rol devolvería
        // una sola resolución de las cuatro.
        $this->assertCount(expectedCount: count(UploadWidthResolver::DEFAULT_WIDTHS), haystack: $rows);
        $this->assertSame(
            expected: array_fill(0, count(UploadWidthResolver::DEFAULT_WIDTHS), true),
            actual: $rows->map(static fn (ProductImage $row): bool => (bool) $row->is_hover)->all(),
        );
        $this->assertSame(
            expected: array_fill(0, count(UploadWidthResolver::DEFAULT_WIDTHS), 7),
            actual: $rows->map(static fn (ProductImage $row): int => (int) $row->sort_order)->all(),
        );
    }

    #[Test]
    public function it_unmarks_the_previous_hover_photo_when_the_upload_becomes_the_new_hover(): void
    {
        $previous = $this->makeHoverPhoto(rows: 2);

        $this->asAdmin();

        $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(),
                'is_hover' => true,
            ])->assertCreated();

        // clearHoverFlag() desmarca TODAS las filas de la foto previa.
        foreach ($previous as $row) {
            $this->assertFalse($row->fresh()->is_hover);
        }
    }

    #[Test]
    public function it_rejects_an_upload_that_marks_the_photo_as_primary_and_hover(): void
    {
        $this->asAdmin();

        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(),
                'is_primary' => true,
                'is_hover' => true,
            ]);

        // 422 antes de convertir y sin filas: una foto no ocupa las dos
        // ranuras de la card a la vez.
        $response->assertStatus(status: 422);
        $this->assertSame(expected: 0, actual: ProductImage::query()->count());
    }

    /**
     * Foto hover previa de N filas, como las deja el upload (mismo
     * `sort_order`, marca replicada).
     *
     * @return list<ProductImage>
     */
    private function makeHoverPhoto(int $rows = 2): array
    {
        $created = [];

        foreach (range(1, $rows) as $position) {
            $created[] = ProductImage::query()->create(attributes: [
                'product_id' => $this->product->id,
                'image_url' => 'https://cdn.example.com/prev-'.$position.'.webp',
                'is_hover' => true,
                'sort_order' => 0,
                'width' => 400 * $position,
            ]);
        }

        return $created;
    }

    // -----------------------------------------------------------------------
    // variant_id
    // -----------------------------------------------------------------------

    #[Test]
    public function upload_persists_a_variant_owned_by_the_product(): void
    {
        $variant = $this->makeVariant(product: $this->product, finish: 'heritage-cherry');

        $this->asAdmin();

        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(width: 1600, height: 1200),
                'variant_id' => $variant->id,
            ]);

        $response->assertCreated();

        // Las 4 resoluciones son LA MISMA foto: todas llevan la variante.
        $this->assertSame(
            expected: array_fill(0, count(UploadWidthResolver::DEFAULT_WIDTHS), (int) $variant->id),
            actual: $this->storedVariantIds(),
        );
        $this->assertSame(
            expected: $variant->id,
            actual: $response->json(key: 'data.0.variant_id'),
        );
    }

    #[Test]
    public function upload_without_variant_id_keeps_working_and_stores_null(): void
    {
        $this->asAdmin();

        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(width: 1600, height: 1200),
            ]);

        $response->assertCreated();
        $this->assertSame(expected: [null, null, null, null], actual: $this->storedVariantIds());
        $this->assertNull($response->json(key: 'data.0.variant_id'));
    }

    #[Test]
    public function upload_rejects_a_variant_that_belongs_to_another_product(): void
    {
        $foreign = $this->makeVariant(product: $this->otherProduct, finish: 'sunburst');

        $this->asAdmin();

        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(width: 1600, height: 1200),
                'variant_id' => $foreign->id,
            ]);

        // 422 y no un attach silencioso: la galería del PDP filtra por
        // variant_id y una foto con la variante ajena miente en las dos
        // direcciones.
        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['variant_id']);
        $this->assertStringContainsString(
            needle: 'no pertenece a este producto',
            haystack: (string) $response->json(key: 'errors.variant_id.0'),
        );
        $this->assertSame(expected: 0, actual: ProductImage::query()->count());
    }

    #[Test]
    public function upload_rejects_a_nonexistent_variant(): void
    {
        $this->asAdmin();

        $response = $this->withHeaders(headers: ['Accept' => 'application/json'])
            ->post(self::ENDPOINT.$this->product->id.'/images/upload', [
                'image' => $this->fakeImage(width: 1600, height: 1200),
                'variant_id' => 999_999,
            ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['variant_id']);
        $this->assertSame(expected: 0, actual: ProductImage::query()->count());
    }

    #[Test]
    public function the_url_based_endpoint_accepts_a_variant_of_the_product_too(): void
    {
        $variant = $this->makeVariant(product: $this->product, finish: 'heritage-cherry');

        $this->asAdmin();

        $response = $this->postJson(self::ENDPOINT.$this->product->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/cherry.jpg',
            'variant_id' => $variant->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.variant_id', expect: $variant->id);

        $row = ProductImage::query()->firstOrFail();
        $this->assertSame(expected: $variant->id, actual: (int) $row->variant_id);
    }

    #[Test]
    public function the_url_based_endpoint_rejects_a_variant_of_another_product(): void
    {
        $foreign = $this->makeVariant(product: $this->otherProduct, finish: 'sunburst');

        $this->asAdmin();

        $response = $this->postJson(self::ENDPOINT.$this->product->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/x.jpg',
            'variant_id' => $foreign->id,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['variant_id']);
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
