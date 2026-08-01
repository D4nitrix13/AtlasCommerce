<?php

namespace App\Console\Commands;

use App\Models\Auth\User;
use App\Models\Stores\Store;
use App\Notifications\Auth\EmailOtpNotification;
use Illuminate\Console\Command;

/**
 * Comando Artisan para previsualizar el correo de OTP de 2FA.
 *
 * Envía un correo OTP de prueba a una dirección específica para revisar
 * el diseño y formato en un servidor de correo local (Mailpit).
 *
 * Uso:
 * ```bash
 * php artisan mail:preview-otp
 * php artisan mail:preview-otp admin@store.com
 * php artisan mail:preview-otp admin@store.com --code=123456 --minutes=5
 * php artisan mail:preview-otp admin@store.com --store=kitsune
 * php artisan mail:preview-otp admin@store.com --store=panda-estampados
 * ```
 *
 * Seguridad: el comando se niega a ejecutarse en el entorno `production`
 * para evitar envíos accidentales de correos de prueba a usuarios reales.
 *
 * Mailpit debe estar ejecutándose en `http://127.0.0.1:8025` para
 * capturar y previsualizar los correos enviados.
 */
class MailPreviewOtpCommand extends Command
{
    protected $signature = 'mail:preview-otp
        {email=admin@store.com : Correo de prueba}
        {--code=420912 : Código OTP de prueba}
        {--minutes=10 : Minutos de expiración}
        {--store= : Slug de la tienda para branding (usa fallback global si se omite)}';

    protected $description = 'Envía un correo OTP de prueba para revisar el diseño en Mailpit.';

    public function handle(): int
    {
        // Protección contra ejecución accidental en producción.
        if (config('app.env') === 'production') {
            $this->error(string: 'Este comando no debe ejecutarse en producción.');

            return self::FAILURE;
        }

        $email = (string) $this->argument(key: 'email');
        $code = (string) $this->option(key: 'code');
        $expiresMinutes = (int) $this->option(key: 'minutes');
        $storeSlug = (string) $this->option(key: 'store');

        $store = $storeSlug
            ? Store::where('slug', $storeSlug)->firstOrFail()
            : null;

        if ($store) {
            $this->info(string: "Usando branding de: {$store->name} ({$store->slug})");
        } else {
            $this->info(string: 'Usando branding global (fallback)');
        }

        // Se crea un usuario ficticio en memoria (sin persistir en BD)
        // para simular el contexto que espera la notificación.
        $user = new User;

        $user->first_name = 'Test';
        $user->last_name = 'Preview';
        $user->email = $email;

        $user->notify(
            new EmailOtpNotification(code: $code, expiresMinutes: $expiresMinutes, store: $store),
        );

        $this->info(string: "Correo OTP de prueba enviado a {$email}.");
        $this->info(string: 'Revisa Mailpit en http://127.0.0.1:8025');

        return self::SUCCESS;
    }
}
