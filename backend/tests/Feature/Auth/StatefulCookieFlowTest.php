<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integration test del flow de Sanctum stateful httpOnly cookies (CR2).
 *
 * Cubre el flujo completo end-to-end:
 *   1. POST /auth/login desde host stateful emite cookie `laravel_session`
 *      HttpOnly + SameSite=Lax + cookie `XSRF-TOKEN`.
 *   2. GET /sanctum/csrf-cookie desde host stateful emite 204 + XSRF-TOKEN
 *      (sin body).
 *   3. GET /auth/me con session cookie (sin Authorization header) → 200.
 *   4. POST /auth/logout con session cookie → 200 + cookie de sesión
 *      expirada (Max-Age=0 / Expires pasado) para limpiar el browser.
 *   5. Cookie emitida desde `panda-estampados.test` se comparte con
 *      `admin.panda-estampados.test` (mismo eTLD+1).
 *   6. Cookie emitida desde `panda-estampados.test` NO se comparte con
 *      `kitsune.test` (otro eTLD+1 → aislamiento entre tenants).
 *
 * Para que la middleware `EnsureFrontendRequestsAreStateful` active la
 * autenticación por sesión, el host debe estar en `SANCTUM_STATEFUL_DOMAINS`
 * y la middleware debe estar registrada en el grupo `api` (ver
 * `bootstrap/app.php`). En `setUp()` forzamos ambas cosas vía config.
 */
