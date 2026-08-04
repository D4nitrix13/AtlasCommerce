<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Core\Tenant\Application\StoreContextService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('architecture')]
#[Group('core')]
final class CoreStructureTest extends TestCase
{
    private const MODULE_DIRECTORIES = [
        'app/Core/Auth',
        'app/Core/Tenant',
        'app/Core/Catalog',
        'app/Core/Cart',
        'app/Core/Checkout',
        'app/Core/Orders',
        'app/Core/Payments',
        'app/Core/Shipping',
        'app/Core/Shared',
        'app/Core/Shared/Events',
        'app/Core/Shared/Plugins',
    ];

    #[Test]
    public function core_namespace_is_psr4_autoloaded(): void
    {
        self::assertTrue(class_exists(StoreContextService::class));
    }

    #[Test]
    public function core_module_boundaries_exist(): void
    {
        foreach (self::MODULE_DIRECTORIES as $directory) {
            self::assertDirectoryExists(base_path($directory));
        }
    }

    #[Test]
    public function legacy_store_context_service_is_removed_in_v2(): void
    {
        self::assertFalse(
            class_exists('App\Services\Stores\StoreContextService'),
            'El wrapper legacy debe eliminarse en v2.0.0. Ver '
            .'docs/development/core-migration.md.',
        );
    }
}
