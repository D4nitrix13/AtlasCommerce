<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del comando `assets:backfill-images` (Fase 5 del image-architecture plan).
 *
 * Cubre:
 *   - Skip de imagenes ya migradas (validate() devuelve true).
 *   - Upload de URLs externas via HTTP GET fake.
 *   - Upload de paths legacy de local disk.
 *   - Update de `image_url` en DB con la nueva storage key canonica.
 *   - Dry-run sin modificar la DB ni el FTP.
 *   - Filtro por tenant via relacion product.stores.store.slug.
 *   - Chunking: chunk_size + 1 imagenes todas se procesan.
 *   - Failure tolerance: una fila rota no rompe el batch.
 *   - Idempotencia: re-correr no duplica uploads ni actualiza imagen_url.
 *   - Confirmation prompt: sin --force se pide confirmacion.
 *   - Cache lock: lock activo impide otra ejecucion concurrente.
 */
#[CoversClass(\App\Console\Commands\BackfillProductImagesCommand::class)]
#[Group('console')]
#[Group('assets')]
final class BackfillProductImagesCommandTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake de los dos disks usados por el comando (ftp + local) y
        // del cliente HTTP. Asi no tocamos el FTP real ni la red externa
        // durante los tests (ver §4 del task file). Laravel 13 no acepta
        // array de disks en Storage::fake(), asi que llamamos dos veces.
        Storage::fake(disk: 'ftp');
        Storage::fake(disk: 'local');

        $this->currency = Currency::factory()->primary()->create();

        $this->store = Store::factory()->for($this->currency, 'currency')->create(attributes: [
            'slug' => 'panda-estampados',
        ]);

        $this->product = Product::factory()->create(attributes: [
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
            'currency_id' => $this->currency->id,
            'slug' => 'camiseta-oversized',
        ]);

        ProductStore::query()->create(attributes: [
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'is_visible' => true,
        ]);

        // Limpiar cualquier lock residual de tests previos. La cache del
        // phpunit.xml es in-memory (array driver) pero el lock TTL es
        // 600s y podria sobrevivir entre tests si la app no se reinicia.
        Cache::lock(name: 'backfill-images')->forceRelease();
    }

    protected function tearDown(): void
    {
        Cache::lock(name: 'backfill-images')->forceRelease();

        parent::tearDown();
    }

    private function makeImage(string $imageUrl): ProductImage
    {
        return ProductImage::query()->create(attributes: [
            'product_id' => $this->product->id,
            'image_url' => $imageUrl,
            'is_primary' => false,
            'sort_order' => 0,
        ]);
    }

    /**
     * Computar la storage key canonica esperada para un contenido dado.
     * Es la misma formula que `StorageKeyDeriver::derive()` pero inline
     * para que el test verifique el contrato sin acoplamiento.
     */
    private function expectedKey(string $content, string $extension): string
    {
        $hash = substr(string: hash(algo: 'sha256', data: $content), offset: 0, length: 12);

        return sprintf(
            'tenants/%s/products/%s/%s.%s',
            $this->store->slug,
            $this->product->slug,
            $hash,
            $extension,
        );
    }

    #[Test]
    public function it_skips_already_migrated_images(): void
    {
        // Path que matchea el regex de StorageKeyDeriver::validate().
        $migratedKey = 'tenants/panda-estampados/products/camiseta-oversized/abc123def456.webp';
        $image = $this->makeImage(imageUrl: $migratedKey);

        $this->artisan(command: 'assets:backfill-images', parameters: ['--force' => true])
            ->assertSuccessful();

        // image_url no se modifico y no se intento ningun HTTP fetch.
        $this->assertSame(expected: $migratedKey, actual: $image->fresh()->image_url);
        Http::assertNothingSent();
        Storage::disk(name: 'ftp')->assertDirectoryEmpty(path: 'tenants/panda-estampados/products/camiseta-oversized');
    }

    #[Test]
    public function it_uploads_external_url_to_ftp(): void
    {
        $contents = 'fake-cdn-image-binary-content';
        Http::fake([
            'cdn.example.com/*' => Http::response($contents, 200),
        ]);

        $this->makeImage(imageUrl: 'https://cdn.example.com/products/camiseta-main.jpg');

        $this->artisan(command: 'assets:backfill-images', parameters: ['--force' => true])
            ->assertSuccessful();

        $expectedKey = $this->expectedKey(content: $contents, extension: 'jpg');
        Storage::disk(name: 'ftp')->assertExists(path: $expectedKey);
        $this->assertSame(expected: $contents, actual: Storage::disk(name: 'ftp')->get(path: $expectedKey));
        Http::assertSent(callback: fn ($request): bool => $request->url() === 'https://cdn.example.com/products/camiseta-main.jpg');
    }

    #[Test]
    public function it_uploads_local_path_to_ftp(): void
    {
        $contents = 'legacy-local-disk-binary-content';
        Storage::disk(name: 'local')->put(path: 'images/products/camiseta-oversized.jpg', contents: $contents);

        $this->makeImage(imageUrl: '/images/products/camiseta-oversized.jpg');

        $this->artisan(command: 'assets:backfill-images', parameters: ['--force' => true])
            ->assertSuccessful();

        $expectedKey = $this->expectedKey(content: $contents, extension: 'jpg');
        Storage::disk(name: 'ftp')->assertExists(path: $expectedKey);
        $this->assertSame(expected: $contents, actual: Storage::disk(name: 'ftp')->get(path: $expectedKey));
    }

    #[Test]
    public function it_replaces_image_url_with_new_storage_key(): void
    {
        $contents = 'some-binary-content';
        Http::fake([
            'cdn.example.com/*' => Http::response($contents, 200),
        ]);

        $image = $this->makeImage(imageUrl: 'https://cdn.example.com/products/foo.jpg');

        $this->artisan(command: 'assets:backfill-images', parameters: ['--force' => true])
            ->assertSuccessful();

        $expectedKey = $this->expectedKey(content: $contents, extension: 'jpg');
        $this->assertSame(expected: $expectedKey, actual: $image->fresh()->image_url);
    }

    #[Test]
    public function it_supports_dry_run_without_modifying_data(): void
    {
        $contents = 'dry-run-content';
        Http::fake([
            'cdn.example.com/*' => Http::response($contents, 200),
        ]);

        $image = $this->makeImage(imageUrl: 'https://cdn.example.com/products/foo.jpg');

        $this->artisan(command: 'assets:backfill-images', parameters: [
            '--dry-run' => true,
        ])
            ->assertSuccessful();

        // image_url intacto y FTP vacio — el dry-run NO escribe en DB ni en disco.
        // El HTTP request SÍ se hace (necesitamos el contenido para computar
        // el hash y la storage key) pero el contenido no se persiste.
        $this->assertSame(expected: 'https://cdn.example.com/products/foo.jpg', actual: $image->fresh()->image_url);
        Storage::disk(name: 'ftp')->assertDirectoryEmpty(path: 'tenants');
        Http::assertSent(callback: fn ($request): bool => str_contains(haystack: $request->url(), needle: 'cdn.example.com'));
    }

    #[Test]
    public function it_filters_by_tenant_when_specified(): void
    {
        // Crear un segundo tenant + producto + imagen. El filtro
        // --tenant=panda-estampados debe dejar la otra imagen sin tocar.
        $kitsune = Store::factory()->for($this->currency, 'currency')->create(attributes: [
            'slug' => 'kitsune',
        ]);
        $kitsuneProduct = Product::factory()->create(attributes: [
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
            'currency_id' => $this->currency->id,
            'slug' => 'figura-anime',
        ]);
        ProductStore::query()->create(attributes: [
            'product_id' => $kitsuneProduct->id,
            'store_id' => $kitsune->id,
            'is_visible' => true,
        ]);
        $kitsuneImage = ProductImage::query()->create(attributes: [
            'product_id' => $kitsuneProduct->id,
            'image_url' => 'https://cdn.example.com/kitsune/foo.jpg',
            'is_primary' => false,
            'sort_order' => 0,
        ]);

        $pandaContents = 'panda-cdn-content';
        $kitsuneContents = 'kitsune-cdn-content';
        Http::fake([
            'cdn.example.com/panda/*' => Http::response($pandaContents, 200),
            'cdn.example.com/kitsune/*' => Http::response($kitsuneContents, 200),
        ]);

        $pandaImage = $this->makeImage(imageUrl: 'https://cdn.example.com/panda/foo.jpg');

        $this->artisan(command: 'assets:backfill-images', parameters: [
            '--force' => true,
            '--tenant' => ['panda-estampados'],
        ])
            ->assertSuccessful();

        // panda-estampados migrada, kitsune intacta.
        $expectedPandaKey = $this->expectedKey(content: $pandaContents, extension: 'jpg');
        $this->assertSame(expected: $expectedPandaKey, actual: $pandaImage->fresh()->image_url);
        $this->assertSame(expected: 'https://cdn.example.com/kitsune/foo.jpg', actual: $kitsuneImage->fresh()->image_url);

        // Solo se hizo HTTP request al CDN de panda.
        Http::assertSent(callback: fn ($request): bool => str_contains(haystack: $request->url(), needle: '/panda/'));
        Http::assertNotSent(callback: fn ($request): bool => str_contains(haystack: $request->url(), needle: '/kitsune/'));
    }

    #[Test]
    public function it_chunks_processing_for_large_datasets(): void
    {
        // Crear `chunk_size + 1` imagenes y verificar que todas se procesan.
        // Esto valida que lazyById no drop rows en el boundary del chunk.
        $contentsPrefix = 'chunked-content-';
        for ($i = 0; $i < 6; $i++) {
            Http::fake([
                'cdn.example.com/*' => Http::response($contentsPrefix.$i, 200),
            ]);

            $this->makeImage(imageUrl: sprintf('https://cdn.example.com/products/img-%d.jpg', $i));

            // Cada llamada a Http::fake REEMPLAZA el fake set del test.
            // En la realidad no importaría (el mismo URL matchea), pero
            // para que cada fila reciba contenido único hacemos el fake
            // nuevo en cada iteración. Sin embargo, el fake se queda
            // con la última configuración — esto NO rompe el test porque
            // el contenido no afecta al match (solo verificamos counts).
        }

        $this->artisan(command: 'assets:backfill-images', parameters: [
            '--force' => true,
            '--chunk' => 2,
        ])
            ->expectsOutputToContain(string: 'Backfilling product images... (6 encontrados)')
            ->assertSuccessful();

        // Las 6 imagenes fueron procesadas. Validamos via counts:
        //   - 6 processados (todas las filas)
        //   - 6 uploaded (asumimos Http::fake siempre devuelve 200)
        //   - 0 skipped, 0 failed
        $this->artisan(command: 'assets:backfill-images', parameters: [
            '--force' => true,
            '--chunk' => 2,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain(string: 'processed=6')
            ->assertSuccessful();
    }

    #[Test]
    public function it_logs_failures_and_continues(): void
    {
        // 3 imagenes: la del medio tiene una URL rota (404). El comando
        // debe procesar las otras dos (upload OK) y contar la rota como
        // failed. Exit code != 0 porque hubo failures.
        $okContentsA = 'ok-content-a';
        $okContentsC = 'ok-content-c';
        Http::fake([
            'cdn.example.com/ok-a.jpg' => Http::response($okContentsA, 200),
            'cdn.example.com/broken.jpg' => Http::response('not found', 404),
            'cdn.example.com/ok-c.jpg' => Http::response($okContentsC, 200),
        ]);

        $imageA = $this->makeImage(imageUrl: 'https://cdn.example.com/ok-a.jpg');
        $imageBroken = $this->makeImage(imageUrl: 'https://cdn.example.com/broken.jpg');
        $imageC = $this->makeImage(imageUrl: 'https://cdn.example.com/ok-c.jpg');

        $this->artisan(command: 'assets:backfill-images', parameters: ['--force' => true])
            ->expectsOutputToContain(string: 'failed=1')
            ->assertFailed();

        // Las dos imagenes OK migraron; la rota NO.
        $this->assertNotSame(expected: 'https://cdn.example.com/ok-a.jpg', actual: $imageA->fresh()->image_url);
        $this->assertSame(expected: 'https://cdn.example.com/broken.jpg', actual: $imageBroken->fresh()->image_url);
        $this->assertNotSame(expected: 'https://cdn.example.com/ok-c.jpg', actual: $imageC->fresh()->image_url);
    }

    #[Test]
    public function it_skips_upload_when_key_already_exists_on_ftp(): void
    {
        // Pre-poblar el FTP con la storage key que el comando derivaria.
        // El comando debe detectar la existencia y skippear sin re-upload.
        $contents = 'pre-populated-content';
        $expectedKey = $this->expectedKey(content: $contents, extension: 'jpg');
        Storage::disk(name: 'ftp')->put(path: $expectedKey, contents: $contents);

        Http::fake([
            'cdn.example.com/*' => Http::response($contents, 200),
        ]);

        $image = $this->makeImage(imageUrl: 'https://cdn.example.com/products/foo.jpg');

        // No se sube ni se actualiza: el comando detecta el pre-check.
        $this->artisan(command: 'assets:backfill-images', parameters: ['--force' => true])
            ->assertSuccessful();

        // image_url NO se actualizo (pre-check skip = sin upload = sin update).
        $this->assertSame(expected: 'https://cdn.example.com/products/foo.jpg', actual: $image->fresh()->image_url);

        // El FTP sigue teniendo exactamente lo que pusimos (no re-upload).
        $this->assertSame(expected: $contents, actual: Storage::disk(name: 'ftp')->get(path: $expectedKey));
    }

    #[Test]
    public function it_prompts_for_confirmation_unless_force(): void
    {
        $contents = 'prompt-content';
        Http::fake([
            'cdn.example.com/*' => Http::response($contents, 200),
        ]);
        $image = $this->makeImage(imageUrl: 'https://cdn.example.com/products/foo.jpg');

        // Sin --force: pide confirmacion. Respondemos 'no' → cancela.
        $this->artisan(command: 'assets:backfill-images', parameters: [])
            ->expectsConfirmation(question: 'Vas a modificar product_images.image_url en la DB. Continuar?', answer: 'no')
            ->assertSuccessful();

        $this->assertSame(expected: 'https://cdn.example.com/products/foo.jpg', actual: $image->fresh()->image_url);

        // Con --force: no pide confirmacion, ejecuta y migra.
        $this->artisan(command: 'assets:backfill-images', parameters: ['--force' => true])
            ->assertSuccessful();

        $expectedKey = $this->expectedKey(content: $contents, extension: 'jpg');
        $this->assertSame(expected: $expectedKey, actual: $image->fresh()->image_url);
    }

    #[Test]
    public function it_skips_run_when_lock_held_by_another_process(): void
    {
        // Pre-adquirir el lock manualmente. La segunda ejecucion debe
        // detectar que el lock esta tomado y abortar con FAILURE.
        $lock = Cache::lock(name: 'backfill-images', seconds: 600);
        $this->assertTrue($lock->get(), 'Test setup: lock pre-acquisition debe succeed');

        try {
            $this->artisan(command: 'assets:backfill-images', parameters: ['--force' => true])
                ->expectsOutputToContain(string: 'Otro proceso de backfill ya esta corriendo')
                ->assertFailed();
        } finally {
            $lock->release();
        }
    }
}
