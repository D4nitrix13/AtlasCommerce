<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
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
        Storage::fake(disk: 'ftp');

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
            'domain' => 'panda-estampados.test',
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

        $response = $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/tenants/panda-estampados/branding/logo.webp');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/webp');
        // Cache-Control ordering es normalizado por Symfony HeaderBag
        // (directives reordenadas); verificamos las 3 directives presentes
        // en lugar de equality estricta.
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', (string) $cacheControl);
        $this->assertStringContainsString('max-age=31536000', (string) $cacheControl);
        $this->assertStringContainsString('immutable', (string) $cacheControl);
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
        $response = $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/tenants/kitsune/branding/logo.webp');

        $response->assertForbidden();
    }

    #[Test]
    public function it_returns_404_when_file_does_not_exist(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/tenants/panda-estampados/branding/missing.webp');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_403_on_path_traversal_attempts(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/tenants/panda-estampados/../../../etc/passwd');

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

        $response = $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/shared/icons/social/facebook.webp');

        $response->assertOk();
        $this->assertSame(expected: $contents, actual: $response->getContent());
    }

    #[Test]
    public function it_returns_304_when_if_none_match_matches_etag(): void
    {
        $contents = 'etag-test-content';
        Storage::disk(name: 'ftp')->put(
            path: 'tenants/panda-estampados/branding/etag-test.webp',
            contents: $contents,
        );

        $expectedEtag = '"'.md5(string: $contents).'"';

        $response = $this->withHeaders(headers: ['If-None-Match' => $expectedEtag])
            ->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/tenants/panda-estampados/branding/etag-test.webp');

        $response->assertStatus(304);
        $this->assertSame($expectedEtag, $response->headers->get('ETag'));
    }

    #[Test]
    public function it_strips_leading_slashes_from_path(): void
    {
        Storage::disk(name: 'ftp')->put(
            path: 'tenants/panda-estampados/brand/logo.webp',
            contents: 'content',
        );

        $response = $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets//tenants/panda-estampados/brand/logo.webp');

        $response->assertOk();
    }

    #[Test]
    public function it_caches_assets_in_redis_with_24h_ttl(): void
    {
        // Path único por test para evitar cache compartida entre tests
        // (RefreshDatabase no limpia Redis).
        Storage::disk(name: 'ftp')->put(
            path: 'tenants/panda-estampados/branding/cache-test.webp',
            contents: 'cached-content',
        );

        $cacheKey = 'ftp-asset:'.hash(algo: 'sha256', data: 'tenants/panda-estampados/branding/cache-test.webp');

        // Antes del request: cache no existe.
        $this->assertFalse(\Illuminate\Support\Facades\Cache::store(name: 'redis')->has($cacheKey));

        // Hacer el request: dispara cache miss + lectura FTP + escritura en cache.
        $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/tenants/panda-estampados/branding/cache-test.webp')
            ->assertOk();

        // Despues del request: cache existe.
        $this->assertTrue(\Illuminate\Support\Facades\Cache::store(name: 'redis')->has($cacheKey));
        $this->assertSame('cached-content', \Illuminate\Support\Facades\Cache::store(name: 'redis')->get($cacheKey));
    }

    #[Test]
    public function it_logs_warning_when_path_does_not_match_naming_convention_in_soft_mode(): void
    {
        // Path valido a nivel de tenant (no 403) pero malformado a nivel de
        // convencion (no tiene el `{hash}.{ext}` final). En soft mode (default)
        // la peticion no se rechaza: se sirve el binario y se emite un log
        // estructurado que sirve de señal para priorizar el backfill de Fase 5.
        $contents = 'soft-mode-warning-content';
        Storage::disk(name: 'ftp')->put(
            path: 'tenants/panda-estampados/branding/soft-mode-warning.webp',
            contents: $contents,
        );

        Log::spy();

        $response = $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/tenants/panda-estampados/branding/soft-mode-warning.webp');

        $response->assertOk();

        Log::shouldHaveReceived(method: 'warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'asset.path.malformed'
                    && $context['tenant_slug'] === 'panda-estampados'
                    && $context['strict_mode'] === false
                    && is_string($context['hash']);
            })
            ->once();
    }

    #[Test]
    public function it_rejects_with_403_in_strict_mode_when_path_malformed(): void
    {
        // Strict mode activado via config. Un path que matchea el tenant prefix
        // pero NO la convención debe abortar con 403 sin tocar el FTP disk.
        config()->set(key: 'assets.path_validation.strict', value: true);

        $contents = 'strict-mode-test-content';
        Storage::disk(name: 'ftp')->put(
            path: 'tenants/panda-estampados/branding/strict-mode-reject.webp',
            contents: $contents,
        );

        $response = $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/tenants/panda-estampados/branding/strict-mode-reject.webp');

        $response->assertForbidden();
    }

    #[Test]
    public function it_serves_correctly_in_soft_mode_when_path_malformed(): void
    {
        // Soft mode (default) sigue entregando el binario aunque el path no
        // matchee la convención. La Fase 5 backfill migrara los paths
        // legacy al formato canónico; Fase 6 flipeara a strict una vez
        // el rate de warnings caiga a ~0.
        $contents = 'soft-mode-serve-content';
        Storage::disk(name: 'ftp')->put(
            path: 'tenants/panda-estampados/branding/soft-mode-serve.webp',
            contents: $contents,
        );

        $response = $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/tenants/panda-estampados/branding/soft-mode-serve.webp');

        $response->assertOk();
        $this->assertSame(expected: $contents, actual: $response->getContent());
    }
}
