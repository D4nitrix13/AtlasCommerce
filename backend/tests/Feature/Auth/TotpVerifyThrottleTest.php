<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\Role;
use App\Models\Auth\TwoFactorSetting;
use App\Models\Auth\User;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del throttle IP-based para POST /api/v1/auth/2fa/totp/verify (H4 del review).
 *
 * Defense-in-depth: aunque TotpService::verify ya valida ventana ±1 step,
 * un atacante podría iterar sobre codes random. El throttle limita a
 * 10 attempts cada 5 min por IP.
 */
#[Group('auth')]
#[Group('http')]
#[Group('two-factor')]
final class TotpVerifyThrottleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

        $currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($currency, 'currency')->create();
        StoreDomain::query()->create(attributes: [
            'store_id' => $this->store->id,
            'domain' => 'localhost',
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $this->user = User::factory()->create(attributes: [
            'email' => 'totpverifyuser@example.com',
            'password' => Hash::make(value: 'secret-password'),
            'is_active' => true,
            'role_id' => $adminRole->id,
        ]);

        $this->user->stores()->attach($this->store->id);

        // Genera 2FA setting sin enabled_at (estado "draft" típico
        // post-setup). Permite invocar /verify.
        $secret = app(abstract: TotpService::class)->generateSecret();
        TwoFactorSetting::query()->create(attributes: [
            'user_id' => $this->user->id,
            'secret' => Crypt::encryptString(value: $secret),
            'enabled_at' => null,
        ]);

        // Limpia throttle keys PR1.
        RateLimiter::clear(key: 'totp-verify:127.0.0.1');
    }

    #[Test]
    public function it_blocks_totp_verify_after_10_attempts_per_ip(): void
    {
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        // 10 intentos con códigos deliberadamente incorrectos.
        // El primer intento (después de setup) responde "Código inválido".
        // A partir del 2do, el setting existe pero enabled_at es null,
        // por lo que verify chequea TOTP y responde "Código inválido"
        // si el code es wrong.
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson(uri: '/api/v1/auth/2fa/totp/verify', data: [
                'code' => '000000',
            ]);

            // Cualquier 4xx (422 invalid, 400 setup missing, 429 throttle)
            // cuenta como "attempt processed".
            $this->assertGreaterThanOrEqual(400, $response->status());
            $this->assertLessThan(500, $response->status());
        }

        // 11º intento: debe ser bloqueado por throttle con "Demasiados intentos".
        $response = $this->postJson(uri: '/api/v1/auth/2fa/totp/verify', data: [
            'code' => '000000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath(
            path: 'errors.code.0',
            expect: fn (string $m): bool => str_contains($m, 'Demasiados intentos'),
        );
    }
}
