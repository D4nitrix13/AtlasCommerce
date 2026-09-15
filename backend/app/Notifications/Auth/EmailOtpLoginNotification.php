<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación de código OTP por email (RF-AUTH-007).
 *
 * Se envía al usuario cuando pide login passwordless vía
 * `POST /auth/login/email-otp/request`. El código de 6 dígitos
 * viaja en el body del email (no como link — son OTP, no magic
 * links).
 *
 * El código está en `context.code` para que el template lo renderice.
 * NO se persiste el código en plain en DB (ver OtpChallenge::code_hash).
 */
final class EmailOtpLoginNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresInMinutes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(subject: 'Tu código de acceso')
            ->greeting('Hola,')
            ->line("Tu código de acceso es: **{$this->code}**")
            ->line("Vence en {$this->expiresInMinutes} minutos.")
            ->line('Si no solicitaste este código, podés ignorar este email.')
            ->salutation('— El equipo de AtlasCommerce');
    }
}
