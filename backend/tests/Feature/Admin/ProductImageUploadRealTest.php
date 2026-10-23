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
use App\Services\Assets\UploadWidthResolver;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prueba REAL de punta a punta. NO usa `Storage::fake()` ni
 * `UploadedFile::fake()`: el binario se escribe en el disco de verdad y el
 * archivo subido es un JPEG real de 1600x1200 copiado del catálogo.
 *
 * Sirve para demostrar que la conversión a WebP funciona dentro del
 * container — que es donde vive la API — y no sólo en el host.
 *
 * El segundo caso sube el MISMO archivo real con un `variant_id` válido
 * contra un producto de dos variantes, y comprueba que las 4 filas
 * quedan atadas al acabado elegido: es el camino completo que hoy no
 * podía ejercitarse porque el admin no tenía forma de decir "esta foto
 * es el acabado Cereza".
 */
class ProductImageUploadRealTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Product $product;

    private string $disk;

    private string $scratch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();

        $currency = Currency::factory()->primary()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        // Sin `Storage::fake()`: esto escribe en el disco real configurado.
        $this->disk = (string) config(key: 'assets.disk');
        $this->scratch = storage_path('app/real-upload-verification');
        if (! is_dir($this->scratch)) {
            mkdir($this->scratch, 0777, true);
        }

        $this->store = Store::factory()->for($currency, 'currency')->create([
            'slug' => 'panda-'.Str::uuid()->toString(),
        ]);
        StoreDomain::query()->create(attributes: [
            'store_id' => $this->store->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'admin@panda-estampados.com',
            'is_active' => true,
            'role_id' => $adminRole->id,
        ]);
        $user->stores()->syncWithoutDetaching(ids: [$this->store->id]);

        $this->product = Product::factory()->create([
            'name' => 'Les Paul Standard Iced Tea',
            'slug' => 'les-paul-standard-iced-tea',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);
        ProductStore::query()->create(attributes: [
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'is_visible' => true,
        ]);
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        // Borra SÓLO lo que escribió este test. La versión anterior
        // hacía `delete()` sobre `allFiles()`, que vacía el disco
        // configurado entero: inocuo con ASSETS_DISK=local en
        // desarrollo, pero contra `ftp` —el valor de otro entorno—
        // borraría el catálogo entero desde una suite de tests.
        $storeSlug = $this->store->slug;
        $prefix = "tenants/{$storeSlug}/products/";

        foreach (Storage::disk($this->disk)->allFiles() as $file) {
            if (str_starts_with($file, $prefix)) {
                Storage::disk($this->disk)->delete($file);
            }
        }

        if (is_dir($this->scratch)) {
            foreach ((array) glob($this->scratch.'/*') as $f) {
                @unlink((string) $f);
            }
            @rmdir($this->scratch);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_writes_real_webp_files_to_the_real_disk(): void
    {
        // Un JPEG REAL de 1600x1200 (extraído del catálogo), no un fake.
        $sourcePath = base_path('tests/Fixtures/guitarra-1600x1200.jpg');
        if (! is_file($sourcePath)) {
            $this->markTestSkipped('fixture real ausente');
        }
        $real = new UploadedFile(
            path: $sourcePath,
            originalName: 'la-guitarra-del-admin.jpg',
            mimeType: 'image/jpeg',
            error: null,
            test: true,
        );

        $response = $this->post(
            uri: "/api/v1/admin/products/{$this->product->id}/images/upload",
            data: ['image' => $real, 'alt_text' => 'Guitarra completa', 'is_primary' => true],
        );

        $response->assertCreated();

        $rows = $response->json(key: 'data');
        $this->assertCount(expectedCount: count(UploadWidthResolver::DEFAULT_WIDTHS), haystack: $rows);

        $widths = [];
        foreach ($rows as $row) {
            $key = $row['image_url'];

            // 1) El archivo EXISTE en el disco real.
            $this->assertTrue(
                Storage::disk($this->disk)->exists(path: $key),
                "El binario {$key} no existe en el disk real {$this->disk}.",
            );

            // 2) Los bytes son WebP de verdad (firma RIFF....WEBP).
            $bytes = Storage::disk($this->disk)->get(path: $key);
            $this->assertSame(expected: 'RIFF', actual: substr($bytes, 0, 4));
            $this->assertSame(expected: 'WEBP', actual: substr($bytes, 8, 4));

            // 3) Las dimensiones del header coinciden con el ancho pedido.
            $dimensions = getimagesizefromstring($bytes);
            $this->assertSame(expected: $row['width'], actual: $dimensions[0]);

            $widths[] = $row['width'];
        }

        sort($widths);
        $this->assertSame(expected: UploadWidthResolver::DEFAULT_WIDTHS, actual: $widths);

        // 4) El aspect ratio del ORIGINAL se preserva. Se compara contra
        // el del archivo subido, no contra un valor fijo: si mañana la
        // fixture cambia de proporción el test sigue siendo cierto.
        $sourceSize = getimagesizefromstring((string) file_get_contents($sourcePath));
        $sourceAspect = $sourceSize[0] / $sourceSize[1];
        foreach ($rows as $row) {
            $size = getimagesizefromstring(Storage::disk($this->disk)->get(path: $row['image_url']));
            $this->assertEqualsWithDelta(
                expected: $sourceAspect,
                actual: $size[0] / $size[1],
                delta: 0.01,
            );
        }

        // 5) Solo la fila del ancho mayor queda como primaria.
        $primaries = ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $this->product->id)
            ->where(column: 'is_primary', operator: '=', value: true)
            ->get();
        $this->assertCount(expectedCount: 1, haystack: $primaries);
        $this->assertSame(expected: 1600, actual: (int) $primaries->first()->width);

        fwrite(STDERR, "\n=== EVIDENCIA REAL ===\n");
        fwrite(STDERR, 'disk: '.$this->disk."\n");
        foreach ($rows as $row) {
            $size = getimagesizefromstring(Storage::disk($this->disk)->get(path: $row['image_url']));
            fwrite(STDERR, sprintf(
                "  w=%-5d %-9s %sx%s  %6d bytes  %s\n",
                $row['width'],
                pathinfo($row['image_url'], PATHINFO_EXTENSION),
                $size[0],
                $size[1],
                Storage::disk($this->disk)->size(path: $row['image_url']),
                $row['image_url'],
            ));
        }
        fwrite(STDERR, "=== fin ===\n");
    }

    #[Test]
    public function it_attaches_a_real_upload_to_the_requested_variant(): void
    {
        $sourcePath = base_path('tests/Fixtures/guitarra-1600x1200.jpg');
        if (! is_file($sourcePath)) {
            $this->markTestSkipped('fixture real ausente');
        }

        // Producto de DOS acabados: el caso donde `variant_id` deja de ser
        // decorativo y la galería del PDP puede filtrar de verdad.
        $cherry = ProductVariant::factory()->for($this->product, 'product')->create(attributes: [
            'name' => 'Heritage Cherry',
            'finish_slug' => 'heritage-cherry',
            'finish_label' => 'Heritage Cherry',
            'is_active' => true,
        ]);
        $sunburst = ProductVariant::factory()->for($this->product, 'product')->create(attributes: [
            'name' => 'Sunburst',
            'finish_slug' => 'sunburst',
            'finish_label' => 'Sunburst',
            'is_active' => true,
        ]);

        $real = new UploadedFile(
            path: $sourcePath,
            originalName: 'sg-standard-heritage-cherry.jpg',
            mimeType: 'image/jpeg',
            error: null,
            test: true,
        );

        $response = $this->post(
            uri: "/api/v1/admin/products/{$this->product->id}/images/upload",
            data: [
                'image' => $real,
                'alt_text' => 'SG Standard - Heritage Cherry',
                'is_primary' => true,
                'variant_id' => $cherry->id,
            ],
        );

        $response->assertCreated();

        // Las 4 resoluciones de la MISMA foto quedan en la variante pedida:
        // no se reparte, porque no hay a cuál elegir.
        $stored = ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $this->product->id)
            ->ordered()
            ->get();

        $this->assertCount(expectedCount: count(UploadWidthResolver::DEFAULT_WIDTHS), haystack: $stored);
        $this->assertSame(
            expected: array_fill(0, count(UploadWidthResolver::DEFAULT_WIDTHS), $cherry->id),
            actual: $stored->map(fn (ProductImage $row): ?int => $row->variant_id === null ? null : (int) $row->variant_id)->all(),
        );

        // Y la otra variante NO quedó con nada colgando.
        $this->assertSame(expected: 0, actual: ProductImage::query()
            ->where(column: 'variant_id', operator: '=', value: $sunburst->id)
            ->count());

        // El recurso admin lo expone: el panel tiene que poder leer lo que
        // el panel acaba de escribir.
        $this->assertSame(
            expected: $cherry->id,
            actual: $response->json(key: 'data.0.variant_id'),
        );

        fwrite(STDERR, "\n=== EVIDENCIA REAL (variant_id) ===\n");
        fwrite(STDERR, sprintf(
            "producto %d · variante %d (%s) · la otra variante %d (%s) sin imágenes\n",
            $this->product->id,
            $cherry->id,
            (string) $cherry->finish_slug,
            $sunburst->id,
            (string) $sunburst->finish_slug,
        ));
        foreach ($stored as $row) {
            fwrite(STDERR, sprintf(
                "  variant_id=%-5d w=%-5d %-9s %s\n",
                (int) $row->variant_id,
                (int) $row->width,
                pathinfo((string) $row->image_url, PATHINFO_EXTENSION),
                $row->image_url,
            ));
        }
        fwrite(STDERR, "=== fin ===\n");
    }
}
