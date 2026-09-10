<?php

declare(strict_types=1);

namespace D4nitrix13\AtlasCommercePluginErp\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PackageMetadataTest extends TestCase
{
    public function test_package_declares_laravel_provider_discovery(): void
    {
        $package = json_decode(
            json: file_get_contents(__DIR__.'/../composer.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame('atlas-commerce-plugin', $package['type']);
        self::assertContains(
            'D4nitrix13\\AtlasCommercePluginErp\\AtlasCommercePluginErpServiceProvider',
            $package['extra']['laravel']['providers'],
        );
    }
}
