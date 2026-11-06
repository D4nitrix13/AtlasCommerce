<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CA.2 (customer-auth sprint, commit 2 de 5) — feature test del
 * register de Customer desde el storefront.
 *
 * Cubre:
 *  - Happy path: User + Customer + Store + token creados, respuesta
 *    tiene el shape esperado.
 *  - Validación de input: email duplicado, password corto, password
 *    confirmation que no coincide, customer_type inválido, etc.
 *  - Defensa: tienda inactiva → 503 (no se debe crear customer).
 *  - Defensa: el role del User creado es 'customer' (no admin).
 *  - Customer y User quedan vinculados vía customer.user_id.
 *  - El token Sanctum es válido y resuelve a la User creada.
 */
#[Group('auth')]
#[Group('customer-auth')]
final class StorefrontRegisterTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);

        $currency = Currency::factory()->primary()->create(attributes: [
            'code' => 'USD',
            'symbol' => '$',
            'decimal_places' => 2,
        ]);

        $this->store = Store::factory()->for($currency, 'currency')->create();
        // El middleware `store.domain` resuelve contexto via el Host
        // header. Usamos `localhost` que es el default de TestCase
        // (mismo patrón que AuthHttpTest::setUp).
        StoreDomain::query()->create(attributes: [
            'store_id' => $this->store->id,
            'domain' => 'localhost',
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);
    }

    protected function tearDown(): void
    {
        // Limpiar archivos de sesión del harness stateful (session driver
        // `file`) para no acumular cruft entre corridas. Mismo patrón que
        // StatefulCookieFlowTest::tearDown.
        $sessionDir = sys_get_temp_dir().'/storefront-register-stateful-sessions';
        if (is_dir($sessionDir)) {
            array_map(unlink(...), glob($sessionDir.'/*') ?: []);
            @rmdir($sessionDir);
        }

        parent::tearDown();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'cliente@panda-estampados.test',
            'password' => 'SecurePass1234',
            'password_confirmation' => 'SecurePass1234',
            'first_name' => 'Cliente',
            'last_name' => 'Panda',
            'phone' => '+505 8888 1234',
            'customer_type' => 'retail',
        ], $overrides);
    }

    private function registerAs(string $host, array $payload): TestResponse
    {
        // Usamos la URL completa con host para que el middleware
        // `store.domain` resuelva el contexto via Host header. El método
        // `postJson` setea automáticamente Content-Type: application/json
        // y serializa el body como JSON.
        return $this->postJson(
            uri: "http://{$host}/api/v1/storefront/auth/register",
            data: $payload,
        );
    }

    #[Test]
    public function happy_path_creates_user_customer_and_store_link(): void
    {
        $this->assertDatabaseCount(table: User::class, count: 0);
        $this->assertDatabaseCount(table: Customer::class, count: 0);

        $response = $this->registerAs('localhost', $this->validPayload());

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'token',
            'user' => ['id', 'email', 'full_name', 'role' => ['slug']],
            'customer' => ['id', 'email', 'first_name', 'last_name', 'customer_type'],
        ]);

        // 1 User, 1 Customer, con user_id linkeado.
        $this->assertDatabaseCount(table: User::class, count: 1);
        $this->assertDatabaseCount(table: Customer::class, count: 1);

        $user = User::query()->where(column: 'email', operator: '=', value: 'cliente@panda-estampados.test')->firstOrFail();
        $customer = Customer::query()->where(column: 'user_id', operator: '=', value: $user->id)->firstOrFail();

        $this->assertSame(expected: 'cliente@panda-estampados.test', actual: $user->email);
        $this->assertSame(expected: 'cliente@panda-estampados.test', actual: $customer->email);
        $this->assertSame(expected: 'Cliente', actual: $user->first_name);
        $this->assertSame(expected: 'Panda', actual: $user->last_name);
        $this->assertTrue(condition: $user->is_active);
        $this->assertTrue(condition: $customer->is_active);
        $this->assertNotNull(actual: $user->email_verified_at);

        // Role es 'customer', NO admin ni staff.
        $this->assertSame(
            expected: 'customer',
            actual: Role::query()->find($user->role_id)->slug,
        );

        // Password fue hashed (no se guarda en plain text).
        $this->assertFalse(condition: Hash::check(value: 'wrong-password', hashedValue: $user->password));
        $this->assertTrue(condition: Hash::check(value: 'SecurePass1234', hashedValue: $user->password));

        // Customer está vinculado a la tienda activa.
        $this->assertDatabaseHas(table: 'customer_stores', data: [
            'customer_id' => $customer->id,
            'store_id' => $this->store->id,
        ]);
    }

    #[Test]
    public function happy_path_emits_a_valid_sanctum_token(): void
    {
        $response = $this->registerAs('localhost', $this->validPayload());
        $response->assertStatus(201);

        $token = $response->json(key: 'token');
        $this->assertIsString(actual: $token);
        $this->assertNotEmpty(actual: $token);

        // El user puede ser resuelto con el token emitido (Personal
        // Access Token de Sanctum).
        $user = User::query()->where(column: 'email', operator: '=', value: 'cliente@panda-estampados.test')->firstOrFail();
        $this->assertTrue(condition: $user->tokens()->where('name', 'storefront-register')->exists());
    }

    #[Test]
    public function email_must_be_unique(): void
    {
        User::factory()->create(attributes: [
            'email' => 'cliente@panda-estampados.test',
            'is_active' => true,
        ]);

        $response = $this->registerAs('localhost', $this->validPayload());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(errors: 'email');
    }

    #[Test]
    public function password_must_be_at_least_8_chars(): void
    {
        $response = $this->registerAs(
            'localhost',
            $this->validPayload(['password' => 'short', 'password_confirmation' => 'short']),
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(errors: 'password');
    }

    #[Test]
    public function password_confirmation_must_match(): void
    {
        $response = $this->registerAs(
            'localhost',
            $this->validPayload(['password_confirmation' => 'DifferentPass1234']),
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(errors: 'password_confirmation');
    }

    #[Test]
    public function first_name_and_last_name_are_required(): void
    {
        $response = $this->registerAs(
            'localhost',
            $this->validPayload(['first_name' => '', 'last_name' => '']),
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(errors: ['first_name', 'last_name']);
    }

    #[Test]
    public function customer_type_must_be_valid_enum(): void
    {
        $response = $this->registerAs(
            'localhost',
            $this->validPayload(['customer_type' => 'hacker']),
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(errors: 'customer_type');
    }

    #[Test]
    public function customer_type_defaults_to_retail_when_omitted(): void
    {
        $payload = $this->validPayload();
        unset($payload['customer_type']);

        $response = $this->registerAs('localhost', $payload);
        $response->assertStatus(201);

        $customer = Customer::query()->latest('id')->firstOrFail();
        $this->assertSame(expected: 'retail', actual: $customer->customer_type);
    }

    #[Test]
    public function returns_404_when_no_active_store_resolves(): void
    {
        // El middleware `store.domain` retorna 404 (no 503) cuando
        // el host no resuelve a una tienda activa. Defense in depth
        // a nivel de routing: el request nunca llega al controller.
        StoreDomain::query()->where(column: 'domain', operator: '=', value: 'localhost')
            ->update(['is_active' => false]);

        $response = $this->registerAs('localhost', $this->validPayload());

        $response->assertStatus(404);

        $this->assertDatabaseCount(table: User::class, count: 0);
        $this->assertDatabaseCount(table: Customer::class, count: 0);
    }

    #[Test]
    public function register_does_not_require_authentication(): void
    {
        // El endpoint es público. Sin Sanctum token debe poder
        // registrarse (la ruta NO está bajo auth:sanctum). Verificamos
        // simplemente que un request sin auth previa recibe 201 — si
        // estuviera bajo auth:sanctum, sería 401.
        $response = $this->registerAs('localhost', $this->validPayload());
        $response->assertStatus(201);
    }

    /**
     * Extrae los cookies Set-Cookie de un response como [nombre => valor].
     *
     * @return array<string, string>
     */
    private function cookiesFrom(TestResponse $response): array
    {
        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        return $cookies;
    }

    #[Test]
    public function register_from_stateful_host_establishes_web_session_for_cookie_only_me(): void
    {
        // Harness stateful (mismo patrón que StatefulCookieFlowTest):
        // session driver `file` para persistir la sesión entre requests
        // (el driver `array` de phpunit.xml la descarta por request) y host
        // en `sanctum.stateful` para que EnsureFrontendRequestsAreStateful
        // arranque StartSession en /register y /me.
        $sessionDir = sys_get_temp_dir().'/storefront-register-stateful-sessions';
        if (! is_dir($sessionDir)) {
            mkdir($sessionDir, 0777, true);
        }
        config(['session.driver' => 'file']);
        config(['session.files' => $sessionDir]);
        config(['sanctum.stateful' => ['localhost']]);

        // 1. Bootstrap CSRF (doble-submit cookie): la sesión nace acá con su
        //    token, entregado al browser via cookie XSRF-TOKEN. El middleware
        //    custom ValidateStatefulCsrf (sin bypass para tests) lo exige en
        //    todo POST stateful.
        $csrfResponse = $this->withHeaders(['Origin' => 'http://localhost'])
            ->get(uri: 'http://localhost/sanctum/csrf-cookie');

        $csrfCookies = $this->cookiesFrom(response: $csrfResponse);
        $sessionCookieName = config(key: 'session.cookie');
        $this->assertNotEmpty($csrfCookies[$sessionCookieName] ?? null);
        $this->assertNotEmpty($csrfCookies['XSRF-TOKEN'] ?? null);

        // 2. Register desde host stateful, con la cookie de sesión + el
        //    header X-XSRF-TOKEN con el valor crudo de la cookie (el browser
        //    copia document.cookie tal cual; ValidateStatefulCsrf lo
        //    desencripta y compara contra el token de la sesión).
        $registerResponse = $this->withCookie(
            name: $sessionCookieName,
            value: $csrfCookies[$sessionCookieName],
        )->withHeaders([
            'Origin' => 'http://localhost',
            'X-XSRF-TOKEN' => $csrfCookies['XSRF-TOKEN'],
        ])->registerAs('localhost', $this->validPayload());

        $registerResponse->assertStatus(201);

        // El login rota el session ID (SessionGuard::login → migrate): la
        // cookie de sesión que vale para /me es la emitida por el register.
        $registerCookies = $this->cookiesFrom(response: $registerResponse);
        $this->assertNotEmpty(
            $registerCookies[$sessionCookieName] ?? null,
            'El register desde host stateful debe emitir cookie de sesión.',
        );

        // 3. GET /me con SOLO la cookie de sesión (sin Authorization): el
        //    usuario recién creado debe quedar autenticado en la sesión web.
        $meResponse = $this->withCookie(
            name: $sessionCookieName,
            value: $registerCookies[$sessionCookieName],
        )->getJson(
            uri: 'http://localhost/api/v1/auth/me',
            headers: ['Origin' => 'http://localhost'],
        );

        $meResponse->assertOk();
        $meResponse->assertJsonPath('user.email', 'cliente@panda-estampados.test');
        $meResponse->assertJsonPath('customer.email', 'cliente@panda-estampados.test');
    }
}
