<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Stores\StoreFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('plugins')]
#[Group('erp')]
final class ErpPluginFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::factory()->primary()->create();
    }

    #[Test]
    public function endpoint_is_available_when_erp_is_enabled_for_current_store(): void
    {
        $this->createStore(domain: 'erp-enabled.test', erpActive: true);

        $response = $this->getJson(uri: 'http://erp-enabled.test/api/erp/status');

        $response
            ->assertOk()
            ->assertJson([
                'plugin' => 'ERP integration',
                'feature' => 'erp',
                'status' => 'installed',
            ]);
    }

    #[Test]
    public function endpoint_is_hidden_when_erp_is_disabled_for_current_store(): void
    {
        $this->createStore(domain: 'erp-disabled.test', erpActive: false);

        $response = $this->getJson(uri: 'http://erp-disabled.test/api/erp/status');

        $response
            ->assertNotFound()
            ->assertJson([
                'message' => 'Feature not enabled for this store.',
            ]);
    }

    #[Test]
    public function endpoint_access_is_isolated_between_stores(): void
    {
        $this->createStore(domain: 'erp-enabled.test', erpActive: true);
        $this->createStore(domain: 'erp-disabled.test', erpActive: false);

        $this->getJson(uri: 'http://erp-enabled.test/api/erp/status')
            ->assertOk();
        $this->getJson(uri: 'http://erp-disabled.test/api/erp/status')
            ->assertNotFound();
    }

    private function createStore(string $domain, bool $erpActive): Store
    {
        $store = Store::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create([
                'name' => $domain,
                'slug' => str_replace(search: '.', replace: '-', subject: $domain),
            ]);

        StoreDomain::query()->create([
            'store_id' => $store->id,
            'domain' => $domain,
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        StoreFeature::query()->create([
            'store_id' => $store->id,
            'slug' => 'erp',
            'is_active' => $erpActive,
        ]);

        return $store;
    }
}
