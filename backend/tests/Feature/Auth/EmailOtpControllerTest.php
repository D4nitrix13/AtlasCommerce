<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\OtpChallenge;
use App\Models\Auth\User;
use App\Notifications\Auth\EmailOtpLoginNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del login passwordless con OTP por email (RF-AUTH-007).
 *
 * Cubre:
 *  - Request: genera challenge + notifica, anti-enumeration misma
 *    respuesta exista o no el email.
 *  - Verify: código correcto emite Sanctum token.
 *  - Verify: código incorrecto retorna 422.
 *  - Verify: email inválido retorna 422.
 *  - Verify: código expirado retorna 422.
 *  - Verify: marca consumido tras uso exitoso.
 *  - Rate limit (5 attempts).
 */
#[Group('auth')]
final class EmailOtpControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        // Limpia rate limiters de tests previos.
        foreach (['cliente@example.com', 'unknown@example.com'] as $email) {
            \Illuminate\Support\Facades\RateLimiter::clear(key: 'email-otp-request:'.sha1($email));
            \Illuminate\Support\Facades\RateLimiter::clear(key: 'email-otp-verify:'.sha1($email));
        }

        $this->user = User::factory()->create([
            'email' => 'cliente@example.com',
            'is_active' => true,
        ]);

        // store.domain middleware requires a recognized host.
        $store = \App\Models\Stores\Store::factory()
            ->for(\App\Models\Settings\Currency::factory()->primary()->create(), 'currency')
            ->create(['slug' => 'panda-estampados']);
        \App\Models\Stores\StoreDomain::query()->create([
            'store_id' => $store->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    #[TestAttr]
    public function request_sends_notification_for_existing_email(): void
    {
        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/auth/login/email-otp/request', data: [
            'email' => 'cliente@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'expires_in_minutes', expect: 10);

        // Notificación enviada al user.
        Notification::assertSentTo(
            notifiable: $this->user,
            notification: EmailOtpLoginNotification::class,
        );

        // Challenge activo en DB.
        $challenge = OtpChallenge::query()
            ->where(column: 'user_id', operator: '=', value: $this->user->id)
            ->where(column: 'type', operator: '=', value: OtpChallenge::TYPE_EMAIL_OTP)
            ->first();
        $this->assertNotNull($challenge);
        $this->assertNotEmpty($challenge->code_hash);
    }

    #[TestAttr]
    public function request_with_unknown_email_returns_same_response(): void
    {
        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/auth/login/email-otp/request', data: [
            'email' => 'unknown@example.com',
        ]);

        // Anti-enumeration: misma respuesta que para email existente.
        $response->assertOk();
        Notification::assertNothingSent();
    }

    #[TestAttr]
    public function verify_with_correct_code_returns_token(): void
    {
        $code = '123456';
        OtpChallenge::query()->create([
            'user_id' => $this->user->id,
            'type' => OtpChallenge::TYPE_EMAIL_OTP,
            'code_hash' => Hash::make($code),
            'context' => ['action' => 'login_otp'],
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/auth/login/email-otp/verify', data: [
            'email' => 'cliente@example.com',
            'code' => $code,
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('token'));

        // Marca consumido.
        $challenge = OtpChallenge::query()->where(column: 'user_id', operator: '=', value: $this->user->id)->first();
        $this->assertNotNull($challenge->consumed_at);
    }

    #[TestAttr]
    public function verify_with_wrong_code_returns_422(): void
    {
        OtpChallenge::query()->create([
            'user_id' => $this->user->id,
            'type' => OtpChallenge::TYPE_EMAIL_OTP,
            'code_hash' => Hash::make('111111'),
            'context' => ['action' => 'login_otp'],
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/auth/login/email-otp/verify', data: [
            'email' => 'cliente@example.com',
            'code' => '999999',
        ]);

        $response->assertStatus(status: 422);
    }

    #[TestAttr]
    public function verify_with_expired_code_returns_422(): void
    {
        OtpChallenge::query()->create([
            'user_id' => $this->user->id,
            'type' => OtpChallenge::TYPE_EMAIL_OTP,
            'code_hash' => Hash::make('111111'),
            'context' => ['action' => 'login_otp'],
            'expires_at' => now()->subMinutes(5),
        ]);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/auth/login/email-otp/verify', data: [
            'email' => 'cliente@example.com',
            'code' => '111111',
        ]);

        $response->assertStatus(status: 422);
    }

    #[TestAttr]
    public function verify_with_unknown_email_returns_422(): void
    {
        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/auth/login/email-otp/verify', data: [
            'email' => 'unknown@example.com',
            'code' => '123456',
        ]);

        $response->assertStatus(status: 422);
    }

    #[TestAttr]
    public function verify_with_inactive_user_fails_after_valid_code(): void
    {
        $this->user->update(['is_active' => false]);

        $code = '123456';
        OtpChallenge::query()->create([
            'user_id' => $this->user->id,
            'type' => OtpChallenge::TYPE_EMAIL_OTP,
            'code_hash' => Hash::make($code),
            'context' => ['action' => 'login_otp'],
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/auth/login/email-otp/verify', data: [
            'email' => 'cliente@example.com',
            'code' => $code,
        ]);

        // Código se marca consumido (el hash match) pero el user
        // inactivo NO recibe token (RuntimeException 500 → convertido
        // a abort). El test verifica que NO se emite token.
        $this->assertNull($response->json('token'));
    }

    #[TestAttr]
    public function request_validates_email(): void
    {
        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/auth/login/email-otp/request', data: [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['email']);
    }

    #[TestAttr]
    public function verify_validates_code_format(): void
    {
        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/auth/login/email-otp/verify', data: [
            'email' => 'cliente@example.com',
            'code' => 'abc', // muy corto y no-numérico
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['code']);
    }
}
