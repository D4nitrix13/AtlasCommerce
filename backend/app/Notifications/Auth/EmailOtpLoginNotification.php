<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Support\LocalizedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación de código OTP por email (RF-AUTH-007).
 *
 * Internacionalizada via `App\Support\LocalizedMail` (RF-USA-044):
 * el subject + body se traducen según el locale del User. Si el User
 * no tiene locale, cae a `app()->getLocale()` y luego a 'es'.
 *
 * El código está en `context.code` para que el template lo renderice.
 * NO se persiste el código en plain en DB (ver OtpChallenge::code_hash).
 */
final class EmailOtpLoginNotification extends Notification
{
    use Queueable;

    /**
     * @param  string  $code  OTP en texto plano (sólo en memoria).
     * @param  int  $expiresInMinutes  Minutos hasta expiración.
     */
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
        return LocalizedMail::for($notifiable, 'auth.email_otp')
            ->withCode($this->code, 'code_label')
            ->expiresIn($this->expiresInMinutes)
            ->toMailMessage(appName: 'AtlasCommerce');
    }
}
