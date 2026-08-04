<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use D4nitrix13\EcommercePluginErp\EcommercePluginErpServiceProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('plugins')]
#[Group('erp')]
final class ErpPluginDiscoveryTest extends TestCase
{
    #[Test]
    public function laravel_discovers_the_erp_provider_from_composer_metadata(): void
    {
        self::assertArrayHasKey(
            EcommercePluginErpServiceProvider::class,
            app()->getLoadedProviders(),
        );
        self::assertSame('erp', config('erp.feature'));
    }

    #[Test]
    public function discovered_provider_registers_the_erp_status_route(): void
    {
        $route = app('router')->getRoutes()->getByName('erp.status');

        self::assertNotNull($route);
        self::assertContains('store.domain', $route->middleware());
    }
}
