<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CatalogAssetsSeederFactory;
use Tests\TestCase;

/**
 * Tests de `CatalogAssetsSeeder::verify()` — la Fase 5 del seeder.
 *
 * La regresión que cubren: `verify()` contaba imágenes primary POR
 * PRODUCTO, y un producto con N variantes legítimamente tiene N
 * imágenes primary (una por acabado, que es lo correcto para una
 * galería por variante). El conteo por producto daba exit 1 en los
 * 19 productos multi-acabado aunque los datos estuvieran bien.
 *
 * Decisión: la regla es "una primary POR VARIANT" y esto es lo que
 * cubre cada test.
 */
#[Group('seeders')]
#[Group('catalog')]
final class SeedCatalogAssetsVerifyTest extends TestCase
{
    use RefreshDatabase;

    private const TENANT = 'instrumentos-musicales-la-voz';

    private string $tempAssetBase;

    private CatalogAssetsSeederFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true, 'is_primary' => true],
        );

        Store::query()->updateOrCreate(
            ['slug' => self::TENANT],
            ['name' => 'La Voz Instrumentos', 'is_active' => true, 'currency_id' => $currency->id],
        );

        Storage::fake('local-fake');

        $this->tempAssetBase = sys_get_temp_dir().'/atlas-seed-verify-'.uniqid();
        mkdir($this->tempAssetBase.'/products/'.self::TENANT, 0755, true);

        $this->factory = new CatalogAssetsSeederFactory(
            tempAssetBase: $this->tempAssetBase,
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

    #[Test]
    public function it_passes_for_a_single_variant_product_with_one_primary(): void
    {
        // 1 variant con su front-1600 — escenario típico de un single-finish.
        $this->factory->createWebp('epiphone/sg-standard/front-1600x1600.webp');
        $this->factory->runSeeder();

        $result = $this->factory->verify();

        $this->assertTrue(
            condition: $result['ok'],
            message: 'verify() debe pasar para producto single-variant con una primary: '.implode('; ', $result['issues']),
        );
        $this->assertSame(
            expected: 1,
            actual: $result['stats']['variants_with_primary'],
            message: 'Una variant bien servida debe contarse como "with_primary".',
        );
    }

    #[Test]
    public function it_passes_for_a_multi_variant_product_with_a_primary_per_variant(): void
    {
        // 2 variants, cada una con su front-1600. Esto era el caso que
        // daba exit 1 en master: el verify anterior sumaba las primaries
        // del producto, veía 2 y reportaba error.
        $this->factory->createWebp('gibson/les-paul-standard/ebony/front-1600x1600.webp');
        $this->factory->createWebp('gibson/les-paul-standard/vintage-sunburst/front-1600x1600.webp');

        $this->factory->runSeeder();

        $result = $this->factory->verify();

        $this->assertTrue(
            condition: $result['ok'],
            message: 'verify() debe pasar para multi-variant con una primary por variant: '.implode('; ', $result['issues']),
        );
        $this->assertSame(
            expected: 2,
            actual: $result['stats']['variants_with_primary'],
            message: 'Cada variant con su primary debe contarse.',
        );
    }

    #[Test]
    public function it_fails_when_a_variant_has_no_primary_image(): void
    {
        // ebony tiene front-1600 → recibe primary.
        // vintage-sunburst solo tiene front-800 (sin 1600) → el seeder
        // no puede marcar primary porque la regla es "rol primario
        // Y size=1600". Resultado: la variant queda sin primary.
        $this->factory->createWebp('gibson/les-paul-standard/ebony/front-1600x1600.webp');
        $this->factory->createWebp('gibson/les-paul-standard/vintage-sunburst/front-800x800.webp');

        $this->factory->runSeeder();

        $result = $this->factory->verify();

        $this->assertFalse($result['ok'], 'verify() debe fallar cuando una variant no tiene primary.');

        $issueMessages = implode("\n", $result['issues']);
        $this->assertStringContainsString(
            needle: 'variant vintage-sunburst: sin imagen primary',
            haystack: $issueMessages,
        );
        $this->assertStringNotContainsString(
            needle: 'variant ebony',
            haystack: $issueMessages,
            message: 'La variant ebony tiene primary — no debe aparecer en los issues.',
        );
    }

    #[Test]
    public function it_fails_when_a_variant_has_more_than_one_primary_image(): void
    {
        // Caso real donde SÍ puede haber 2 primaries: drift entre el
        // seeder y un backfill manual, o bug que vuelva a marcar dos
        // filas. Forzamos el estado malformado por BD.
        $this->factory->createWebp('gibson/les-paul-standard/ebony/front-1600x1600.webp');
        $this->factory->createWebp('gibson/les-paul-standard/ebony/back-1600x1600.webp');
        $this->factory->runSeeder();

        $product = Product::query()->where('slug', 'les-paul-standard')->firstOrFail();
        $variant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('finish_slug', 'ebony')
            ->firstOrFail();
        // Marcar el `back-1600x1600` también como primary via
        // `sort_order=2` (rol back). Simula el drift.
        \App\Models\Catalog\ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->where(column: 'variant_id', operator: '=', value: $variant->id)
            ->where(column: 'sort_order', operator: '=', value: 2)
            ->update(['is_primary' => true]);

        $result = $this->factory->verify();

        $this->assertFalse($result['ok'], 'verify() debe fallar con 2 primaries en una variant.');
        $this->assertStringContainsString(
            needle: 'variant ebony: 2 imágenes primary',
            haystack: implode("\n", $result['issues']),
        );
    }

    #[Test]
    public function it_fails_when_primary_is_not_1600px_wide(): void
    {
        // Una imagen existe, está marcada como primary en el seeder,
        // pero su ancho es 800 (no 1600). Esto no puede ocurrir con
        // el seeder actual porque solo marca primary a las de 1600.
        // Forzamos el estado malformado por BD para verificar que
        // el check de "primary width" se cumpla.
        $this->factory->createWebp('gibson/j-45/front-1600x1600.webp');
        $this->factory->runSeeder();

        \App\Models\Catalog\ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: Product::query()->where(column: 'slug', operator: '=', value: 'j-45')->firstOrFail()->id)
            ->update(['width' => 800]);

        $result = $this->factory->verify();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString(
            needle: 'primary width=800 (debería ser 1600)',
            haystack: implode("\n", $result['issues']),
        );
    }
}
