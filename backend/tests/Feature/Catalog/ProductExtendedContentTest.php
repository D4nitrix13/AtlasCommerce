<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Http\Controllers\Api\Storefront\ProductController;
use App\Http\Resources\Catalog\ProductResource;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductExtendedDescription;
use App\Models\Catalog\ProductFaq;
use App\Models\Catalog\ProductGroupedSpec;
use App\Models\Catalog\ProductHighlight;
use App\Models\Catalog\ProductSpecValue;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del contenido extendido de Fase 8 (highlights, groupedSpecs,
 * faqs, extendedDescriptions) y su exposición en el product detail
 * del storefront.
 *
 * Cubre:
 *  - Persistencia básica de los 4 modelos.
 *  - Relaciones en `Product` (hasMany ordered by position).
 *  - Cascade delete: borrar el producto borra los recursos anidados.
 *  - Shape de `ProductResource` y del endpoint `/api/v1/storefront/products/{slug}`.
 *  - Position ordering preservado en la respuesta.
 */
#[Group('catalog')]
#[Group('phase8')]
final class ProductExtendedContentTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()
            ->for($currency, 'currency')
            ->create(['slug' => 'la-voz']);

        StoreDomain::query()->create(attributes: [
            'store_id' => $this->store->id,
            'domain' => 'la-voz.test',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'currency_id' => $currency->id,
        ]);

        ProductStore::query()->create(attributes: [
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'is_visible' => true,
        ]);
    }

    #[Test]
    public function it_persists_highlights_in_order(): void
    {
        ProductHighlight::query()->create(attributes: [
            'product_id' => $this->product->id,
            'icon' => 'premio',
            'label' => 'Cuerpo de caoba',
            'position' => 0,
        ]);
        ProductHighlight::query()->create(attributes: [
            'product_id' => $this->product->id,
            'icon' => 'destello',
            'label' => 'Tapa de arce',
            'position' => 1,
        ]);

        $labels = $this->product->highlights()->pluck(column: 'label')->all();

        $this->assertSame(expected: ['Cuerpo de caoba', 'Tapa de arce'], actual: $labels);
    }

    #[Test]
    public function it_persists_grouped_specs_with_values_in_order(): void
    {
        $body = ProductGroupedSpec::query()->create(attributes: [
            'product_id' => $this->product->id,
            'category' => 'Cuerpo',
            'position' => 0,
        ]);
        ProductSpecValue::query()->create(attributes: [
            'grouped_spec_id' => $body->id,
            'label' => 'Madera',
            'value' => 'Caoba',
            'position' => 0,
        ]);
        ProductSpecValue::query()->create(attributes: [
            'grouped_spec_id' => $body->id,
            'label' => 'Tapa',
            'value' => 'Arce AAA',
            'position' => 1,
        ]);

        $firstGroup = $this->product->groupedSpecs()->first();
        $this->assertNotNull($firstGroup);
        $this->assertSame(expected: 'Cuerpo', actual: $firstGroup->category);

        $values = $firstGroup->values()->pluck(column: 'label')->all();
        $this->assertSame(expected: ['Madera', 'Tapa'], actual: $values);
    }

    #[Test]
    public function it_persists_faqs_in_order(): void
    {
        ProductFaq::query()->create(attributes: [
            'product_id' => $this->product->id,
            'question' => '¿Pregunta 1?',
            'answer' => 'Respuesta 1',
            'position' => 0,
        ]);
        ProductFaq::query()->create(attributes: [
            'product_id' => $this->product->id,
            'question' => '¿Pregunta 2?',
            'answer' => 'Respuesta 2',
            'position' => 1,
        ]);

        $questions = $this->product->faqs()->pluck(column: 'question')->all();

        $this->assertSame(expected: ['¿Pregunta 1?', '¿Pregunta 2?'], actual: $questions);
    }

    #[Test]
    public function it_persists_extended_descriptions_in_order(): void
    {
        ProductExtendedDescription::query()->create(attributes: [
            'product_id' => $this->product->id,
            'title' => 'The Les Paul, llevada al extremo',
            'body' => 'Gibson se enorgullece...',
            'position' => 0,
        ]);

        $entry = $this->product->extendedDescriptions()->first();
        $this->assertNotNull($entry);
        $this->assertSame(expected: 'The Les Paul, llevada al extremo', actual: $entry->title);
    }

    #[Test]
    public function cascade_delete_removes_all_extended_content(): void
    {
        ProductHighlight::query()->create(attributes: [
            'product_id' => $this->product->id,
            'icon' => 'premio',
            'label' => 'Cuerpo de caoba',
        ]);
        ProductFaq::query()->create(attributes: [
            'product_id' => $this->product->id,
            'question' => '¿Pregunta?',
            'answer' => 'Respuesta',
        ]);
        $group = ProductGroupedSpec::query()->create(attributes: [
            'product_id' => $this->product->id,
            'category' => 'Cuerpo',
        ]);
        ProductSpecValue::query()->create(attributes: [
            'grouped_spec_id' => $group->id,
            'label' => 'Madera',
            'value' => 'Caoba',
        ]);
        ProductExtendedDescription::query()->create(attributes: [
            'product_id' => $this->product->id,
            'title' => 'Bloque',
            'body' => 'Cuerpo',
        ]);

        $productId = $this->product->id;
        $groupId = $group->id;

        // FK product_stores.product_id es RESTRICT (no CASCADE), así
        // que primero hay que sacar el row de product_stores antes de
        // poder borrar el product. Una vez removido, forceDelete
        // dispara el cascade real del FK de Fase 8.
        ProductStore::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->delete();
        $this->product->forceDelete();

        $this->assertDatabaseMissing(table: 'product_highlights', data: ['product_id' => $productId]);
        $this->assertDatabaseMissing(table: 'product_faqs', data: ['product_id' => $productId]);
        $this->assertDatabaseMissing(table: 'product_extended_descriptions', data: ['product_id' => $productId]);
        $this->assertDatabaseMissing(table: 'product_grouped_specs', data: ['product_id' => $productId]);
        $this->assertDatabaseMissing(table: 'product_spec_values', data: ['grouped_spec_id' => $groupId]);
    }

    #[Test]
    public function product_resource_exposes_extended_content_when_relations_loaded(): void
    {
        ProductHighlight::query()->create(attributes: [
            'product_id' => $this->product->id,
            'icon' => 'premio',
            'label' => 'Cuerpo de caoba',
            'position' => 0,
        ]);
        $group = ProductGroupedSpec::query()->create(attributes: [
            'product_id' => $this->product->id,
            'category' => 'Cuerpo',
            'position' => 0,
        ]);
        ProductSpecValue::query()->create(attributes: [
            'grouped_spec_id' => $group->id,
            'label' => 'Madera',
            'value' => 'Caoba',
            'position' => 0,
        ]);

        $loaded = Product::query()
            ->with(relations: [
                'highlights',
                'groupedSpecs.values',
                'faqs',
                'extendedDescriptions',
            ])
            ->findOrFail(id: $this->product->id);

        /** @var array<string, mixed> $payload */
        $payload = (new ProductResource(resource: $loaded))->toArray(request: Request::create(uri: '/'));

        $this->assertArrayHasKey(key: 'highlights', array: $payload);
        $this->assertSame(
            expected: [['icon' => 'premio', 'label' => 'Cuerpo de caoba']],
            actual: $payload['highlights'],
        );

        $this->assertArrayHasKey(key: 'groupedSpecs', array: $payload);
        $this->assertSame(
            expected: [['category' => 'Cuerpo', 'items' => [['label' => 'Madera', 'value' => 'Caoba']]]],
            actual: $payload['groupedSpecs'],
        );

        $this->assertArrayHasKey(key: 'faqs', array: $payload);
        $this->assertSame(expected: [], actual: $payload['faqs']);

        $this->assertArrayHasKey(key: 'extendedDescriptions', array: $payload);
        $this->assertSame(expected: [], actual: $payload['extendedDescriptions']);
    }

    #[Test]
    public function product_resource_omits_extended_content_when_relations_not_loaded(): void
    {
        /** @var array<string, mixed> $payload */
        $payload = (new ProductResource(resource: $this->product))->toArray(request: Request::create(uri: '/'));

        $this->assertArrayNotHasKey(key: 'highlights', array: $payload);
        $this->assertArrayNotHasKey(key: 'groupedSpecs', array: $payload);
        $this->assertArrayNotHasKey(key: 'faqs', array: $payload);
        $this->assertArrayNotHasKey(key: 'extendedDescriptions', array: $payload);
    }

    #[Test]
    public function storefront_show_endpoint_returns_extended_content(): void
    {
        ProductHighlight::query()->create(attributes: [
            'product_id' => $this->product->id,
            'icon' => 'premio',
            'label' => 'Cuerpo de caoba',
            'position' => 0,
        ]);
        ProductHighlight::query()->create(attributes: [
            'product_id' => $this->product->id,
            'icon' => 'destello',
            'label' => 'Tapa de arce',
            'position' => 1,
        ]);
        ProductFaq::query()->create(attributes: [
            'product_id' => $this->product->id,
            'question' => '¿Pregunta?',
            'answer' => 'Respuesta',
            'position' => 0,
        ]);
        ProductExtendedDescription::query()->create(attributes: [
            'product_id' => $this->product->id,
            'title' => 'The Les Paul',
            'body' => 'Gibson...',
            'position' => 0,
        ]);

        $request = Request::create(uri: '/api/v1/storefront/products/'.$this->product->slug);
        $request->attributes->set(key: 'store', value: $this->store);

        $response = (new ProductController)->show($request, $this->product->slug);

        $this->assertSame(expected: 200, actual: $response->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = $response->getData(true);

        $this->assertArrayHasKey(key: 'highlights', array: $payload);
        $this->assertArrayHasKey(key: 'groupedSpecs', array: $payload);
        $this->assertArrayHasKey(key: 'faqs', array: $payload);
        $this->assertArrayHasKey(key: 'extendedDescriptions', array: $payload);

        $highlightLabels = array_map(
            callback: fn (array $entry): string => (string) $entry['label'],
            array: $payload['highlights'],
        );
        $this->assertSame(
            expected: ['Cuerpo de caoba', 'Tapa de arce'],
            actual: $highlightLabels,
            message: 'Highlights deben venir ordenadas por position',
        );

        $this->assertCount(expectedCount: 1, haystack: $payload['faqs']);
        $this->assertSame(expected: '¿Pregunta?', actual: $payload['faqs'][0]['question']);
        $this->assertCount(expectedCount: 1, haystack: $payload['extendedDescriptions']);
        $this->assertSame(expected: 'The Les Paul', actual: $payload['extendedDescriptions'][0]['title']);
    }

    #[Test]
    public function storefront_show_endpoint_returns_empty_arrays_when_product_has_no_extended_content(): void
    {
        $request = Request::create(uri: '/api/v1/storefront/products/'.$this->product->slug);
        $request->attributes->set(key: 'store', value: $this->store);

        $response = (new ProductController)->show($request, $this->product->slug);

        /** @var array<string, mixed> $payload */
        $payload = $response->getData(true);

        $this->assertSame(expected: [], actual: $payload['highlights']);
        $this->assertSame(expected: [], actual: $payload['groupedSpecs']);
        $this->assertSame(expected: [], actual: $payload['faqs']);
        $this->assertSame(expected: [], actual: $payload['extendedDescriptions']);
    }
}
