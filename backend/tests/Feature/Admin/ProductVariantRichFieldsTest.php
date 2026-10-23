<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de los campos rich de variante escribibles por la API admin
 * (slice 1.4): `finish_slug`, `finish_label`, `finish_hex`, `condition`,
 * `stock` y `handedness`.
 *
 * El eje de todo el archivo es la categoría: `guitars` es finishable
 * (el acabado es un concepto del dominio) y `capos` no lo es (el color
 * es un atributo del producto). Ver
 * `App\Services\Catalog\VariantFinishDeriver`.
 *
 * Cubre:
 *  - round-trip de cada campo en alta y en update.
 *  - formato de `finish_hex` (`#RRGGBB` y `#RRGGBBAA`) y sus rechazos.
 *  - unicidad de `finish_slug` con el alcance REAL: por producto.
 *  - `condition` / `handedness` contra el vocabulario del dominio.
 *  - `stock` no negativo.
 *  - update parcial que no borra lo que no recibe.
 *  - exposición de los campos en el resource admin.
 */
#[Group('admin')]
#[Group('catalog')]
final class ProductVariantRichFieldsTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_BASE = 'http://panda-estampados.com/api/v1/admin/variants';

    private User $admin;

    private Store $store;

    private Currency $currency;

    private Brand $brand;

    /** Categoría finishable: el acabado es un concepto del dominio. */
    private Category $guitars;

    /** Categoría NO finishable: el color es un atributo del producto. */
    private Category $capos;

    private Product $guitarProduct;

    private Product $capoProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);

        $this->currency = Currency::factory()->primary()->create();
        $this->brand = Brand::factory()->create();
        $this->store = Store::factory()->for($this->currency, 'currency')->create([
            'slug' => 'panda-estampados',
        ]);
        StoreDomain::query()->create(attributes: [
            'store_id' => $this->store->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->guitars = Category::factory()->create(attributes: [
            'name' => 'Guitarras',
            'slug' => 'guitars',
        ]);
        $this->capos = Category::factory()->create(attributes: [
            'name' => 'Capos',
            'slug' => 'capos',
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin@panda-estampados.com',
            'is_active' => true,
            'role_id' => Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail()->id,
        ]);
        $this->admin->stores()->syncWithoutDetaching(ids: [$this->store->id]);

        $this->guitarProduct = $this->makeProduct(category: $this->guitars, slug: 'les-paul-standard');
        $this->capoProduct = $this->makeProduct(category: $this->capos, slug: 'capo-maestro');

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);
    }

    /**
     * Helper: producto publicado y visible en la tienda del admin.
     */
    private function makeProduct(Category $category, string $slug): Product
    {
        $product = Product::factory()->create(attributes: [
            'category_id' => $category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'slug' => $slug,
            'name' => ucfirst($slug),
            'is_active' => true,
            'published_at' => now(),
        ]);

        ProductStore::query()->create(attributes: [
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'is_visible' => true,
        ]);

        return $product;
    }

    /**
     * Body mínimo válido para una variante de categoría finishable.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function guitarVariantBody(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Iced Tea Burst',
            'sku' => 'LP-ITB-01',
        ], $overrides);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function validHexProvider(): array
    {
        return [
            'rgb' => ['#B91C1C'],
            'rgb en minúscula' => ['#b91c1c'],
            'rgba' => ['#B91C1C80'],
            'rgba opaco' => ['#FFFFFF00'],
        ];
    }

    /**
     * Formatos malformados.
     *
     * El string vacío NO está en la lista a propósito: el middleware
     * global `ConvertEmptyStringsToNull` lo convierte en `null` antes de
     * validar, y `null` es un valor legítimo (la variante queda sin
     * color). El comportamiento del vacío se cubre en
     * `store_allows_a_missing_finish_slug_when_the_category_is_not_finishable`.
     *
     * @return array<string, list<string>>
     */
    public static function malformedHexProvider(): array
    {
        return [
            'sin hash' => ['B91C1C'],
            'hash suelto' => ['#B91C1'],
            'tres dígitos de alpha' => ['#B91C1C8'],
            'letras no hex' => ['#GGGGGG'],
            'hash al final' => ['B91C1C#'],
            'html' => ['<span>#B91C1C</span>'],
            'rgb sin_alpha' => ['rgb(185,28,28)'],
        ];
    }

    #[Test]
    public function store_round_trips_every_rich_field(): void
    {
        $response = $this->postJson(uri: self::ADMIN_BASE, data: $this->guitarVariantBody([
            'product_id' => $this->guitarProduct->id,
            'finish_slug' => 'iced-tea-burst',
            'finish_label' => 'Iced Tea Burst',
            'finish_hex' => '#B91C1C80',
            'condition' => 'usado',
            'stock' => 7,
            'handedness' => 'left-handed',
        ]));

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.finish_slug', expect: 'iced-tea-burst');
        $response->assertJsonPath(path: 'data.finish_label', expect: 'Iced Tea Burst');
        $response->assertJsonPath(path: 'data.finish_hex', expect: '#B91C1C80');
        $response->assertJsonPath(path: 'data.condition', expect: 'usado');
        $response->assertJsonPath(path: 'data.stock', expect: 7);
        $response->assertJsonPath(path: 'data.handedness', expect: 'left-handed');

        $this->assertDatabaseHas(table: 'product_variants', data: [
            'product_id' => $this->guitarProduct->id,
            'sku' => 'LP-ITB-01',
            'finish_slug' => 'iced-tea-burst',
            'finish_label' => 'Iced Tea Burst',
            'finish_hex' => '#B91C1C80',
            'condition' => 'usado',
            'stock' => 7,
            'handedness' => 'left-handed',
        ]);
    }

    #[Test]
    public function store_defaults_condition_to_nuevo_when_it_is_omitted(): void
    {
        $response = $this->postJson(uri: self::ADMIN_BASE, data: $this->guitarVariantBody([
            'product_id' => $this->guitarProduct->id,
            'finish_slug' => 'ebony',
        ]));

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.condition', expect: 'nuevo');
        $response->assertJsonPath(path: 'data.stock', expect: 0);
        $response->assertJsonPath(path: 'data.handedness', expect: null);
        $response->assertJsonPath(path: 'data.finish_hex', expect: null);
    }

    #[Test]
    #[DataProvider('validHexProvider')]
    public function store_accepts_the_documented_hex_formats(string $hex): void
    {
        $response = $this->postJson(uri: self::ADMIN_BASE, data: $this->guitarVariantBody([
            'product_id' => $this->guitarProduct->id,
            'finish_slug' => 'sunburst',
            'finish_hex' => $hex,
        ]));

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.finish_hex', expect: $hex);
    }

    #[Test]
    #[DataProvider('malformedHexProvider')]
    public function store_rejects_malformed_hex(string $hex): void
    {
        $response = $this->postJson(uri: self::ADMIN_BASE, data: $this->guitarVariantBody([
            'product_id' => $this->guitarProduct->id,
            'finish_slug' => 'sunburst',
            'finish_hex' => $hex,
        ]));

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['finish_hex']);
        $this->assertDatabaseMissing(table: 'product_variants', data: ['sku' => 'LP-ITB-01']);
    }

    #[Test]
    public function store_rejects_a_finish_slug_that_is_not_kebab_case(): void
    {
        $response = $this->postJson(uri: self::ADMIN_BASE, data: $this->guitarVariantBody([
            'product_id' => $this->guitarProduct->id,
            'finish_slug' => 'Iced Tea Burst',
        ]));

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['finish_slug']);
    }

    #[Test]
    public function store_rejects_a_finish_slug_already_used_by_a_sibling_variant(): void
    {
        ProductVariant::factory()->create(attributes: [
            'product_id' => $this->guitarProduct->id,
            'sku' => 'LP-EBONY-01',
            'finish_slug' => 'ebony',
        ]);

        $response = $this->postJson(uri: self::ADMIN_BASE, data: $this->guitarVariantBody([
            'product_id' => $this->guitarProduct->id,
            'finish_slug' => 'ebony',
        ]));

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['finish_slug']);
    }

    #[Test]
    public function store_allows_the_same_finish_slug_on_a_different_product(): void
    {
        // El alcance real de la unicidad es POR PRODUCTO, no global: los
        // índices de `product_variants` no imponen unicidad sobre
        // `finish_slug` y dos productos distintos comparten acabados
        // legítimamente (una SG y una LP pueden ser las dos `ebony`).
        ProductVariant::factory()->create(attributes: [
            'product_id' => $this->guitarProduct->id,
            'sku' => 'LP-EBONY-01',
            'finish_slug' => 'ebony',
        ]);

        $response = $this->postJson(uri: self::ADMIN_BASE, data: [
            'product_id' => $this->capoProduct->id,
            'name' => 'Negro',
            'sku' => 'CAPO-NEGRO-01',
            'finish_slug' => 'ebony',
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.finish_slug', expect: 'ebony');
    }

    #[Test]
    public function store_requires_a_finish_slug_when_the_category_is_finishable(): void
    {
        $response = $this->postJson(uri: self::ADMIN_BASE, data: $this->guitarVariantBody([
            'product_id' => $this->guitarProduct->id,
        ]));

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['finish_slug']);
        $this->assertDatabaseMissing(table: 'product_variants', data: ['sku' => 'LP-ITB-01']);
    }

    #[Test]
    public function store_rejects_an_empty_finish_slug_when_the_category_is_finishable(): void
    {
        $response = $this->postJson(uri: self::ADMIN_BASE, data: $this->guitarVariantBody([
            'product_id' => $this->guitarProduct->id,
            'finish_slug' => '',
        ]));

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['finish_slug']);
    }

    #[Test]
    public function store_allows_a_missing_finish_slug_when_the_category_is_not_finishable(): void
    {
        $response = $this->postJson(uri: self::ADMIN_BASE, data: [
            'product_id' => $this->capoProduct->id,
            'name' => 'Maestro Negro',
            'sku' => 'CAPO-NEGRO-02',
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.finish_slug', expect: null);
        $response->assertJsonPath(path: 'data.condition', expect: 'nuevo');
    }

    #[Test]
    public function store_accepts_every_documented_condition(): void
    {
        foreach (['nuevo', 'usado', 'ambos'] as $condition) {
            $response = $this->postJson(uri: self::ADMIN_BASE, data: $this->guitarVariantBody([
                'product_id' => $this->guitarProduct->id,
                'sku' => 'LP-'.strtoupper($condition).'-01',
                'finish_slug' => 'finish-'.$condition,
                'condition' => $condition,
            ]));

            $response->assertCreated();
            $response->assertJsonPath(path: 'data.condition', expect: $condition);
        }
    }

    #[Test]
    public function store_rejects_a_condition_outside_the_domain_vocabulary(): void
    {
        $response = $this->postJson(uri: self::ADMIN_BASE, data: $this->guitarVariantBody([
            'product_id' => $this->guitarProduct->id,
            'finish_slug' => 'sunburst',
            'condition' => 'seminuevo',
        ]));

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['condition']);
        $this->assertDatabaseMissing(table: 'product_variants', data: ['sku' => 'LP-ITB-01']);
    }

    #[Test]
    public function store_rejects_a_handedness_outside_the_domain_vocabulary(): void
    {
        $response = $this->postJson(uri: self::ADMIN_BASE, data: $this->guitarVariantBody([
            'product_id' => $this->guitarProduct->id,
            'finish_slug' => 'sunburst',
            'handedness' => 'right-handed',
        ]));

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['handedness']);
    }

    #[Test]
    public function store_accepts_null_handedness_as_the_right_handed_convention(): void
    {
        $response = $this->postJson(uri: self::ADMIN_BASE, data: $this->guitarVariantBody([
            'product_id' => $this->guitarProduct->id,
            'finish_slug' => 'sunburst',
            'handedness' => null,
        ]));

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.handedness', expect: null);
    }

    #[Test]
    public function store_rejects_negative_stock(): void
    {
        $response = $this->postJson(uri: self::ADMIN_BASE, data: $this->guitarVariantBody([
            'product_id' => $this->guitarProduct->id,
            'finish_slug' => 'sunburst',
            'stock' => -1,
        ]));

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['stock']);
    }

    #[Test]
    public function store_rejects_non_integer_stock(): void
    {
        $response = $this->postJson(uri: self::ADMIN_BASE, data: $this->guitarVariantBody([
            'product_id' => $this->guitarProduct->id,
            'finish_slug' => 'sunburst',
            'stock' => 'muchos',
        ]));

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['stock']);
    }

    #[Test]
    public function update_round_trips_every_rich_field(): void
    {
        $variant = ProductVariant::factory()->create(attributes: [
            'product_id' => $this->guitarProduct->id,
            'sku' => 'LP-ORIG-01',
            'finish_slug' => 'cherry',
        ]);

        $response = $this->putJson(uri: self::ADMIN_BASE.'/'.$variant->id, data: [
            'finish_slug' => 'heritage-cherry-sunburst',
            'finish_label' => 'Heritage Cherry Sunburst',
            'finish_hex' => '#7A1F1F',
            'condition' => 'ambos',
            'stock' => 12,
            'handedness' => 'left-handed',
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.finish_slug', expect: 'heritage-cherry-sunburst');
        $response->assertJsonPath(path: 'data.finish_label', expect: 'Heritage Cherry Sunburst');
        $response->assertJsonPath(path: 'data.finish_hex', expect: '#7A1F1F');
        $response->assertJsonPath(path: 'data.condition', expect: 'ambos');
        $response->assertJsonPath(path: 'data.stock', expect: 12);
        $response->assertJsonPath(path: 'data.handedness', expect: 'left-handed');

        $this->assertDatabaseHas(table: 'product_variants', data: [
            'id' => $variant->id,
            'finish_slug' => 'heritage-cherry-sunburst',
            'finish_label' => 'Heritage Cherry Sunburst',
            'finish_hex' => '#7A1F1F',
            'condition' => 'ambos',
            'stock' => 12,
            'handedness' => 'left-handed',
        ]);
    }

    #[Test]
    public function update_does_not_wipe_the_fields_it_does_not_receive(): void
    {
        $variant = ProductVariant::factory()->create(attributes: [
            'product_id' => $this->guitarProduct->id,
            'sku' => 'LP-KEEP-01',
            'finish_slug' => 'wild-ivy',
            'finish_label' => 'Wild Ivy',
            'finish_hex' => '#4C6B4C',
            'condition' => 'usado',
            'stock' => 3,
            'handedness' => 'left-handed',
        ]);

        $response = $this->putJson(uri: self::ADMIN_BASE.'/'.$variant->id, data: [
            'finish_label' => 'Wild Ivy Metallic',
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.finish_label', expect: 'Wild Ivy Metallic');

        $variant->refresh();
        $this->assertSame(expected: 'wild-ivy', actual: $variant->finish_slug);
        $this->assertSame(expected: '#4C6B4C', actual: $variant->finish_hex);
        $this->assertSame(expected: 'usado', actual: $variant->condition);
        $this->assertSame(expected: 3, actual: $variant->stock);
        $this->assertSame(expected: 'left-handed', actual: $variant->handedness);
    }

    #[Test]
    public function update_leaves_the_finish_alone_when_the_body_does_not_mention_it(): void
    {
        $variant = ProductVariant::factory()->create(attributes: [
            'product_id' => $this->guitarProduct->id,
            'sku' => 'LP-PARTIAL-01',
            'finish_slug' => 'polaris-white',
        ]);

        $response = $this->putJson(uri: self::ADMIN_BASE.'/'.$variant->id, data: [
            'price_cents' => 99_900,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.finish_slug', expect: 'polaris-white');
    }

    #[Test]
    public function update_refuses_to_clear_the_finish_of_a_finishable_variant(): void
    {
        $variant = ProductVariant::factory()->create(attributes: [
            'product_id' => $this->guitarProduct->id,
            'sku' => 'LP-NOCLEAR-01',
            'finish_slug' => 'classic-white',
        ]);

        $response = $this->putJson(uri: self::ADMIN_BASE.'/'.$variant->id, data: [
            'finish_slug' => null,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['finish_slug']);

        $variant->refresh();
        $this->assertSame(expected: 'classic-white', actual: $variant->finish_slug);
    }

    #[Test]
    public function update_allows_clearing_the_finish_of_a_non_finishable_variant(): void
    {
        $variant = ProductVariant::factory()->create(attributes: [
            'product_id' => $this->capoProduct->id,
            'sku' => 'CAPO-CLEAR-01',
            'finish_slug' => 'natural',
        ]);

        $response = $this->putJson(uri: self::ADMIN_BASE.'/'.$variant->id, data: [
            'finish_slug' => null,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.finish_slug', expect: null);

        $variant->refresh();
        $this->assertNull($variant->finish_slug);
    }

    #[Test]
    public function update_ignores_its_own_row_in_the_finish_uniqueness_check(): void
    {
        $variant = ProductVariant::factory()->create(attributes: [
            'product_id' => $this->guitarProduct->id,
            'sku' => 'LP-SELF-01',
            'finish_slug' => 'transparent-purple',
        ]);

        $response = $this->putJson(uri: self::ADMIN_BASE.'/'.$variant->id, data: [
            'finish_label' => 'Transparent Purple',
            'finish_slug' => 'transparent-purple',
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.finish_slug', expect: 'transparent-purple');
    }

    #[Test]
    public function update_rejects_a_finish_slug_taken_by_a_sibling_variant(): void
    {
        ProductVariant::factory()->create(attributes: [
            'product_id' => $this->guitarProduct->id,
            'sku' => 'LP-TURQ-01',
            'finish_slug' => 'transparent-purple',
        ]);
        $variant = ProductVariant::factory()->create(attributes: [
            'product_id' => $this->guitarProduct->id,
            'sku' => 'LP-RED-01',
            'finish_slug' => 'radiant-red',
        ]);

        $response = $this->putJson(uri: self::ADMIN_BASE.'/'.$variant->id, data: [
            'finish_slug' => 'transparent-purple',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['finish_slug']);

        $variant->refresh();
        $this->assertSame(expected: 'radiant-red', actual: $variant->finish_slug);
    }

    #[Test]
    public function update_rejects_malformed_hex(): void
    {
        $variant = ProductVariant::factory()->create(attributes: [
            'product_id' => $this->guitarProduct->id,
            'sku' => 'LP-HEX-01',
            'finish_slug' => 'frost-blue',
        ]);

        $response = $this->putJson(uri: self::ADMIN_BASE.'/'.$variant->id, data: [
            'finish_hex' => '#12345',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['finish_hex']);
    }

    #[Test]
    public function show_exposes_the_rich_fields(): void
    {
        $variant = ProductVariant::factory()->create(attributes: [
            'product_id' => $this->guitarProduct->id,
            'sku' => 'LP-SHOW-01',
            'finish_slug' => 'aqua-mist',
            'finish_label' => 'Aqua Mist',
            'finish_hex' => '#9FD8D0',
            'condition' => 'nuevo',
            'stock' => 4,
            'handedness' => 'left-handed',
        ]);

        $response = $this->getJson(uri: self::ADMIN_BASE.'/'.$variant->id);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.finish_slug', expect: 'aqua-mist');
        $response->assertJsonPath(path: 'data.finish_label', expect: 'Aqua Mist');
        $response->assertJsonPath(path: 'data.finish_hex', expect: '#9FD8D0');
        $response->assertJsonPath(path: 'data.condition', expect: 'nuevo');
        $response->assertJsonPath(path: 'data.stock', expect: 4);
        $response->assertJsonPath(path: 'data.handedness', expect: 'left-handed');
    }
}
