<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\RecordingTtlStore;
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

        // El `AssetController` resuelve el disk vía `config('assets.disk')`,
        // no hardcodea `'ftp'`. En dev `ASSETS_DISK=local` (porque el
        // seeder puebla el disco local y el FTP queda vacío), entonces
        // sin este override el controller leería de `local` (que NO está
        // faked) y devolvería 404 en todos los asserts.
        //
        // Fijar el config explícitamente al disk faked es la única forma
        // de que el controller y el test hablen del mismo filesystem.
        // Patrón análogo al que usan los tests de Fase 3 (cache_store,
        // cache_ttl) — el helper del controller ya está separado en el
        // `Config` facade, solo faltaba este override.
        config()->set(key: 'assets.disk', value: 'ftp');

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
    public function it_caches_assets_with_configured_store_and_ttl(): void
    {
        // Fase 3: T3 — el cache store y TTL vienen de config en lugar de
        // hard-coded. El store configurado en la suite es `array`
        // (`phpunit.xml`), o sea un store en memoria que Laravel
        // reconstruye por test: el "antes del request no hay nada" es
        // una propiedad del diseño, no una casualidad de que la key no
        // haya quedado de una corrida anterior.
        $path = 'tenants/panda-estampados/branding/cache-test.webp';

        Storage::disk(name: 'ftp')->put(
            path: $path,
            contents: 'cached-content',
        );

        $cacheStoreName = (string) config(key: 'assets.cache_store');
        $cacheTtl = (int) config(key: 'assets.cache_ttl');
        $recorder = $this->recordTtlOf(name: $cacheStoreName);
        $cacheKey = 'ftp-asset:'.hash(algo: 'sha256', data: $path);

        // Antes del request: cache no existe en el store configurado.
        $this->assertFalse(Cache::store(name: $cacheStoreName)->has($cacheKey));

        // Hacer el request: dispara cache miss + lectura FTP + escritura en cache.
        $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/'.$path)
            ->assertOk();

        // Despues del request: cache existe y respeta el TTL configurado.
        $this->assertTrue(Cache::store(name: $cacheStoreName)->has($cacheKey));
        $this->assertSame(
            expected: 'cached-content',
            actual: Cache::store(name: $cacheStoreName)->get($cacheKey),
        );

        // El TTL va exacto, sin ventana de drift: se lee lo que el
        // controller le pasó al store, no lo que quedó en un reloj
        // externo degradándose con el paso del tiempo.
        $this->assertSame(expected: $cacheTtl, actual: $recorder->ttlFor($cacheKey));
    }

    #[Test]
    public function it_uses_configured_cache_store(): void
    {
        // Cambiar `assets.cache_store` debe cambiar el store usado. El
        // probe es un store en memoria propio de este test: prueba que
        // el controller escribe donde dice la config y NO donde dice por
        // defecto. Con `array` como destino el assert sería vacío, porque
        // `array` es justamente el default de la suite.
        $probe = $this->registerRecordingCacheStore(name: 'assets-probe');

        config()->set(key: 'assets.cache_store', value: 'assets-probe');
        config()->set(key: 'assets.cache_ttl', value: 300);

        $path = 'tenants/panda-estampados/branding/cache-store-config-test.webp';

        Storage::disk(name: 'ftp')->put(
            path: $path,
            contents: 'array-store-content',
        );

        $cacheKey = 'ftp-asset:'.hash(algo: 'sha256', data: $path);

        // Antes del request: nada en el probe.
        $this->assertFalse(Cache::store(name: 'assets-probe')->has($cacheKey));

        $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/'.$path)
            ->assertOk();

        // Después del request: presente en el store configurado.
        $this->assertTrue(Cache::store(name: 'assets-probe')->has($cacheKey));
        $this->assertSame(
            expected: 'array-store-content',
            actual: Cache::store(name: 'assets-probe')->get($cacheKey),
        );

        // Y ausente del store por defecto de la suite: la config manda.
        $this->assertFalse(Cache::store(name: (string) config(key: 'cache.default'))->has($cacheKey));
    }

    #[Test]
    public function it_uses_configured_cache_ttl(): void
    {
        // Cambiar `assets.cache_ttl` debe cambiar el TTL que se aplica.
        // El probe registra el TTL en el `put()` que dispara el
        // `remember()` del controller, así que el assert mide el
        // comportamiento del controller y no el estado de Redis.
        $customTtl = 86_400;
        $probe = $this->registerRecordingCacheStore(name: 'assets-ttl-probe');

        config()->set(key: 'assets.cache_store', value: 'assets-ttl-probe');
        config()->set(key: 'assets.cache_ttl', value: $customTtl);

        $path = 'tenants/panda-estampados/branding/cache-ttl-config-test.webp';

        Storage::disk(name: 'ftp')->put(
            path: $path,
            contents: 'ttl-config-content',
        );

        $cacheKey = 'ftp-asset:'.hash(algo: 'sha256', data: $path);

        $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/'.$path)
            ->assertOk();

        $this->assertSame(expected: $customTtl, actual: $probe->ttlFor($cacheKey));
    }

    /**
     * Reemplaza el store subyacente del cache store `name` por un double
     * que registra el TTL de cada escritura, y lo devuelve.
     *
     * `Cache::store()` devuelve el mismo repository en todo el test, así
     * que el double es el mismo objeto que usa el controller.
     */
    private function recordTtlOf(string $name): RecordingTtlStore
    {
        $recorder = new RecordingTtlStore;
        Cache::store(name: $name)->setStore($recorder);

        return $recorder;
    }

    /**
     * Registra un cache store nuevo, en memoria, respaldado por un double
     * que registra el TTL. Sirve para distinguir "el controller usó el
     * store configurado" de "el controller usó el store por defecto".
     *
     * El creator devuelve el `Repository`, no el store pelado: Laravel no
     * envuelve lo que devuelve `Cache::extend()`.
     */
    private function registerRecordingCacheStore(string $name): RecordingTtlStore
    {
        $recorder = new RecordingTtlStore;
        Cache::extend(
            driver: 'assets-recording',
            callback: static fn (): Repository => Cache::repository(store: $recorder),
        );
        config()->set(key: 'cache.stores.'.$name, value: ['driver' => 'assets-recording']);

        return $recorder;
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

    // -----------------------------------------------------------------------
    // Fase 3 — URL signing (T2)
    // -----------------------------------------------------------------------

    #[Test]
    public function it_serves_without_signature_when_url_signing_not_required(): void
    {
        // Default: url_signing.required=false. Una request sin
        // ?expires&sig debe pasar sin chequeo de firma.
        $this->assertFalse((bool) config(key: 'assets.url_signing.required'));

        $contents = 'unsigned-content';
        Storage::disk(name: 'ftp')->put(
            path: 'tenants/panda-estampados/branding/unsigned.webp',
            contents: $contents,
        );

        $response = $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/tenants/panda-estampados/branding/unsigned.webp');

        $response->assertOk();
        $this->assertSame(expected: $contents, actual: $response->getContent());
    }

    #[Test]
    public function it_rejects_without_signature_when_url_signing_required(): void
    {
        config()->set(key: 'assets.url_signing.required', value: true);

        $contents = 'signed-only-content';
        Storage::disk(name: 'ftp')->put(
            path: 'tenants/panda-estampados/branding/signed-only.webp',
            contents: $contents,
        );

        // Sin query params de firma.
        $response = $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/tenants/panda-estampados/branding/signed-only.webp');

        $response->assertForbidden();
    }

    #[Test]
    public function it_rejects_expired_signature_when_url_signing_required(): void
    {
        config()->set(key: 'assets.url_signing.required', value: true);

        $contents = 'expired-sig-content';
        Storage::disk(name: 'ftp')->put(
            path: 'tenants/panda-estampados/branding/expired-sig.webp',
            contents: $contents,
        );

        // Firma con expires en el pasado (time() - 10).
        $path = 'tenants/panda-estampados/branding/expired-sig.webp';
        $expires = (string) (time() - 10);
        $signature = hash_hmac(
            algo: 'sha256',
            data: $path.'|'.$expires,
            key: (string) config(key: 'assets.url_signing.secret'),
        );

        $response = $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/'.$path.'?expires='.$expires.'&sig='.$signature);

        $response->assertForbidden();
    }

    #[Test]
    public function it_serves_with_valid_signature_when_url_signing_required(): void
    {
        config()->set(key: 'assets.url_signing.required', value: true);

        $contents = 'signed-content';
        Storage::disk(name: 'ftp')->put(
            path: 'tenants/panda-estampados/branding/signed.webp',
            contents: $contents,
        );

        $path = 'tenants/panda-estampados/branding/signed.webp';
        $expires = (string) (time() + 600);
        $signature = hash_hmac(
            algo: 'sha256',
            data: $path.'|'.$expires,
            key: (string) config(key: 'assets.url_signing.secret'),
        );

        $response = $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/'.$path.'?expires='.$expires.'&sig='.$signature);

        $response->assertOk();
        $this->assertSame(expected: $contents, actual: $response->getContent());
    }

    // -----------------------------------------------------------------------
    // Fase 3 — Enumeration logging (T4)
    // -----------------------------------------------------------------------

    #[Test]
    public function it_logs_structured_warning_on_404(): void
    {
        Log::spy();

        $missingPath = 'tenants/panda-estampados/branding/missing-enum-test.webp';
        $expectedHash = hash(algo: 'sha256', data: $missingPath);

        $response = $this->getJson(uri: 'http://panda-estampados.test/api/v1/storefront/assets/'.$missingPath);

        $response->assertNotFound();

        Log::shouldHaveReceived(method: 'warning')
            ->withArgs(function (string $message, array $context) use ($expectedHash): bool {
                return $message === 'asset.not_found'
                    && $context['tenant_slug'] === 'panda-estampados'
                    && $context['hash'] === $expectedHash
                    && array_key_exists(key: 'client_ip', array: $context)
                    && array_key_exists(key: 'user_agent', array: $context)
                    && is_string($context['user_agent']);
            })
            ->once();
    }
}
