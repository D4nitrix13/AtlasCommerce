<?php

declare(strict_types=1);

namespace Tests\Feature\Stores;

use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del sistema de feature flags por tenant.
 *
 * Verifica que el helper `Store::hasFeature(slug)` consulta
 * correctamente la tabla `store_features`, que la combinación
 * (store_id, slug) es única, y que la relación funciona.
 */
#[Group('stores')]
#[Group('features')]
final class StoreFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $store;

    /**
     * Crear tienda base antes de cada test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(attributes: ['name' => 'Test Store', 'slug' => 'test-store']);
    }

    #[Test]
    public function has_feature_returns_false_when_no_features_exist(): void
    {
        $this->assertFalse($this->store->hasFeature('wholesale_pricing'));
    }

    #[Test]
    public function has_feature_returns_true_when_feature_is_active(): void
    {
        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
            'is_active' => true,
        ]);

        $this->assertTrue($this->store->hasFeature('wholesale_pricing'));
    }

    #[Test]
    public function has_feature_returns_false_when_feature_exists_but_is_inactive(): void
    {
        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
            'is_active' => false,
        ]);

        $this->assertFalse($this->store->hasFeature('wholesale_pricing'));
    }

    #[Test]
    public function features_relation_returns_all_features_of_a_store(): void
    {
        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
            'is_active' => true,
        ]);
        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'bulk_import',
            'is_active' => true,
        ]);
        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'instagram_shop',
            'is_active' => false,
        ]);

        $features = $this->store->features;

        $this->assertCount(3, $features);
        $this->assertTrue($features->where('slug', 'wholesale_pricing')->isNotEmpty());
        $this->assertTrue($features->where('slug', 'bulk_import')->isNotEmpty());
        $this->assertTrue($features->where('slug', 'instagram_shop')->isNotEmpty());
    }

    #[Test]
    public function feature_flags_are_isolated_per_store(): void
    {
        $otherStore = Store::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(attributes: ['name' => 'Other Store', 'slug' => 'other-store']);

        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
            'is_active' => true,
        ]);

        // $otherStore no tiene la feature.
        $this->assertTrue($this->store->hasFeature('wholesale_pricing'));
        $this->assertFalse($otherStore->hasFeature('wholesale_pricing'));
    }

    #[Test]
    public function feature_slug_must_be_unique_per_store(): void
    {
        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
            'is_active' => true,
        ]);

        // Intentar crear el mismo (store, slug) debe fallar.
        $this->expectException(\Illuminate\Database\QueryException::class);

        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
            'is_active' => false,
        ]);
    }

    #[Test]
    public function same_slug_can_exist_in_different_stores(): void
    {
        $otherStore = Store::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(attributes: ['name' => 'Other Store', 'slug' => 'other-store']);

        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
            'is_active' => true,
        ]);
        // Mismo slug, otra tienda: OK.
        StoreFeature::query()->create(attributes: [
            'store_id' => $otherStore->id,
            'slug' => 'wholesale_pricing',
            'is_active' => true,
        ]);

        $this->assertTrue($this->store->hasFeature('wholesale_pricing'));
        $this->assertTrue($otherStore->hasFeature('wholesale_pricing'));
    }

    #[Test]
    public function deleting_store_cascades_to_features(): void
    {
        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas(table: 'store_features', data: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
        ]);

        $this->store->forceDelete();

        $this->assertDatabaseMissing('store_features', [
            'store_id' => $this->store->id,
        ]);
    }
}
