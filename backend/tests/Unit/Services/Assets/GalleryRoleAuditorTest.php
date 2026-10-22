<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assets;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use App\Services\Assets\AssetFilenameParser;
use App\Services\Assets\GalleryRoleAuditor;
use App\Services\Assets\StorageKeyDeriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del auditor `GalleryRoleAuditor`.
 *
 * Cubren el bug Oct 2026 donde `storageKeyFor()` derivaba el `entity`
 * del path por offsets (`count($parts) - 3` sobre `{tenant}/{brand}/...`),
 * lo que:
 *
 *   - Acertaba en multi-variante (`{brand}/{product}/{variant}/{file}`):
 *     la posición 2 es el slug del producto y el hash seguía coincidiendo
 *     contra la BD.
 *   - Fallaba en single-variante (`{brand}/{product}/{file}`): la misma
 *     fórmula caía en la MARCA, fabricando un key que el seeder nunca
 *     escribió.
 *
 * `StorageKeyDeriver::derive()` embebe el entity en la key
 * (`tenants/{tenant}/products/{entity}/{hash12}.webp`) y el seeder
 * siempre usa `$product->slug`. El auditor inventaba entonces keys que
 * no existían en la BD y reportaba como `missing_rows` el 97,3% del
 * dataset (6.960/7.152) cuando todas estaban.
 *
 * Fix: `variantFolders()` ya resuelve el slug del producto del árbol
 * de assets, así que `storageKeyFor(webpPath, productSlug)` lo recibe
 * como parámetro en vez de recalcularlo desde offsets.
 *
 * Lo que estos tests fijan:
 *
 *   1. Layout single-variante reconcilia limpio (cero missing).
 *   2. Layout multi-variante reconcilia limpio (cero missing).
 *   3. Un archivo sin fila en BD todavía aparece como missing — el
 *      fix no ciega al auditor.
 *   4. El storage_key de un archivo single-variante embebe el slug
 *      del PRODUCTO, no de la marca (con dos marcas distintas para
 *      que una eventual fuga sea observable).
 */
