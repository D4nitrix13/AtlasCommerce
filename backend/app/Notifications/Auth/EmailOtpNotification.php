<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación para enviar códigos OTP por correo electrónico.
 *
 * Se usa como parte del flujo de 2FA: envía un código numérico de un solo uso
 * con tiempo de expiración al correo del usuario.
 *
 * Canal de entrega: `mail` (SMTP).
 *
 * La vista personalizada `emails.auth.email-otp` renderiza el correo con
 * el layout base `x-email.layout` que incluye branding (logo, colores, redes sociales).
 *
 * Los datos de branding (logo, dirección, redes sociales) se leen de
 * `config/brand.php` que a su vez depende de variables de entorno en `.env`.
 */
class EmailOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly int $expiresMinutes,
    ) {
        //
    }

    /**
     * Canales de entrega de la notificación.
     *
     * Solo usa el canal `mail` para enviar el código OTP por correo.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Construye la representación del correo con el código OTP.
     *
     * Usa una vista Blade personalizada (`emails.auth.email-otp`) en lugar
     * del MailMessage por defecto de Laravel, para permitir diseño HTML
     * completo con branding de la aplicación.
     *
     * El asunto del correo incluye el nombre de la app para facilitar
     * la identificación en la bandeja de entrada del usuario.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appName = (string) config(key: 'app.name');

        return (new MailMessage)
            ->subject(subject: "Código de seguridad - {$appName}")
            ->view(
                view: 'emails.auth.email-otp',
                data: [
                    'appName' => $appName,
                    'code' => $this->code,
                    'expiresMinutes' => $this->expiresMinutes,
                    'logoUrl' => asset(
                        path: (string) config(
                            key: 'brand.default_logo_path',
                            default: 'images/shared/brand/app-logo.webp',
                        ),
                    ),
                    'userName' => $notifiable->first_name ?? null,
                    'supportEmail' => config(
                        key: 'brand.support_email',
                        default: 'support@example.test',
                    ),
                    'address' => '',
                    'facebookUrl' => null,
                    'instagramUrl' => null,
                    'facebookIconUrl' => asset(
                        path: (string) config(
                            key: 'brand.social.facebook_icon_path',
                            default: 'images/shared/icons/social/facebook.webp',
                        ),
                    ),
                    'instagramIconUrl' => asset(
                        path: (string) config(
                            key: 'brand.social.instagram_icon_path',
                            default: 'images/shared/icons/social/instagram.webp',
                        ),
                    ),
                ],
            );
    }

    /**
     * Representación en array de la notificación (para BD de notificaciones).
     *
     * No se usa para el canal mail, pero se requiere por contrato de Laravel.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
