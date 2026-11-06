<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T2 — mensajes de validación y de auth localizados por el locale
 * del request (header `X-Locale` → `Accept-Language` → default `es`).
 *
 * Cubre:
 *  - RegisterRequest: el mensaje de `required` en `email` sigue el
 *    locale del request (es/en/default).
 *  - LoginRequest: los mensajes `custom` (email/password requeridos)
 *    siguen el locale del request.
 *  - Controller-level (AuthController::login): el mensaje de
 *    credenciales inválidas se traduce según el locale del request.
 *  - Middleware SetRequestLocale: `Accept-Language` se usa cuando no
 *    hay header `X-Locale` (primary tag de `en-US,en;q=0.9` → `en`).
 */
#[Group('auth')]
#[Group('customer-auth')]
final class ValidationLocaleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Headers que representan "sin preferencia de idioma".
     *
     * `Request::create()` de Symfony (el harness de tests) inyecta
     * `Accept-Language: en-us,en;q=0.5` en todo request que no lo mande
     * explícitamente; la cadena de resolución (X-Locale →
     * Accept-Language → es) lo resolvería a `en`. Para simular un
     * cliente que realmente no manda headers de idioma (el caso que
     * debe caer al default `es`), se pisa con un valor vacío.
     */
    private const NO_LANGUAGE_HEADERS = ['Accept-Language' => ''];

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);

        $currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($currency, 'currency')->create();
        // El middleware `store.domain` resuelve contexto via el Host
        // header. Usamos `localhost` que es el default de TestCase
        // (mismo patrón que StorefrontRegisterTest::setUp).
        StoreDomain::query()->create(attributes: [
            'store_id' => $this->store->id,
            'domain' => 'localhost',
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);
    }

    /**
     * POST al register con body inválido (vacío) y los headers dados.
     */
    private function postRegister(array $headers = []): TestResponse
    {
        return $this->postJson(
            uri: 'http://localhost/api/v1/storefront/auth/register',
            data: [],
            headers: $headers,
        );
    }

    /**
     * POST al login con body inválido (vacío) y los headers dados.
     */
    private function postLogin(array $headers = []): TestResponse
    {
        return $this->postJson(
            uri: 'http://localhost/api/v1/auth/login',
            data: [],
            headers: $headers,
        );
    }

    #[Test]
    public function register_validation_messages_follow_locale(): void
    {
        // X-Locale: es → mensaje custom en español (voseo del producto).
        $es = $this->postRegister(['X-Locale' => 'es']);
        $es->assertStatus(422);
        $this->assertSame(expected: 'Ingresá tu correo electrónico.', actual: $es->json(key: 'errors.email.0'));

        // X-Locale: en → mensaje custom en inglés.
        $en = $this->postRegister(['X-Locale' => 'en']);
        $en->assertStatus(422);
        $this->assertSame(expected: 'Enter your email address.', actual: $en->json(key: 'errors.email.0'));

        // Sin headers de idioma → default del storefront: español.
        $default = $this->postRegister(self::NO_LANGUAGE_HEADERS);
        $default->assertStatus(422);
        $this->assertSame(expected: 'Ingresá tu correo electrónico.', actual: $default->json(key: 'errors.email.0'));
        $this->assertNotSame(expected: 'The email field is required.', actual: $default->json(key: 'errors.email.0'));
    }

    #[Test]
    public function login_validation_messages_follow_locale(): void
    {
        // X-Locale: es → mensajes custom en español.
        $es = $this->postLogin(['X-Locale' => 'es']);
        $es->assertStatus(422);
        $this->assertSame(expected: 'Ingresá tu correo electrónico.', actual: $es->json(key: 'errors.email.0'));
        $this->assertSame(expected: 'Ingresá una contraseña.', actual: $es->json(key: 'errors.password.0'));

        // X-Locale: en → mensajes custom en inglés.
        $en = $this->postLogin(['X-Locale' => 'en']);
        $en->assertStatus(422);
        $this->assertSame(expected: 'Enter your email address.', actual: $en->json(key: 'errors.email.0'));
        $this->assertSame(expected: 'Enter a password.', actual: $en->json(key: 'errors.password.0'));

        // Sin headers de idioma → default del storefront: español.
        $default = $this->postLogin(self::NO_LANGUAGE_HEADERS);
        $default->assertStatus(422);
        $this->assertSame(expected: 'Ingresá tu correo electrónico.', actual: $default->json(key: 'errors.email.0'));
    }

    #[Test]
    public function login_failure_message_follows_locale(): void
    {
        $customerRole = Role::query()->where(column: 'slug', operator: '=', value: 'customer')->firstOrFail();
        User::factory()->create(attributes: [
            'first_name' => 'Cliente',
            'last_name' => 'Panda',
            'email' => 'cliente@panda-estampados.test',
            'password' => Hash::make(value: 'SecurePass1234'),
            'role_id' => $customerRole->id,
            'is_active' => true,
        ]);
        RateLimiter::clear(key: 'login:cliente@panda-estampados.test|127.0.0.1');

        // X-Locale: es → mensaje del controller en español.
        $es = $this->postJson(
            uri: 'http://localhost/api/v1/auth/login',
            data: ['email' => 'cliente@panda-estampados.test', 'password' => 'wrong-password'],
            headers: ['X-Locale' => 'es'],
        );
        $es->assertStatus(422);
        $this->assertSame(expected: 'Las credenciales no son válidas.', actual: $es->json(key: 'errors.email.0'));

        // X-Locale: en → mensaje del controller en inglés.
        $en = $this->postJson(
            uri: 'http://localhost/api/v1/auth/login',
            data: ['email' => 'cliente@panda-estampados.test', 'password' => 'wrong-password'],
            headers: ['X-Locale' => 'en'],
        );
        $en->assertStatus(422);
        $this->assertSame(expected: 'Invalid credentials.', actual: $en->json(key: 'errors.email.0'));
    }

    #[Test]
    public function accept_language_primary_tag_is_used_when_no_locale_header(): void
    {
        // Sin X-Locale: el primary tag de Accept-Language resuelve el
        // locale (`en-US,en;q=0.9` → `en`).
        $response = $this->postRegister(['Accept-Language' => 'en-US,en;q=0.9']);
        $response->assertStatus(422);
        $this->assertSame(expected: 'Enter your email address.', actual: $response->json(key: 'errors.email.0'));
    }
}
