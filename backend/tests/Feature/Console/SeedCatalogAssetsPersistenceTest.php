<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de lo que `assets:seed-catalog` PERSISTE por imagen y por
 * variante.
 *
 * La regresión que cubren: el seeder descubría el directorio de cada
 * variante y el ancho de cada resolución, y después los tiraba. El
 * resultado eran `product_images.variant_id` y `product_variants.handedness`
 * siempre vacíos — los backfills de hoy arreglaban los datos actuales y
 * la próxima carga del catálogo los perdía otra vez.
 *
 * El filesystem es temporal y `Storage::fake()` aísla el upload: ningún
 * test toca el FTP ni el árbol de `assets/` real.
 *
 * ⚠️ Cada webp fixture necesita bytes DISTINTOS: el `storage_key` es el
 *    SHA-256 del binario, así que dos archivos con el mismo contenido
 *    collapsan en la misma fila de `product_images` y el testaría mal.
 */
final class SeedCatalogAssetsPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const TENANT = 'instrumentos-musicales-la-voz';

    private string $tempAssetBase;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase trunca todo: la currency y el Store que el
        // seeder referencia vía FK hay que re-crearlos en cada test.
        $currency = Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true, 'is_primary' => true],
        );

        Store::query()->updateOrCreate(
            ['slug' => self::TENANT],
            ['name' => 'La Voz Instrumentos', 'is_active' => true, 'currency_id' => $currency->id],
        );

        Storage::fake('local-fake');

        $this->tempAssetBase = sys_get_temp_dir().'/atlas-seed-persist-'.uniqid();
        mkdir($this->tempAssetBase.'/products/'.self::TENANT, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempAssetBase)) {
            exec(sprintf('rm -rf %s', escapeshellarg($this->tempAssetBase)));
        }

        parent::tearDown();
    }

    /**
     * Crea un webp con contenido único y devuelve su path absoluto.
     */
    private function webp(string $relativePath): string
    {
        $full = $this->tempAssetBase.'/products/'.self::TENANT.'/'.$relativePath;
        $dir = dirname($full);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        // Bytes únicos por archivo: el storage_key es el hash del binario.
        file_put_contents($full, 'RIFF'.random_bytes(4).'WEBPVP8 '.random_bytes(24));

        return $full;
    }

    private function runSeeder(): int
    {
        return $this->artisan('assets:seed-catalog', [
            '--asset-base' => $this->tempAssetBase,
            '--storage-disk' => 'local-fake',
        ])->run();
    }

    #[Test]
    public function it_attaches_every_image_to_its_own_variant_on_a_multi_variant_product(): void
    {
        $this->webp('gibson/les-paul-standard/ebony/front-1600x1600.webp');
        $this->webp('gibson/les-paul-standard/ebony/back-1600x1600.webp');
        $this->webp('gibson/les-paul-standard/vintage-sunburst/front-1600x1600.webp');

        // No se asserta el exit code: `CatalogAssetsSeeder::verify()`
        // cuenta las imágenes primary POR PRODUCTO, y un producto
        // multi-acabado tiene legítimamente una primary por variante.
        // Eso hace que verify devuelva 1 en los 19 productos
        // multi-variante del catálogo (bug preexistente, ajeno a este
        // cambio). Lo que se verifica acá es lo que el seeder PERSISTE.
        $this->runSeeder();

        $product = Product::query()->where(column: 'slug', operator: '=', value: 'les-paul-standard')->firstOrFail();
        $this->assertCount(2, $product->variants);

        $images = $product->images()->with(relations: ['variant'])->get();
        $this->assertCount(3, $images);

        // Cada fila apunta a UN variant (el de su subdirectorio), no a
        // null como antes del fix.
        foreach ($images as $image) {
            $this->assertNotNull(
                $image->variant_id,
                "La imagen {$image->image_url} quedó sin variant_id.",
            );
        }

        $ebony = $product->variants()->where(column: 'finish_slug', operator: '=', value: 'ebony')->firstOrFail();
        $sunburst = $product->variants()->where(column: 'finish_slug', operator: '=', value: 'vintage-sunburst')->firstOrFail();

        $this->assertSame(
            2,
            $product->images()->where(column: 'variant_id', operator: '=', value: $ebony->id)->count(),
        );
        $this->assertSame(
            1,
            $product->images()->where(column: 'variant_id', operator: '=', value: $sunburst->id)->count(),
        );
    }

    #[Test]
    public function it_attaches_every_image_to_the_only_variant_on_a_single_variant_product(): void
    {
        // Sin subdirectorio de variante: los webp cuelgan del root del
        // producto (el caso de los 150 productos single-variant).
        $this->webp('epiphone/sg-standard/front-1600x1600.webp');
        $this->webp('epiphone/sg-standard/back-1600x1600.webp');

        $this->assertSame(0, $this->runSeeder());

        $product = Product::query()->where(column: 'slug', operator: '=', value: 'sg-standard')->firstOrFail();
        $variant = $product->variants()->firstOrFail();

        $images = $product->images()->get();
        $this->assertCount(2, $images);
        $this->assertTrue($images->every(fn (ProductImage $i): bool => $i->variant_id === $variant->id));
    }

    #[Test]
    public function it_persists_the_width_of_every_resolution_and_only_marks_the_1600_of_the_primary_role_as_primary(): void
    {
        // 4 resoluciones de `front` + 2 de `back`. La galería tiene que
        // poder colapsar las 4 de `front` en un slide y armar el srcset
        // con las otras tres; sin `width` no hay forma de distinguirlas.
        foreach ([400, 800, 1200, 1600] as $w) {
            $this->webp("gibson/j-45/front-{$w}x{$w}.webp");
        }
        $this->webp('gibson/j-45/back-1600x1600.webp');
        $this->webp('gibson/j-45/back-400x400.webp');

        $this->assertSame(0, $this->runSeeder());

        $product = Product::query()->where(column: 'slug', operator: '=', value: 'j-45')->firstOrFail();
        $images = $product->images()->orderBy(column: 'sort_order')->get();

        $this->assertCount(6, $images);

        $widths = $images->where('sort_order', 1)->pluck('width')->sort()->values()->all();
        $this->assertSame([400, 800, 1200, 1600], $widths, 'Las 4 resoluciones de `front` deben quedar con su ancho.');

        // Primary SOLO en la de mayor resolución del rol principal, como
        // siempre — si no, el verify reporta N primaries por producto.
        // `where()` sobre Collection devuelve la MISMA instancia, así que
        // encadenarle `get()` llama a `Collection::get()` y no a la BD.
        $primaries = $images->filter(fn (ProductImage $i): bool => $i->is_primary === true)->values();
        $this->assertCount(1, $primaries);
        $this->assertSame(1600, (int) $primaries->first()->width);
        $this->assertSame(1, (int) $primaries->first()->sort_order);
    }

    #[Test]
    public function it_derives_handedness_from_the_product_text_not_only_from_the_variant_slug(): void
    {
        // El nombre dice "Left Handed" con espacio y el directorio de
        // variante NO menciona la mano. La versión anterior del seeder
        // sólo hacía `str_contains($variantSlug, 'left-handed')` y
        // devolvía null: el producto quedaba diestro en la PDP.
        $this->webp('kramer/striker-hss-left-handed-floyd-rose-special-ebony/front-1600x1600.webp');

        $this->assertSame(0, $this->runSeeder());

        $product = Product::query()
            ->where(column: 'slug', operator: '=', value: 'striker-hss-left-handed-floyd-rose-special-ebony')
            ->firstOrFail();

        $variants = $product->variants()->get();
        $this->assertNotEmpty($variants);
        foreach ($variants as $variant) {
            $this->assertSame(
                'left-handed',
                $variant->handedness,
                'La mano del producto debe llegar a todas sus variantes.',
            );
        }
    }

    #[Test]
    public function it_leaves_handedness_null_for_a_regular_product(): void
    {
        $this->webp('epiphone/j-45-standard/front-1600x1600.webp');

        $this->assertSame(0, $this->runSeeder());

        $variant = ProductVariant::query()->where(column: 'name', operator: '=', value: 'J 45 Standard')->firstOrFail();

        $this->assertNull($variant->handedness, 'Un producto diestro se guarda como NULL, no como un string falso.');
    }

    #[Test]
    public function it_creates_the_product_with_no_variants_and_no_images_when_the_asset_directory_has_no_webps(): void
    {
        // Directorio de producto vacío: el producto se crea igual (el
        // catálogo existe aunque las fotos no hayan llegado), sin
        // variantes fantasma y sin imágenes. `verify()` reporta "sin
        // imagen primary" y el comando sale con 1 — comportamiento
        // preexistente, idéntico antes y después de este cambio.
        mkdir($this->tempAssetBase.'/products/'.self::TENANT.'/gibson/catalog-meta-only', 0755, true);

        $this->runSeeder();

        $product = Product::query()->where(column: 'slug', operator: '=', value: 'catalog-meta-only')->firstOrFail();
        $this->assertCount(0, $product->variants);
        $this->assertCount(0, $product->images);
    }

    #[Test]
    public function it_keeps_both_rows_when_two_variants_share_the_same_binary(): void
    {
        // El `storage_key` es el hash del binario y el path no lleva
        // variante, así que el mismo archivo bajo dos variantes produce
        // la misma key. Con la clave (product_id, image_url) una fila se
        // pisaba a la otra y la variante perdedora quedaba sin esa foto
        // —y sin primary—. Con `variant_id` en la clave cada reclamo
        // tiene su fila.
        $shared = 'RIFF'.random_bytes(4).'WEBPVP8 '.random_bytes(24);
        $this->writeWebp(
            relativePath: 'gibson/j-45-standard/vintage-burst/headstock-1600x1600.webp',
            bytes: $shared,
        );
        $this->writeWebp(
            relativePath: 'gibson/j-45-standard/vintage-burst-left-handed/front-1600x1600.webp',
            bytes: $shared,
        );

        $this->assertSame(0, $this->runSeeder());

        $product = Product::query()->where(column: 'slug', operator: '=', value: 'j-45-standard')->firstOrFail();
        $images = $product->images()->get();

        $this->assertCount(2, $images, 'Cada variante tiene que conservar su fila aunque el binario sea el mismo.');

        // El storage es content-addressed: un binario, una key, una sola
        // copia en disco. Que las dos filas apunten a la MISMA key es lo
        // correcto y lo que evita subir dos veces el mismo archivo — los
        // left-handed reusan las fotos del diestro justamente para eso.
        // Lo que las separa es `variant_id`.
        //
        // Exigir dos URLs distintas codificaba la hipótesis de que la key
        // tenía que desambiguar el binario, que es al revés de lo que
        // hace `StorageKeyDeriver::derive()`.
        $this->assertCount(
            1,
            $images->pluck('image_url')->unique(),
            'El binario es uno solo: las dos filas comparten la misma key de storage.',
        );
        $this->assertCount(
            2,
            $images->pluck('variant_id')->unique(),
            'Lo que separa las filas es la variante, no la key de storage.',
        );

        // `alt_text` tiene la forma "{nombre} - vista {rol}"; el rol es lo que
        // va después de la última " - vista ".
        $roles = $images->pluck('alt_text')->map(
            static fn (string $alt): string => substr($alt, (int) strrpos($alt, ' ') + 1),
        )->sort()->values()->all();
        $this->assertSame(['front', 'headstock'], $roles);
    }

    #[Test]
    public function it_does_not_duplicate_the_product_slug_in_the_sku_of_a_single_variant_product(): void
    {
        // Producto real del catálogo: 68 caracteres de slug y sin
        // subdirectorio de variante. El seeder usaba el slug del producto
        // como slug de variante, así que el SKU salía
        // `EPIP-LESP...FRETBOARDS-LESP...FRETBOARDS` (122 caracteres) y el
        // INSERT moría con `SQLSTATE[22001] value too long for type
        // character varying(120)`, lo que abortaba también la fila del
        // producto y sus imágenes.
        $slug = 'les-paul-standard-60s-iced-tea-burst-left-handed-rosewood-fretboards';
        $this->webp("epiphone/{$slug}/front-1600x1600.webp");

        $this->assertSame(0, $this->runSeeder(), 'El SKU duplicado abortaba el seed con un error de longitud.');

        $variant = ProductVariant::query()->firstOrFail();

        $this->assertSame(
            'EPIP-LESPAULSTANDARD60SICEDTEABURSTLEFTHANDEDROSEWOODFRETBOARDS',
            $variant->sku,
        );
        $this->assertSame(
            1,
            substr_count($variant->sku, 'LESPAULSTANDARD60SICEDTEABURSTLEFTHANDEDROSEWOODFRETBOARDS'),
            'El slug del producto aparece una sola vez en el SKU.',
        );
        $this->assertLessThanOrEqual(120, strlen($variant->sku));
    }

    #[Test]
    public function it_keeps_the_three_part_sku_when_the_variant_has_its_own_directory(): void
    {
        // Con subdirectorio de variante el SKU sí lleva los tres
        // segmentos: la variante aporta información que el producto no
        // tiene, y sin ella dos acabados del mismo modelo serían el
        // mismo código contra el índice único de `product_variants.sku`.
        $this->webp('gibson/les-paul-standard/ebony/front-1600x1600.webp');

        $this->assertSame(0, $this->runSeeder());

        $variant = ProductVariant::query()->firstOrFail();

        $this->assertSame('GIBS-LESPAULSTANDARD-EBONY', $variant->sku);
        $this->assertSame('ebony', $variant->finish_slug);
    }

    #[Test]
    public function it_keeps_the_mirrored_finish_slug_when_the_variant_directory_reuses_the_product_slug(): void
    {
        // Sin subdirectorio de variante, el "slug de variante" es el slug
        // del producto. Se conserva el espejo en `finish_slug` aunque no
        // aporte información propia, porque `NULL` rompe tres consumidores
        // que hoy funcionan:
        //
        //  - `StorefrontProductResource::resolveVariants()` filtra las
        //    variantes sin `finish_slug` -> el PDP devolvería `variants: []`.
        //  - `BackfillVariantFinishCommand` usa "finish_slug !==
        //    products.slug" como gate de "ya tiene acabado real"; con
        //    `NULL` el gate nunca pasa y el backfill queda neutralizado
        //    justo para las filas que debería arreglar.
        //  - `BackfillImageVariantCommand` indexa por `finish_slug` y
        //    contaría esas imágenes como huérfanas.
        //
        // Medido: ninguno de los 306 espejos del árbol actual supera los
        // 64 caracteres de la columna, así que espejar no desborda.
        $slug = 'les-paul-standard-60s-iced-tea-burst-left-handed-rosewood-fretboards';
        $this->webp("epiphone/{$slug}/front-1600x1600.webp");

        $this->assertSame(0, $this->runSeeder());

        $variant = ProductVariant::query()->firstOrFail();

        $this->assertSame($slug, $variant->finish_slug);
        $this->assertSame('Les Paul Standard 60s Iced Tea Burst Left Handed Rosewood Fretboards', $variant->finish_label);
        // El SKU no duplica el slug aunque el directorio de variante lo
        // repita: ese era el overflow real del VARCHAR(120). El SKU se
        // construye en mayúsculas y sin guiones, así que la comparación
        // se hace contra esa misma forma; lo que importa es que el slug
        // aparezca UNA vez y que entre en la columna.
        $slugInSkuForm = strtoupper(str_replace('-', '', $slug));
        $this->assertSame(1, substr_count((string) $variant->sku, $slugInSkuForm));
        $this->assertLessThanOrEqual(120, strlen((string) $variant->sku));
        // El nombre de la variante se conserva: es lo que la PDP muestra.
        $this->assertSame('Les Paul Standard 60s Iced Tea Burst Left Handed Rosewood Fretboards', $variant->name);
    }

    /**
     * Escribe un webp con bytes concretos. Necesario cuando dos archivos
     * tienen que compartir el MISMO binario.
     */
    private function writeWebp(string $relativePath, string $bytes): string
    {
        $full = $this->tempAssetBase.'/products/'.self::TENANT.'/'.$relativePath;
        $dir = dirname($full);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($full, $bytes);

        return $full;
    }

    #[Test]
    public function it_skips_files_whose_name_does_not_follow_the_role_size_convention_without_failing(): void
    {
        // `logo.webp` no matchea `{role}-{WxH}.webp`: se ignora en silencio,
        // sin ensuciar la galería con una fila sin ancho ni rol.
        $this->webp('gibson/sg-standard/logo.webp');
        $this->webp('gibson/sg-standard/front-1600x1600.webp');

        $this->assertSame(0, $this->runSeeder());

        $product = Product::query()->where(column: 'slug', operator: '=', value: 'sg-standard')->firstOrFail();
        $images = $product->images()->get();

        $this->assertCount(1, $images);
        $this->assertSame(1600, (int) $images->first()->width);
    }
}
