<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del endpoint público `GET /api/v1/storefront/assets/{path}`.
 *
 * Verifica que:
 *   - Sirve el archivo del disk `ftp` cuando el path es valido.
 *   - Devuelve 403 si el path no pertenece al tenant activo ni a shared/.
 *   - Devuelve 404 si el archivo no existe en el FTP.
 *   - Devuelve 403 si el path tiene '..' (path traversal).
 *   - Setea headers correctos (Content-Type, Cache-Control, ETag).
 *   - Soporta If-None-Match para 304 Not Modified.
 *   - Shared/ es accesible desde cualquier tenant.
 */
#[Group('storefront')]
#[Group('assets')]
final class StorefrontAssetTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake del disk `ftp` para no depender de un FTP real en tests.
        Storage::fake(name: 'ftp');

        $this->currency = Currency::query()->create(attributes: [
            'code' => 'NIO',
            'name' => 'Cordoba',
            'symbol' => 'C$',
            'is_active' => true,
            'decimal_places' => 2,
        ]);

        $this->store = Store::query()->create(attributes: [
            'currency_id' => $this->currency->id,
            'name' => 'Panda Estampados',
            'slug' => 'panda-estampados',
            'description' => 'Test store',
            'is_active' => true,
            'maintenance_mode' => false,
        ]);

        StoreDomain::query()->create(attributes: [
            'store_id' => $this->store->id,
            'host' => 'panda-estampados.test',
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_serves_an_asset_that_belongs_to_the_active_tenant(): void
    {
        $contents = 'fake-webp-binary-content';
        Storage::disk(name: 'ftp')->put(
            path: 'tenants/panda-estampados/branding/logo.webp',
            contents: $contents,
        );

        $response = $this->withServerVariables(serverVariables: [
            'HTTP_HOST' => 'panda-estampados.test',
        ])->get(uri: '/api/v1/storefront/assets/tenants/panda-estampados/branding/logo.webp');

        $response->assertOk();
        $response->assertHeader(name: 'Content-Type', value: 'image/webp');
        $response->assertHeader(name: 'Cache-Control', value: 'public, max-age=31536000, immutable');
        $this->assertNotEmpty(actual: $response->headers->get(key: 'ETag'));
        $this->assertSame(expected: $contents, actual: $response->getContent());
    }

    #[Test]
    public function it_returns_403_when_path_belongs_to_a_different_tenant(): void
    {
        // Subir un asset al tenant "kitsune".
        Storage::disk(name: 'ftp')->put(
            path: 'tenants/kitsune/branding/logo.webp',
            contents: 'kitsune-logo-content',
        );

        // Intentar acceder como panda-estampados.
        $response = $this->withServerVariables(serverVariables: [
            'HTTP_HOST' => 'panda-estampados.test',
        ])->get(uri: '/api/v1/storefront/assets/tenants/kitsune/branding/logo.webp');

        $response->assertForbidden();
    }

    #[Test]
    public function it_returns_404_when_file_does_not_exist(): void
    {
        $response = $this->withServerVariables(serverVariables: [
            'HTTP_HOST' => 'panda-estampados.test',
        ])->get(uri: '/api/v1/storefront/assets/tenants/panda-estampados/branding/missing.webp');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_403_on_path_traversal_attempts(): void
    {
        $response = $this->withServerVariables(serverVariables: [
            'HTTP_HOST' => 'panda-estampados.test',
        ])->get(uri: '/api/v1/storefront/assets/tenants/panda-estampados/../../../etc/passwd');

        $response->assertForbidden();
    }

    #[Test]
    public function shared_assets_are_accessible_from_any_tenant(): void
    {
        $contents = 'shared-icon-content';
        Storage::disk(name: 'ftp')->put(
            path: 'shared/icons/social/facebook.webp',
            contents: $contents,
        );

        $response = $this->withServerVariables(serverVariables: [
            'HTTP_HOST' => 'panda-estampados.test',
        ])->get(uri: '/api/v1/storefront/assets/shared/icons/social/facebook.webp');

        $response->assertOk();
        $this->assertSame(expected: $contents, actual: $response->getContent());
    }

    #[Test]
    public function it_returns_304_when_if_none_match_matches_etag(): void
    {
        $contents = 'cached-content';
        Storage::disk(name: 'ftp')->put(
            path: 'tenants/panda-estampados/branding/logo.webp',
            contents: $contents,
        );

        $expectedEtag = '"'.md5(string: $contents).'"';

        $response = $this->withServerVariables(serverVariables: [
            'HTTP_HOST' => 'panda-estampados.test',
            'HTTP_IF_NONE_MATCH' => $expectedEtag,
        ])->get(uri: '/api/v1/storefront/assets/tenants/panda-estampados/branding/logo.webp');

        $response->assertStatus(code: 304);
        $this->assertSame(expected: $expectedEtag, actual: $response->headers->get(key: 'ETag'));
    }

    #[Test]
    public function it_strips_leading_slashes_from_path(): void
    {
        Storage::disk(name: 'ftp')->put(
            path: 'tenants/panda-estampados/brand/logo.webp',
            contents: 'content',
        );

        $response = $this->withServerVariables(serverVariables: [
            'HTTP_HOST' => 'panda-estampados.test',
        ])->get(uri: '/api/v1/storefront/assets//tenants/panda-estampados/brand/logo.webp');

        $response->assertOk();
    }

    #[Test]
    public function it_caches_assets_in_redis_with_24h_ttl(): void
    {
        Storage::disk(name: 'ftp')->put(
            path: 'tenants/panda-estampados/branding/logo.webp',
            contents: 'cached-content',
        );

        $cacheKey = 'ftp-asset:'.hash(algo: 'sha256', data: 'tenants/panda-estampados/branding/logo.webp');

        // Antes del request: cache no existe.
        $this->assertFalse(condition: \Illuminate\Support\Facades\Cache::store(name: 'redis')->has(key: $cacheKey));

        // Hacer el request: dispara cache miss + lectura FTP + escritura en cache.
        $this->withServerVariables(serverVariables: [
            'HTTP_HOST' => 'panda-estampados.test',
        ])->get(uri: '/api/v1/storefront/assets/tenants/panda-estampados/branding/logo.webp')
            ->assertOk();

        // Despues del request: cache existe.
        $this->assertTrue(condition: \Illuminate\Support\Facades\Cache::store(name: 'redis')->has(key: $cacheKey));
        $this->assertSame(expected: 'cached-content', actual: \Illuminate\Support\Facades\Cache::store(name: 'redis')->get(key: $cacheKey));
    }
}
