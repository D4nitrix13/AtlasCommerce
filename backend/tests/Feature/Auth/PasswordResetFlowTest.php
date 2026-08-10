<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\PasswordResetToken;
use App\Models\Auth\User;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del flujo de recuperación de contraseña:
 *
 *  - POST /auth/forgot-password → genera código OTP, hashea, envía email.
 *  - POST /auth/reset-password → valida código, cambia password, auto-login.
 *
 * Multi-tenant: ambos endpoints validan acceso a la tienda activa.
 * Rate limiting: 3 attempts / 5 min para forgot, 5 / 15 min para reset.
 */
#[Group('auth')]
#[Group('http')]
#[Group('password-reset')]
final class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($currency, 'currency')->create();
        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => 'localhost',
            'type' => 'domain',
            'base_path' => '/',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'OldPassword1234',
            'is_active' => true,
        ]);
        $this->user->stores()->attach($this->store->id);

        // Limpiar TODOS los rate limiters de forgot-password para
        // empezar cada test con throttling limpio. El limiter se
        // acumula por email a través de tests, así que lo limpiamos
        // para el email principal Y para cualquier otro que los
        // tests puedan usar.
        $cache = $this->app->make(\Illuminate\Contracts\Cache\Repository::class);
        $cache->flush();
    }

    #[Test]
    public function forgot_password_creates_a_token_and_returns_the_code_in_dev(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'jane@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message', 'expires_minutes', 'code', 'dev']);
        // En APP_ENV=testing el helper app()->environment() devuelve 'testing',
        // NO 'production', así que el código viene en la respuesta (dev mode).
        $this->assertNotEmpty($response->json('code'));
        $this->assertSame(6, strlen((string) $response->json('code')));

        $this->assertDatabaseHas('password_reset_tokens', [
            'user_id' => $this->user->id,
            'email' => 'jane@example.com',
            'used_at' => null,
        ]);

        // Email enviado (fake de Notification).
        Notification::assertSentTo(
            $this->user,
            \App\Notifications\Auth\PasswordResetCodeNotification::class,
        );
    }

    #[Test]
    public function forgot_password_returns_a_generic_response_when_email_does_not_exist(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'noexiste@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message', 'expires_minutes']);
        $response->assertJsonMissing(['code']);
        $response->assertJsonMissing(['dev']);

        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }

    #[Test]
    public function forgot_password_returns_generic_response_for_inactive_user(): void
    {
        Notification::fake();
        $this->user->update(['is_active' => false]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'jane@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonMissing(['code']);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }

    #[Test]
    public function forgot_password_returns_generic_response_for_user_without_store_access(): void
    {
        Notification::fake();
        $otherStore = Store::factory()->create();
        $this->user->stores()->detach();
        $this->user->stores()->attach($otherStore->id);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'jane@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonMissing(['code']);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }

    #[Test]
    public function forgot_password_invalidates_previous_unused_tokens(): void
    {
        // Crear token previo sin usar.
        PasswordResetToken::query()->create([
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'token_hash' => Hash::make(value: '111111'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'jane@example.com',
        ]);

        $response->assertOk();

        // El token previo debe estar marcado como usado.
        $previousTokens = PasswordResetToken::query()
            ->where('user_id', $this->user->id)
            ->get();

        $this->assertSame(2, $previousTokens->count());

        // El token previo (con code 111111) debe estar marcado como usado.
        $previous = PasswordResetToken::query()
            ->where('user_id', $this->user->id)
            ->get()
            ->first(fn (PasswordResetToken $t): bool => Hash::check(value: '111111', hashedValue: $t->token_hash));

        $this->assertNotNull($previous);
        $this->assertNotNull($previous->used_at);
    }

    #[Test]
    public function forgot_password_throttles_after_three_attempts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/forgot-password', [
                'email' => 'jane@example.com',
            ])->assertOk();
        }

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'jane@example.com',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function forgot_password_rejects_invalid_email_format(): void
    {
        $this->postJson('/api/auth/forgot-password', [
            'email' => 'not-an-email',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function reset_password_changes_password_and_returns_auth_token(): void
    {
        $code = '654321';
        $token = PasswordResetToken::query()->create([
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'token_hash' => Hash::make(value: $code),
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'jane@example.com',
            'code' => $code,
            'password' => 'NewPassword1234',
            'password_confirmation' => 'NewPassword1234',
            'device_name' => 'web-admin',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'user' => ['id', 'email']]);

        // Password actualizado.
        $this->user->refresh();
        $this->assertTrue(Hash::check(value: 'NewPassword1234', hashedValue: $this->user->password));

        // Token marcado como usado.
        $this->assertNotNull($token->fresh()->used_at);
    }

    #[Test]
    public function reset_password_rejects_invalid_code(): void
    {
        PasswordResetToken::query()->create([
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'token_hash' => Hash::make(value: '654321'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'jane@example.com',
            'code' => '000000',
            'password' => 'NewPassword1234',
            'password_confirmation' => 'NewPassword1234',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function reset_password_rejects_expired_token(): void
    {
        PasswordResetToken::query()->create([
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'token_hash' => Hash::make(value: '654321'),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'jane@example.com',
            'code' => '654321',
            'password' => 'NewPassword1234',
            'password_confirmation' => 'NewPassword1234',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function reset_password_rejects_already_used_token(): void
    {
        $code = '654321';
        PasswordResetToken::query()->create([
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'token_hash' => Hash::make(value: $code),
            'expires_at' => now()->addMinutes(15),
            'used_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'jane@example.com',
            'code' => $code,
            'password' => 'NewPassword1234',
            'password_confirmation' => 'NewPassword1234',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function reset_password_rejects_weak_passwords(): void
    {
        $code = '654321';
        PasswordResetToken::query()->create([
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'token_hash' => Hash::make(value: $code),
            'expires_at' => now()->addMinutes(15),
        ]);

        // Muy corta (< 12 chars).
        $this->postJson('/api/auth/reset-password', [
            'email' => 'jane@example.com',
            'code' => $code,
            'password' => 'Short1',
            'password_confirmation' => 'Short1',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        // Sin mayúscula.
        $this->postJson('/api/auth/reset-password', [
            'email' => 'jane@example.com',
            'code' => $code,
            'password' => 'alllowercase1234',
            'password_confirmation' => 'alllowercase1234',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        // Sin número.
        $this->postJson('/api/auth/reset-password', [
            'email' => 'jane@example.com',
            'code' => $code,
            'password' => 'AllUpperCaseOnly',
            'password_confirmation' => 'AllUpperCaseOnly',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        // Passwords no coinciden.
        $this->postJson('/api/auth/reset-password', [
            'email' => 'jane@example.com',
            'code' => $code,
            'password' => 'NewPassword1234',
            'password_confirmation' => 'OtherPassword1234',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function reset_password_rejects_non_6_digit_code(): void
    {
        $this->postJson('/api/auth/reset-password', [
            'email' => 'jane@example.com',
            'code' => '12345', // solo 5 dígitos
            'password' => 'NewPassword1234',
            'password_confirmation' => 'NewPassword1234',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function full_flow_forgot_then_reset_with_real_code(): void
    {
        Notification::fake();

        // Step 1: forgot.
        $forgotResponse = $this->postJson('/api/auth/forgot-password', [
            'email' => 'jane@example.com',
        ]);
        $forgotResponse->assertOk();
        $code = $forgotResponse->json('code');
        $this->assertNotEmpty($code);

        // Step 2: reset con el código real.
        $resetResponse = $this->postJson('/api/auth/reset-password', [
            'email' => 'jane@example.com',
            'code' => $code,
            'password' => 'BrandNewPassword1234',
            'password_confirmation' => 'BrandNewPassword1234',
        ]);

        $resetResponse->assertOk();
        $resetResponse->assertJsonStructure(['token', 'user']);

        // Login con la nueva password funciona.
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'BrandNewPassword1234',
        ]);
        $loginResponse->assertOk();
    }
}
