<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación con el código de recuperación de contraseña.
 *
 * Se envía cuando un usuario solicita restablecer su contraseña via
 * POST /auth/forgot-password. El código es de 6 dígitos numéricos,
 * válido por 15 minutos (configurable via env PASSWORD_RESET_TTL_MINUTES).
 *
 * Solo se almacena el hash del código en la BD — el código en texto
 * plano vive solo en este correo (y en el log en dev). Si este correo
 * es interceptado, el atacante puede restablecer la contraseña del
 * usuario antes de que expire el código.
 *
 * Si el usuario NO solicitó el código, debe ignorar el correo.
 */
class PasswordResetCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $code  Código OTP en texto plano (solo en el correo).
     * @param  string  $appName  Nombre de la aplicación para el asunto y saludo.
     * @param  int  $expiresMinutes  Tiempo de expiración del código.
     * @param  string  $supportEmail  Email de soporte para contacto.
     */
    public function __construct(
        public readonly string $code,
        public readonly string $appName,
        public readonly int $expiresMinutes,
        public readonly string $supportEmail,
    ) {}

    /**
     * Canal de entrega: solo mail.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Construye el mensaje de correo.
     *
     * Renderiza la vista Markdown `resources/views/emails/auth/password-reset-code.blade.php`.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(subject: "Código de recuperación — {$this->appName}")
            ->markdown(
                view: 'emails.auth.password-reset-code',
                data: [
                    'appName' => $this->appName,
                    'code' => $this->code,
                    'expiresMinutes' => $this->expiresMinutes,
                    'supportEmail' => $this->supportEmail,
                    'userName' => $notifiable->first_name ?? null,
                ],
            );
    }
}
