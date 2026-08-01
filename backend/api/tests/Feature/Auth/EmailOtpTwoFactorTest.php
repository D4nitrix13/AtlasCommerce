<?php

namespace Tests\Feature\Auth;

use App\Models\Auth\User;
use App\Notifications\Auth\EmailOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Test de integración del flujo completo de 2FA con Email OTP.
 *
 * Valida el ciclo de vida completo:
 * 1. Estado inicial: 2FA deshabilitado.
 * 2. Envío de código OTP.
 * 3. Verificación del código y activación de 2FA.
 * 4. Verificación en BD de `two_factor_settings`, `two_factor_methods` y `otp_challenges`.
 * 5. Regeneración y consulta de recovery codes.
 * 6. Desactivación de 2FA.
 *
 * También valida el caso de error: código OTP inválido retorna 422.
 *
 * El test usa `Notification::fake()` para interceptar el correo y extraer
 * el código OTP directamente de la notificación (vía ReflectionProperty),
 * ya que el código nunca se almacena en texto plano en la BD.
 */
class EmailOtpTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Valida el flujo completo de activación, uso de recovery codes y desactivación de 2FA.
     *
     * Pasos:
     * 1. Consulta estado inicial → 2FA deshabilitado.
     * 2. Envía código OTP → respuesta con expiración y destino.
     * 3. Extrae el OTP de la notificación fakeada (via Reflection).
     * 4. Verifica el código → 2FA activado.
     * 5. Valida en BD: settings, methods y challenge.
     * 6. Regenera recovery codes → 8 códigos nuevos.
     * 7. Consulta estadísticas → total: 8, used: 0, remaining: 8.
     * 8. Desactiva 2FA → settings con enabled: false.
     */
    public function test_authenticated_user_can_complete_email_otp_two_factor_flow(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        // Paso 1: Estado inicial — 2FA debe estar deshabilitado.
        $this->getJson('/api/auth/2fa')
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('preferred_method', null);

        // Paso 2: Envío de código OTP.
        $this->postJson('/api/auth/2fa/email/send')
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'expires_at',
                'destination',
            ]);

        // Paso 3: Extraer el código OTP de la notificación fakeada.
        // Se usa ReflectionProperty para acceder al valor privado `code`
        // de la notificación, ya que el código nunca se persiste en texto plano.
        $otpCode = null;

        Notification::assertSentTo(
            $user,
            EmailOtpNotification::class,
            function (EmailOtpNotification $notification) use (&$otpCode): bool {
                $otpCode = $this->readPrivateProperty(
                    object: $notification,
                    property: 'code',
                );

                return is_string($otpCode) && $otpCode !== '';
            },
        );

        $this->assertNotNull(
            actual: $otpCode,
            message: 'El código OTP no fue capturado desde la notificación.',
        );

        // Paso 4: Verificar el código y activar 2FA.
        $this->postJson('/api/auth/2fa/email/verify', [
            'code' => $otpCode,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Email OTP enabled successfully.');

        // Paso 5: Validar en BD que 2FA se activó correctamente.
        $this->assertDatabaseHas('two_factor_settings', [
            'user_id' => $user->id,
            'enabled' => true,
            'preferred_method' => 'email_otp',
        ]);

        $this->assertDatabaseHas('two_factor_methods', [
            'user_id' => $user->id,
            'method' => 'email_otp',
            'active' => true,
            'verified' => true,
        ]);

        // El challenge debe estar marcado como usado.
        $this->assertDatabaseHas('otp_challenges', [
            'user_id' => $user->id,
            'method' => 'email_otp',
            'purpose' => 'enable_2fa',
            'used' => true,
        ]);

        // Paso 6: Regenerar recovery codes.
        $this->postJson('/api/auth/2fa/recovery-codes/regenerate')
            ->assertOk()
            ->assertJsonPath('message', 'Recovery codes regenerated successfully.')
            ->assertJsonCount(8, 'codes');

        // Paso 7: Consultar estadísticas de recovery codes.
        $this->getJson('/api/auth/2fa/recovery-codes')
            ->assertOk()
            ->assertJsonPath('total', 8)
            ->assertJsonPath('used', 0)
            ->assertJsonPath('remaining', 8);

        // Paso 8: Desactivar 2FA.
        $this->postJson('/api/auth/2fa/disable')
            ->assertOk()
            ->assertJsonPath('message', 'Two-factor authentication disabled successfully.');

        // Validar que 2FA quedó deshabilitado en BD.
        $this->assertDatabaseHas('two_factor_settings', [
            'user_id' => $user->id,
            'enabled' => false,
            'preferred_method' => null,
        ]);
    }

    /**
     * Valida que un código OTP inválido sea rechazado con error 422.
     *
     * Envía un código que no coincide con el generado y verifica que:
     * 1. La respuesta sea 422 con mensaje de error.
     * 2. 2FA NO se active en la BD.
     */
    public function test_invalid_email_otp_is_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/2fa/email/send')
            ->assertOk();

        // Se envía un código inválido (no coincide con el generado).
        $this->postJson('/api/auth/2fa/email/verify', [
            'code' => '000000',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid or expired security code.');

        // 2FA no debe haberse activado.
        $this->assertDatabaseMissing('two_factor_settings', [
            'user_id' => $user->id,
            'enabled' => true,
            'preferred_method' => 'email_otp',
        ]);
    }

    /**
     * Lee una propiedad privada de un objeto usando Reflection.
     *
     * Se usa para extraer el código OTP de la notificación fakeada,
     * ya que el código es una propiedad `private readonly` y no tiene getter.
     *
     * ReflectionProperty es seguro aquí porque se usa exclusivamente en tests.
     */
    private function readPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new ReflectionProperty($object, $property);

        return $reflection->getValue($object);
    }
}
