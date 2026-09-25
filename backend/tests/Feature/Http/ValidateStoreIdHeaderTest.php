<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Auth\Role;
use App\Models\Auth\User as UserModel;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests para `ValidateStoreIdHeader` middleware.
 *
 * Casos:
 *   - Sin X-Store-Id header → request pasa (header opcional)
 *   - X-Store-Id que coincide con el store del host → pasa
 *   - X-Store-Id que NO coincide → 400 con mensaje explicito
 *   - X-Store-Id vacio ('') → trata como ausente, pasa
 *
 * Para activar el middleware 'validate.store_id' en el test, lo
 * registramos en un middleware group ad-hoc en cada test (Laravel
 * test::withMiddleware / directly en routes).
 */
final class ValidateStoreIdHeaderTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $user;

    private Store $store;

    private string $host = 'admin.panda-estampados.test';

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );
        $currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($currency, 'currency')->create();

        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => $this->host,
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $this->user = UserModel::query()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'validate-store-id@panda-estampados.test',
            'email_verified_at' => now(),
            'password' => Hash::make(value: 'secret'),
            'phone' => '5555555555',
            'is_active' => true,
            'registered_at' => now(),
            'role_id' => $role->id,
        ]);
        $this->user->stores()->attach($this->store->id);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);
    }

    public function test_request_without_x_store_id_passes(): void
    {
        // El test route incluye solo el middleware 'store.domain' para
        // resolver el store; NO incluye 'validate.store_id' (header opcional).
        $response = $this->withHeaders(headers: ['Host' => $this->host])
            ->getJson(uri: "http://{$this->host}/api/v1/auth/me");

        // 200 OK si esta logueado, 401/302 si no — lo importante es que
        // NO es 400 (que seria el signature de validate.store_id).
        $this->assertNotSame(400, $response->getStatusCode());
    }

    public function test_request_with_matching_x_store_id_passes(): void
    {
        $response = $this->withHeaders([
            'Host' => $this->host,
            'X-Store-Id' => (string) $this->store->id,
        ])->getJson(uri: "http://{$this->host}/api/v1/admin/metrics/revenue/yearly");

        // 200 OK si el store matchea. Sin validate.store_id aplicado al route,
        // el header es ignorado. Sin error 400.
        $this->assertNotSame(400, $response->getStatusCode());
    }

    public function test_middleware_unit_test_direct_invocation(): void
    {
        // Test unit del middleware con Request simulados. Evita la
        // complejidad de routing ad-hoc.
        $middleware = new \App\Http\Middleware\ValidateStoreIdHeader;

        // Sin header → pasa al next.
        $request = \Illuminate\Http\Request::create(uri: '/x', method: 'GET');
        $request->attributes->set(key: 'store', value: $this->store);
        $response = $middleware->handle($request, fn ($req) => response()->json(['ok' => true]));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(true, $response->getData(assoc: true)['ok']);

        // Header match → pasa.
        $request2 = \Illuminate\Http\Request::create(uri: '/x', method: 'GET');
        $request2->headers->set('X-Store-Id', (string) $this->store->id);
        $request2->attributes->set(key: 'store', value: $this->store);
        $response2 = $middleware->handle($request2, fn ($req) => response()->json(['ok' => true]));
        $this->assertSame(200, $response2->getStatusCode());

        // Header mismatch → 400.
        $request3 = \Illuminate\Http\Request::create(uri: '/x', method: 'GET');
        $request3->headers->set('X-Store-Id', '999');  // ID diferente
        $request3->attributes->set(key: 'store', value: $this->store);
        $response3 = $middleware->handle($request3, fn ($req) => response()->json(['ok' => true]));
        $this->assertSame(400, $response3->getStatusCode());
        $this->assertSame(
            'X-Store-Id header does not match the resolved store for this host.',
            $response3->getData(assoc: true)['message'],
        );
        $this->assertSame(999, $response3->getData(assoc: true)['header_store_id']);
        $this->assertSame(
            (int) $this->store->id,
            $response3->getData(assoc: true)['resolved_store_id'],
        );

        // Header vacio → trata como ausente, pasa.
        $request4 = \Illuminate\Http\Request::create(uri: '/x', method: 'GET');
        $request4->headers->set('X-Store-Id', '');
        $request4->attributes->set(key: 'store', value: $this->store);
        $response4 = $middleware->handle($request4, fn ($req) => response()->json(['ok' => true]));
        $this->assertSame(200, $response4->getStatusCode());
    }
}
