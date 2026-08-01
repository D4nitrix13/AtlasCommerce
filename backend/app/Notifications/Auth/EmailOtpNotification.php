<?php

namespace App\Notifications\Auth;

use App\Models\Stores\Store;
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
 * Soporte multi-tienda: si se pasa un modelo Store, el correo usa el logo,
 * nombre y redes sociales de esa tienda. Si no se pasa, usa los fallbacks
 * globales de config/brand.php.
 */
class EmailOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly int $expiresMinutes,
        private readonly ?Store $store = null,
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
     * Resuelve el branding desde la tienda si se proporciona, usando
     * logo, nombre y redes sociales de la tienda en la BD. Si no hay
     * tienda, usa los fallbacks globales de config/brand.php.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appName = $this->store?->name ?? (string) config('app.name');

        return (new MailMessage)
            ->subject("Código de seguridad - {$appName}")
            ->view(
                'emails.auth.email-otp',
                data: [
                    'appName' => $appName,
                    'code' => $this->code,
                    'expiresMinutes' => $this->expiresMinutes,
                    'logoUrl' => $this->resolveLogoUrl(),
                    'userName' => $notifiable->first_name ?? null,
                    'supportEmail' => config(
                        'brand.support_email',
                        default: 'support@example.test',
                    ),
                    'address' => $this->store?->address ?? '',
                    'facebookUrl' => $this->resolveSocialUrl('facebook'),
                    'instagramUrl' => $this->resolveSocialUrl('instagram'),
                    'facebookIconUrl' => asset(
                        $this->resolveSocialIconPath('facebook'),
                    ),
                    'instagramIconUrl' => asset(
                        $this->resolveSocialIconPath('instagram'),
                    ),
                ],
            );
    }

    /**
     * Resuelve la URL del logo desde la tienda o el fallback global.
     */
    private function resolveLogoUrl(): string
    {
        $path = $this->store?->logo
            ?? (string) config('brand.default_logo_path', default: 'images/shared/brand/app-logo.webp');

        return asset($path);
    }

    /**
     * Resuelve la URL de una red social desde la tienda o null si no existe.
     */
    private function resolveSocialUrl(string $platform): ?string
    {
        if (! $this->store) {
            return null;
        }

        return $this->store
            ->socialLinks()
            ->where('platform', $platform)
            ->where('is_active', true)
            ->value('url');
    }

    /**
     * Resuelve la ruta del icono de una red social.
     *
     * Si hay tienda, usa images/stores/{slug}/social/{platform}.webp.
     * Si no, usa el fallback compartido en images/shared/icons/social/.
     */
    private function resolveSocialIconPath(string $platform): string
    {
        if ($this->store) {
            $storeIcon = "images/stores/{$this->store->slug}/social/{$platform}.webp";

            if (storage_path("app/public/{$storeIcon}") || file_exists(public_path($storeIcon))) {
                return $storeIcon;
            }
        }

        return (string) config(
            "brand.social.{$platform}_icon_path",
            default: "images/shared/icons/social/{$platform}.webp",
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