#[CoversClass(GalleryRoleAuditor::class)]
#[Group('assets')]
final class GalleryRoleAuditorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tenant estable durante todos los tests. NO debe coincidir con
     * el dev DB: los tests corren contra `<instancia>_test`.
     */
    private const TENANT = 'instrumentos-musicales-la-voz';

    /** Marca A — single-variante. */
    private const BRAND_EPI = 'epiphone';

    /** Marca B — única razón de existir: hacer observable una fuga de marca. */
    private const BRAND_GIB = 'gibson';

    private string $tempAssetBase;

    private StorageKeyDeriver $deriver;

    private GalleryRoleAuditor $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase trunca todo. El `auditor` no toca estas tablas,
        // pero las explota indirectamente vía FKs en `products` (currency
        // también es restrict). Aseguramos un USD porque el catálogo real
        // lo asume.
        Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true, 'is_primary' => true],
        );

        $this->deriver = new StorageKeyDeriver;
        $this->tempAssetBase = sys_get_temp_dir().'/atlas-auditor-'.uniqid();
        mkdir($this->tempAssetBase.'/products/'.self::TENANT, 0755, true);

        $this->auditor = new GalleryRoleAuditor(
            deriver: $this->deriver,
            filenames: new AssetFilenameParser,
            assetBase: $this->tempAssetBase,
            tenantSlug: self::TENANT,
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempAssetBase)) {
            exec(sprintf('rm -rf %s', escapeshellarg($this->tempAssetBase)));
        }

        parent::tearDown();
    }

    // ────────────────────────────────────────────────────────────────
    // helpers
    // ────────────────────────────────────────────────────────────────

    /**
     * Escribe un webp con bytes únicos en disco. Devuelve el path
     * absoluto para que el test encadene asserts y reconstruya la key.
     *
     * El `storage_key` es el SHA-256 del binario, así que dos fixtures
     * con el mismo contenido colapsan en la misma fila de BD. Útil
     * cuando queremos probar el caso "una foto compartida"; irrelevante
     * (y molesto) en estos tests, donde cada webp representa una
     * imagen distinta.
     */
    private function writeWebp(string $relativePath): string
    {
        $full = $this->tempAssetBase.'/products/'.self::TENANT.'/'.$relativePath;
        $dir = dirname($full);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($full, 'RIFF'.random_bytes(4).'WEBPVP8 '.random_bytes(24));

        return $full;
    }

    /**
     * Devuelve el storage_key EXACTO que el seeder escribiría
     * (`entity = $product->slug`). Es la única forma de garantizar que
     * la BD tendrá la misma key que el auditor deriva — clave para
     * los tests "no debería haber missing".
     */
    private function keyFor(Product $product, string $webpPath): string
    {
        return $this->deriver->derive(
            tenantSlug: self::TENANT,
            collection: 'products',
            entity: $product->slug,
            content: (string) file_get_contents(filename: $webpPath),
            extension: 'webp',
        );
    }

    /**
     * Crea Brand + Category mínimas + Product. Las FKs de `products`
     * son restrictivas — sin Brand/Category los inserts fallan.
     *
     * El slug del producto es lo que el auditor usa como entity. Lo
     * recibimos por parámetro para que cada test pueda fijar su propio
     * slug sin chocar con el resto de la suite.
     */
    private function makeProduct(string $productSlug): Product
    {
        $brand = Brand::query()->updateOrCreate(
            ['slug' => 'auditor-test-brand'],
            ['name' => 'Auditor Test Brand', 'is_active' => true],
        );
        $category = Category::query()->updateOrCreate(
            ['slug' => 'auditor-test-cat'],
            ['name' => 'Auditor Test Cat', 'is_active' => true],
        );

        return Product::query()->updateOrCreate(
            ['slug' => $productSlug],
            [
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'name' => $productSlug,
                'sku' => strtoupper('AUD-'.str_replace('-', '', $productSlug)).'-'.uniqid(),
                'base_price_cents' => 100,
                'cost_cents' => 50,
                'is_active' => true,
                'published_at' => now(),
            ],
        );
    }

    /**
     * Crea una variante para producto single-variant. `finish_slug`
     * se iguala al slug del producto para que el `resolveVariant()`
     * del auditor la encuentre por slug.
     *
     * El seeder hoy deja `finish_slug` en NULL en ese caso (el directorio
     * de variante ES el del producto y no aporta un acabado), así que
     * estas filas ejercitan el camino del slug; las que llegan del seed
     * real resuelven por el fallback de variante única de
     * `resolveVariant()`. Ambas rutas tienen que dar la misma variante.
     */
    private function makeSingleVariant(Product $product): ProductVariant
    {
        return ProductVariant::query()->updateOrCreate(
            ['product_id' => $product->id, 'sku' => 'AUD-SINGLE-'.uniqid()],
            [
                'name' => $product->slug,
                'price_cents' => 100,
                'cost_cents' => 50,
                'is_active' => true,
                'finish_slug' => $product->slug,
                'condition' => 'nuevo',
                'stock' => 0,
            ],
        );
    }

    /**
     * Crea una variante para producto multi-variant.
     */
    private function makeVariant(Product $product, string $finishSlug): ProductVariant
    {
        return ProductVariant::query()->updateOrCreate(
            ['product_id' => $product->id, 'sku' => 'AUD-'.strtoupper($finishSlug).'-'.uniqid()],
            [
                'name' => $finishSlug,
                'price_cents' => 100,
                'cost_cents' => 50,
                'is_active' => true,
                'finish_slug' => $finishSlug,
                'condition' => 'nuevo',
                'stock' => 0,
            ],
        );
    }

    /**
     * Inserta una fila en `product_images` con la key que el seeder
     * usaría. Test 1 y 2 necesitan el `image_url` correcto — cualquier
     * mismatch sería `missing_rows`.
     */
    private function insertImageForVariant(
        Product $product,
        ProductVariant $variant,
        string $webpPath,
        string $role,
        int $width,
        bool $isPrimary,
        int $sortOrder,
    ): ProductImage {
        return ProductImage::query()->create(attributes: [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'image_url' => $this->keyFor(product: $product, webpPath: $webpPath),
            'alt_text' => "{$product->name} - vista {$role}",
            'is_primary' => $isPrimary,
            'sort_order' => $sortOrder,
            'width' => $width,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // tests
    // ────────────────────────────────────────────────────────────────

    #[Test]
    public function it_reconciles_a_single_variant_product_with_no_missing_rows(): void
    {
        $product = $this->makeProduct(productSlug: 'sg-standard-1961');
        $variant = $this->makeSingleVariant(product: $product);

        // Single-variant: los webp cuelgan del directorio del PRODUCTO,
        // no de un subdirectorio de variante. Esta es la layout donde
        // el bug viejo (offsets sobre el path) devolvía el BRAND como
        // entity en vez del producto.
        $front = $this->writeWebp(self::BRAND_EPI.'/sg-standard-1961/front-1600x1600.webp');
        $back = $this->writeWebp(self::BRAND_EPI.'/sg-standard-1961/back-1600x1600.webp');

        // El seeder habría escrito estas dos filas.
        $this->insertImageForVariant(
            product: $product,
            variant: $variant,
            webpPath: $front,
            role: 'front',
            width: 1600,
            isPrimary: true,
            sortOrder: 1,
        );
        $this->insertImageForVariant(
            product: $product,
            variant: $variant,
            webpPath: $back,
            role: 'back',
            width: 1600,
            isPrimary: false,
            sortOrder: 2,
        );

        $report = $this->auditor->audit();

        $this->assertSame(expected: 2, actual: $report['files_on_disk']);
        $this->assertSame(expected: 2, actual: $report['parseable_files']);
        $this->assertSame(
            expected: [],
            actual: $report['missing_rows'],
            message: 'Single-variant con BD sincronizada NO debe reportar missing_rows.',
        );
        $this->assertSame(expected: [], actual: $report['unparseable']);
    }

    #[Test]
    public function it_reconciles_a_multi_variant_product_with_no_missing_rows(): void
    {
        $product = $this->makeProduct(productSlug: 'les-paul-standard');
        $ebony = $this->makeVariant(product: $product, finishSlug: 'ebony');
        $burst = $this->makeVariant(product: $product, finishSlug: 'vintage-sunburst');

        // Multi-variant: un webp por subdirectorio de variante.
        $ebonyFront = $this->writeWebp(self::BRAND_GIB.'/les-paul-standard/ebony/front-1600x1600.webp');
        $burstFront = $this->writeWebp(self::BRAND_GIB.'/les-paul-standard/vintage-sunburst/front-1600x1600.webp');

        $this->insertImageForVariant(
            product: $product,
            variant: $ebony,
            webpPath: $ebonyFront,
            role: 'front',
            width: 1600,
            isPrimary: true,
            sortOrder: 1,
        );
        $this->insertImageForVariant(
            product: $product,
            variant: $burst,
            webpPath: $burstFront,
            role: 'front',
            width: 1600,
            isPrimary: true,
            sortOrder: 1,
        );

        $report = $this->auditor->audit();

        $this->assertSame(expected: [], actual: $report['missing_rows']);
        $this->assertSame(expected: [], actual: $report['unparseable']);
    }

    #[Test]
    public function it_still_flags_a_row_when_the_webp_has_no_matching_product_image(): void
    {
        $product = $this->makeProduct(productSlug: 'es-335-reissue');
        $this->makeSingleVariant(product: $product);

        // Archivo parseable en disco pero SIN fila en `product_images`.
        // El fix no debe haber cegado al auditor: este archivo tiene
        // que caer en `missing_rows` exactamente una vez.
        $this->writeWebp(self::BRAND_EPI.'/es-335-reissue/front-1600x1600.webp');

        $report = $this->auditor->audit();

        $this->assertCount(1, $report['missing_rows']);
        $missing = $report['missing_rows'][0];
        $this->assertSame(expected: 'es-335-reissue', actual: $missing['product']);
        $this->assertSame(expected: (int) $product->id, actual: $missing['product_id']);
        $this->assertSame(expected: 'front', actual: $missing['role']);
        $this->assertSame(expected: 1600, actual: $missing['width']);
    }

    #[Test]
    public function it_embeds_the_product_slug_not_the_brand_in_single_variant_storage_keys(): void
    {
        // Producto A bajo `epiphone`, producto B bajo `gibson`.
        // Usar dos marcas hace observable una fuga: `epiphone` solo
        // puede aparecer en la key del producto A si el auditor la
        // metió por error — el resto del sistema la ignora.
        $productA = $this->makeProduct(productSlug: '1962-es-335-reissue-sixties-cherry');
        $this->makeSingleVariant(product: $productA);

        $productB = $this->makeProduct(productSlug: 'sg-standard-left-handed');
        $this->makeSingleVariant(product: $productB);

        $frontA = $this->writeWebp(self::BRAND_EPI.'/1962-es-335-reissue-sixties-cherry/front-1600x1600.webp');
        $frontB = $this->writeWebp(self::BRAND_GIB.'/sg-standard-left-handed/front-1600x1600.webp');

        // Sin filas en la BD → ambas imágenes caen en `missing_rows`
        // y exponen su `storage_key`. La key es justo lo que el
        // auditor esperaba EXISTIR si el seeder las hubiera escrito.
        // Si la marca se filtró al entity, el prefijo diría
        // `/products/epiphone/` en vez de `/products/1962-es-.../`.
        $report = $this->auditor->audit();

        $this->assertCount(2, $report['missing_rows']);

        $keyA = $this->keyFor(product: $productA, webpPath: $frontA);
        $keyB = $this->keyFor(product: $productB, webpPath: $frontB);

        $prefixA = 'tenants/'.self::TENANT.'/products/1962-es-335-reissue-sixties-cherry/';
        $prefixB = 'tenants/'.self::TENANT.'/products/sg-standard-left-handed/';

        // El auditor no expone la key por archivo: la inferimos vía la
        // derivación directa (que es lo que el auditor hace internamente
        // con `$product->slug` desde `variantFolders()`) y comparamos
        // contra el prefijo que DEBERÍA aparecer si la BD tuviera la
        // fila. Una fuga de marca cambiaría ese prefijo a algo como
        // `/products/epiphone/`.
        $this->assertStringStartsWith(
            prefix: $prefixA,
            string: $keyA,
            message: 'El entity del storage_key tiene que ser el slug del producto, no el de la marca.',
        );
        $this->assertStringStartsWith(prefix: $prefixB, string: $keyB);
        $this->assertStringNotContainsString(
            needle: 'epiphone',
            haystack: $keyA,
            message: 'La marca NO debe filtrarse al entity del storage_key.',
        );
        $this->assertStringNotContainsString(needle: 'gibson', haystack: $keyB);

        // Complementario: el audit no fabricó una key fantasma con la
        // marca. Si la fabricara, no matchearía con la BD y caería en
        // `missing_rows` — que es exactamente el reporte del bug viejo.
        // Aquí validamos que ese escenario no se produce: el "missing"
        // es por la fila ausente en BD, no por mismatch de marca.
        $missingA = collect(value: $report['missing_rows'])
            ->firstWhere(key: 'product', value: '1962-es-335-reissue-sixties-cherry');
        $this->assertNotNull($missingA, 'El producto A debe aparecer en missing_rows (no hay fila en BD).');
        $this->assertSame(
            expected: $keyA,
            actual: $missingA['storage_key'],
            message: 'El storage_key reportado para el archivo ausente debe ser el derivado del slug del producto.',
        );
    }
}