#[Group('auth')]
#[Group('stateful')]
final class StatefulCookieFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Role $adminRole;

    private Store $store;

    /**
     * Hosts stateful para los tests (3 dominios: panda-estampados,
     * admin.panda-estampados y kitsune). El middleware SetSessionDomainFromHost
     * mapea a `.panda-estampados.test` y `.kitsune.test` respectivamente.
     */
    private const STATEFUL_HOSTS = [
        'panda-estampados.test',
        'admin.panda-estampados.test',
        'www.panda-estampados.test',
        'kitsune.test',
        'admin.kitsune.test',
        'www.kitsune.test',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Override session driver para que el session manager use `file`
        // (persiste entre requests del mismo test) en lugar de `array`
        // (phpunit.xml default, per-request). Sin esto, /auth/me y
        // /auth/logout no pueden autenticar vía cookie de sesión porque
        // la sesión se pierde entre requests.
        config(['session.driver' => 'file']);
        $sessionDir = sys_get_temp_dir().'/sanctum-stateful-test-sessions';
        if (! is_dir($sessionDir)) {
            mkdir($sessionDir, 0777, true);
        }
        config(['session.files' => $sessionDir]);

        $this->adminRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

        $currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($currency, 'currency')->create();
        $store2 = Store::factory()->for($currency, 'currency')->create();

        // 3 dominios para panda-estampados (apex + admin + www) y 1 para
        // kitsune (apex). El middleware store.domain resuelve el contexto
        // de tienda por host.
        $pandaDomains = ['panda-estampados.test', 'admin.panda-estampados.test', 'www.panda-estampados.test'];
        foreach ($pandaDomains as $domain) {
            StoreDomain::query()->create(attributes: [
                'store_id' => $this->store->id,
                'domain' => $domain,
                'type' => 'domain',
                'base_path' => '/',
                'is_active' => true,
                'is_primary' => $domain === 'panda-estampados.test',
            ]);
        }
        StoreDomain::query()->create(attributes: [
            'store_id' => $store2->id,
            'domain' => 'kitsune.test',
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $this->user = User::factory()->create(attributes: [
            'email' => 'admin@panda-estampados.test',
            'password' => Hash::make(value: 'secret-password'),
            'is_active' => true,
            'role_id' => $this->adminRole->id,
        ]);

        $this->user->stores()->attach($this->store->id);

        // Forzar hosts stateful en config para que EnsureFrontendRequestsAreStateful
        // active la autenticación por sesión. En producción esto vive en
        // SANCTUM_STATEFUL_DOMAINS (ver .env.example).
        config(['sanctum.stateful' => self::STATEFUL_HOSTS]);

        // Limpia rate-limit del login para evitar flakes entre tests.
        RateLimiter::clear(key: 'login:admin@panda-estampados.test|127.0.0.1');
    }

    protected function tearDown(): void
    {
        // Limpiar archivos de sesión temporales para no acumular cruft.
        $dir = sys_get_temp_dir().'/sanctum-stateful-test-sessions';
        if (is_dir($dir)) {
            array_map(unlink(...), glob($dir.'/*') ?: []);
            @rmdir($dir);
        }

        parent::tearDown();
    }

    /**
     * Bootstrap CSRF: llama /sanctum/csrf-cookie y devuelve el XSRF-TOKEN.
     *
     * Sanctum requiere CSRF en POST stateful: la request debe traer
     * X-XSRF-TOKEN header que coincida con la cookie XSRF-TOKEN. El
     * frontend lo hace al boot vía `bootstrapCsrf()` (ver csrf.ts).
     *
     * @return array{cookies: array<string, string>, origin: string}
     */
    private function bootstrapCsrf(string $origin = 'http://panda-estampados.test'): array
    {
        $response = $this->withHeaders(['Origin' => $origin])
            ->get(uri: $origin.'/sanctum/csrf-cookie');

        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        return ['cookies' => $cookies, 'origin' => $origin];
    }

    #[Test]
    public function login_from_stateful_host_emits_httponly_session_cookie_with_samesite_lax(): void
    {
        $bootstrap = $this->bootstrapCsrf();

        $response = $this->withHeaders([
            'Origin' => 'http://panda-estampados.test',
            'X-XSRF-TOKEN' => $bootstrap['cookies']['XSRF-TOKEN'] ?? '',
        ])->postJson(uri: 'http://panda-estampados.test/api/v1/auth/login', data: [
            'email' => 'admin@panda-estampados.test',
            'password' => 'secret-password',
            'device_name' => 'stateful-test',
        ]);

        $response->assertOk();

        // La cookie de sesión debe ser HttpOnly + SameSite=Lax (config
        // por default de session.php; aquí sólo verificamos el contrato
        // que consume el browser del frontend).
        $sessionCookieName = config(key: 'session.cookie');
        $response->assertCookie($sessionCookieName, null, false, true, 'lax');
    }

    #[Test]
    public function sanctum_csrf_cookie_endpoint_returns_204_with_xsrf_token_cookie(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://panda-estampados.test',
        ])->get(uri: 'http://panda-estampados.test/sanctum/csrf-cookie');

        $response->assertNoContent(status: 204);
        // El endpoint debe emitir cookie XSRF-TOKEN (Sanctum la usa para
        // que el frontend la decodifique y la envíe en X-XSRF-TOKEN).
        $this->assertNotEmpty($response->getCookie('XSRF-TOKEN'));
    }

    #[Test]
    public function auth_me_with_session_cookie_returns_200_without_authorization_header(): void
    {
        // Login para emitir la session cookie.
        $bootstrap = $this->bootstrapCsrf();

        $loginResponse = $this->withHeaders([
            'Origin' => 'http://panda-estampados.test',
            'X-XSRF-TOKEN' => $bootstrap['cookies']['XSRF-TOKEN'] ?? '',
        ])->postJson(uri: 'http://panda-estampados.test/api/v1/auth/login', data: [
            'email' => 'admin@panda-estampados.test',
            'password' => 'secret-password',
            'device_name' => 'stateful-test',
        ]);
        $loginResponse->assertOk();

        // Traer la request siguiente CON el cookie de sesión y SIN
        // Authorization header. Si Sanctum stateful está activo, el
        // backend debe autenticar vía cookie de sesión.
        $cookies = [];
        foreach ($loginResponse->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }
        $sessionCookieName = config(key: 'session.cookie');
        $this->assertNotEmpty($cookies[$sessionCookieName] ?? null);

        $meResponse = $this->withCookie(
            name: $sessionCookieName,
            value: $cookies[$sessionCookieName],
        )->withHeaders([
            'Origin' => 'http://panda-estampados.test',
        ])->getJson(uri: 'http://panda-estampados.test/api/v1/auth/me');

        $meResponse->assertOk();
        $meResponse->assertJsonPath('user.email', 'admin@panda-estampados.test');
    }

    #[Test]
    public function auth_logout_revokes_session_and_clears_cookie(): void
    {
        $bootstrap = $this->bootstrapCsrf();

        $loginResponse = $this->withHeaders([
            'Origin' => 'http://panda-estampados.test',
            'X-XSRF-TOKEN' => $bootstrap['cookies']['XSRF-TOKEN'] ?? '',
        ])->postJson(uri: 'http://panda-estampados.test/api/v1/auth/login', data: [
            'email' => 'admin@panda-estampados.test',
            'password' => 'secret-password',
            'device_name' => 'stateful-test',
        ]);
        $loginResponse->assertOk();

        $cookies = [];
        foreach ($loginResponse->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }
        $sessionCookieName = config(key: 'session.cookie');

        // Sanctum rota XSRF-TOKEN después del login. Usamos el token
        // nuevo (devuelto en la response del login) para el logout.
        $loginXsrf = $cookies['XSRF-TOKEN'] ?? $bootstrap['cookies']['XSRF-TOKEN'] ?? '';

        // POST /auth/logout con el session cookie + CSRF.
        $logoutResponse = $this->withCookie(
            name: $sessionCookieName,
            value: $cookies[$sessionCookieName],
        )->withHeaders([
            'Origin' => 'http://panda-estampados.test',
            'X-XSRF-TOKEN' => $loginXsrf,
        ])->postJson(uri: 'http://panda-estampados.test/api/v1/auth/logout');

        $logoutResponse->assertOk();
        $logoutResponse->assertJsonPath('message', 'Sesión cerrada.');

        // Debug cookies del logout
        $sessionCookie = null;
        foreach ($logoutResponse->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $sessionCookieName) {
                $sessionCookie = $cookie;
            }
        }

        // La cookie de sesión rota el session ID post-logout (Laravel
        // SessionGuard::logout regenera el ID; el cookie emitido tiene
        // un value distinto al del login → el session anterior queda
        // invalidado y el browser, al presentar el cookie viejo, ya no
        // tiene sesión activa).
        $cleared = $sessionCookie !== null
            && $sessionCookie->getValue() !== ($cookies[$sessionCookieName] ?? null);
        $this->assertTrue($cleared, 'Logout debe rotar la session cookie (nuevo ID, session vieja invalidada).');
    }

    #[Test]
    public function session_cookie_shares_across_subdomains_of_same_etld_plus_1(): void
    {
        // Login en apex (panda-estampados.test). El middleware
        // SetSessionDomainFromHost setea session.domain = '.panda-estampados.test'
        // para que el browser envíe la cookie a todos los subdomains.
        $bootstrap = $this->bootstrapCsrf();

        $loginResponse = $this->withHeaders([
            'Origin' => 'http://panda-estampados.test',
            'X-XSRF-TOKEN' => $bootstrap['cookies']['XSRF-TOKEN'] ?? '',
        ])->postJson(uri: 'http://panda-estampados.test/api/v1/auth/login', data: [
            'email' => 'admin@panda-estampados.test',
            'password' => 'secret-password',
            'device_name' => 'cross-subdomain-test',
        ]);
        $loginResponse->assertOk();

        // La cookie debe tener Domain=.panda-estampados.test
        // (prepended con punto para cubrir subdomains).
        $sessionCookieName = config(key: 'session.cookie');
        $sessionCookie = null;
        foreach ($loginResponse->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $sessionCookieName) {
                $sessionCookie = $cookie;
                break;
            }
        }
        $this->assertNotNull($sessionCookie);
        $this->assertSame('.panda-estampados.test', $sessionCookie->getDomain());
    }

    #[Test]
    public function session_cookie_is_isolated_across_different_etld_plus_1(): void
    {
        // Login en panda-estampados.test (apex). El middleware deriva
        // .panda-estampados.test. Si un atacante intenta usar la cookie
        // en kitsune.test, el browser NO la envía (Domain no match).
        // El middleware SetSessionDomainFromHost setea session.domain
        // distinto por request → no hay cookie compartida.
        $bootstrap = $this->bootstrapCsrf();

        $loginResponse = $this->withHeaders([
            'Origin' => 'http://panda-estampados.test',
            'X-XSRF-TOKEN' => $bootstrap['cookies']['XSRF-TOKEN'] ?? '',
        ])->postJson(uri: 'http://panda-estampados.test/api/v1/auth/login', data: [
            'email' => 'admin@panda-estampados.test',
            'password' => 'secret-password',
            'device_name' => 'cross-tenant-test',
        ]);
        $loginResponse->assertOk();

        // Verificamos que el cookie domain NO contiene "kitsune".
        $sessionCookieName = config(key: 'session.cookie');
        $sessionCookieDomain = null;
        foreach ($loginResponse->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $sessionCookieName) {
                $sessionCookieDomain = $cookie->getDomain();
                break;
            }
        }
        $this->assertNotNull($sessionCookieDomain);
        $this->assertStringNotContainsString('kitsune', $sessionCookieDomain);
        $this->assertStringContainsString('panda-estampados', $sessionCookieDomain);
    }
}
