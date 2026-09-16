<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\OtpChallenge;
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
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del nuevo flow re-auth de TotpController::destroy (H3 del review).
 *
 * Disable 2FA requiere proof-of-identity: password XOR current_totp_code.
 * Si el user tiene 2FA activa → current_totp_code (preferred).
 * Si NO tiene 2FA → password (única opción).
 *
 * En éxito, persiste OtpChallenge audit row + emite Log::warning.
 */
#[Group('auth')]
#[Group('http')]
#[Group('two-factor')]
final class TotpDestroyReAuthTest extends TestCase
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
            'email' => 'totpuser@example.com',
            'password' => Hash::make(value: 'secret-password'),
            'is_active' => true,
            'role_id' => $adminRole->id,
        ]);

        $this->user->stores()->attach($this->store->id);
    }

    /**
     * Helper: crea un user con 2FA habilitada y retorna el secret
     * base32 en texto plano para que el test compute el TOTP code.
     *
     * @return array{user: User, secret: string}
     */
    private function userWith2fa(): array
    {
        $secret = app(abstract: TotpService::class)->generateSecret();

        TwoFactorSetting::query()->create(attributes: [
            'user_id' => $this->user->id,
            'secret' => Crypt::encryptString(value: $secret),
            'enabled_at' => now(),
        ]);

        return ['user' => $this->user, 'secret' => $secret];
    }

    /**
     * Helper: computa el código TOTP de 6 dígitos para el secret dado.
     */
    private function totpCodeFor(string $base32Secret, ?int $step = null): string
    {
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32 = strtoupper(rtrim($base32Secret, '='));
        $base32 = preg_replace('/\s+/', '', $base32) ?? $base32;
        $lookup = array_flip(str_split($base32chars));
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;
        for ($i = 0, $n = strlen($base32); $i < $n; $i++) {
            $buffer = ($buffer << 5) | $lookup[$base32[$i]];
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        $step ??= intdiv(num1: time(), num2: TotpService::STEP_SECONDS);
        $counter = pack('N*', 0, $step);
        $hash = hash_hmac(algo: 'sha1', data: $counter, key: $output, binary: true);
        $offset = ord($hash[19]) & 0xF;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** TotpService::DIGITS);

        return str_pad(
            string: (string) $code,
            length: TotpService::DIGITS,
            pad_string: '0',
            pad_type: STR_PAD_LEFT,
        );
    }

    #[Test]
    public function test_destroy_requires_reauth_with_totp_code_when_2fa_active(): void
    {
        // User tiene 2FA activa; DELETE sin current_totp_code (solo password)
        // debe ser rechazado: con 2FA activa, current_totp_code es obligatorio.
        $this->userWith2fa();

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->deleteJson(uri: '/api/v1/auth/2fa/totp', data: [
            'password' => 'secret-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath(path: 'message', expect: 'Con 2FA activo, debes confirmar con current_totp_code.');
    }

    #[Test]
    public function test_destroy_requires_password_when_2fa_not_active(): void
    {
        // User NO tiene 2FA activa; DELETE con current_totp_code debe ser
        // rechazado: sin 2FA, solo password es válido.
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->deleteJson(uri: '/api/v1/auth/2fa/totp', data: [
            'current_totp_code' => '123456',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath(path: 'message', expect: 'Debes confirmar con password.');
    }

    #[Test]
    public function test_destroy_returns_422_on_wrong_totp_code(): void
    {
        ['secret' => $secret] = $this->userWith2fa();
        // Generamos un code DELIBERADAMENTE incorrecto (distinto al actual).
        $wrongStep = intdiv(num1: time() + 100, num2: TotpService::STEP_SECONDS);
        $wrongCode = $this->totpCodeFor(base32Secret: $secret, step: $wrongStep);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->deleteJson(uri: '/api/v1/auth/2fa/totp', data: [
            'current_totp_code' => $wrongCode === $this->totpCodeFor($secret)
                ? '000000'
                : $wrongCode,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath(path: 'message', expect: 'Código TOTP inválido.');
    }

    #[Test]
    public function test_destroy_returns_422_on_wrong_password(): void
    {
        // User NO tiene 2FA activa; DELETE con password incorrecto debe fallar.
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->deleteJson(uri: '/api/v1/auth/2fa/totp', data: [
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath(path: 'message', expect: 'Password incorrecto.');
    }

    #[Test]
    public function test_destroy_creates_audit_row_and_logs_warning(): void
    {
        // Caso feliz: 2FA activa + current_totp_code válido → 200,
        // OtpChallenge audit row + Log::warning('2fa.disabled').
        ['secret' => $secret] = $this->userWith2fa();
        $validCode = $this->totpCodeFor(base32Secret: $secret);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        Log::spy();

        $response = $this->deleteJson(uri: '/api/v1/auth/2fa/totp', data: [
            'current_totp_code' => $validCode,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'message', expect: '2FA deshabilitada.');

        // Audit row en OtpChallenge con action=disable_2fa + method=totp_code.
        $this->assertDatabaseHas(table: 'otp_challenges', data: [
            'user_id' => $this->user->id,
            'type' => OtpChallenge::TYPE_TOTP,
            'consumed_at' => now()->toDateTimeString(),
        ]);

        $audit = OtpChallenge::query()
            ->where(column: 'user_id', operator: '=', value: $this->user->id)
            ->where(column: 'type', operator: '=', value: OtpChallenge::TYPE_TOTP)
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame(expected: 'disable_2fa', actual: $audit->context['action']);
        $this->assertSame(expected: 'totp_code', actual: $audit->context['method']);

        // Log::warning('2fa.disabled', [user_id, ip, method]).
        Log::shouldHaveReceived(method: 'warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === '2fa.disabled'
                    && $context['user_id'] === $this->user->id
                    && $context['method'] === 'totp_code';
            })
            ->once();
    }

    #[Test]
    public function test_destroy_returns_401_when_unauthenticated(): void
    {
        // Sin Sanctum::actingAs → middleware auth responde 401.
        $response = $this->deleteJson(uri: '/api/v1/auth/2fa/totp', data: [
            'password' => 'secret-password',
        ]);

        $response->assertStatus(401);
    }
}
