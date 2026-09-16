<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\App;

/**
 * Helper para construir emails transaccionales con i18n (RF-USA-044).
 *
 * Laravel 11 cambió el sistema de traducciones: `lang/` (no `resources/lang/`).
 * Las traducciones de emails viven en `backend/lang/{locale}/emails.php`.
 *
 * Helper:
 * - Localiza el `subject` desde `emails.auth.<kind>.subject`.
 * - Construye un `MailMessage` con `greeting`, `intro`, `code`, `expires`,
 *   `ignore_notice`, `salutation` traducidos.
 * - Permite override del locale por notificación (ej: usuario bilingüe).
 *
 * Uso:
 * ```php
 * $mail = LocalizedMail::for($user, 'auth.email_otp')
 *     ->subjectParams(['minutes' => 10])
 *     ->withCode($code);
 *
 * return (new Notification)->toMail($notifiable, $mail);
 * ```
 */
final class LocalizedMail
{
    /**
     * @param  string  $translationKey  Key bajo `emails.*` (ej: `auth.email_otp`).
     */
    public static function for(mixed $notifiable, string $translationKey): self
    {
        $locale = self::resolveLocale($notifiable);

        App::setLocale($locale);

        return new self($translationKey, $locale);
    }

    private function __construct(
        private readonly string $translationKey,
        private readonly string $locale,
    ) {}

    /**
     * @var array<string, mixed>
     */
    private array $subjectParams = [];

    /**
     * @var list<string>
     */
    private array $introLines = [];

    private ?string $codeValue = null;

    private ?string $codeLabel = null;

    private ?int $expiresMinutes = null;

    /**
     * @param  array<string, mixed>  $params
     */
    public function subjectParams(array $params): self
    {
        $this->subjectParams = $params;

        return $this;
    }

    public function line(string $text): self
    {
        $this->introLines[] = $text;

        return $this;
    }

    public function withCode(string $code, ?string $labelKey = null): self
    {
        $this->codeValue = $code;
        $this->codeLabel = $labelKey ?? 'code_label';

        return $this;
    }

    public function expiresIn(int $minutes): self
    {
        $this->expiresMinutes = $minutes;

        return $this;
    }

    public function toMailMessage(string $appName = 'AtlasCommerce'): MailMessage
    {
        $mail = new MailMessage;
        $mail->subject($this->translate('subject'));
        $mail->greeting($this->translate('greeting'));

        // Intro line(s) — todas vienen del template.
        $intro = $this->translate('intro', ['appName' => $appName]);
        $mail->line($intro);

        foreach ($this->introLines as $extra) {
            $mail->line($extra);
        }

        // Code (OTP / reset code).
        if ($this->codeValue !== null) {
            $codeLabel = $this->codeLabel ?? 'code_label';
            $mail->line("**{$this->translate($codeLabel)}** **{$this->codeValue}**");
        }

        // Expiration notice.
        if ($this->expiresMinutes !== null) {
            $mail->line($this->translate('expires', ['minutes' => $this->expiresMinutes]));
        }

        $mail->line($this->translate('ignore_notice'));
        $mail->salutation($this->translate('salutation', ['appName' => $appName]));

        return $mail;
    }

    private function translate(string $key, array $replace = []): string
    {
        $fullKey = "emails.{$this->translationKey}.{$key}";

        return trans($fullKey, $replace, $this->locale);
    }

    /**
     * Resuelve locale del notifiable.
     *
     * Orden de prioridad:
     *   1. User->locale (columna explícita en users).
     *   2. app()->getLocale() si está seteado (runtime override).
     *   3. Fallback 'es' (locale por defecto del proyecto).
     *
     * NO usamos config('app.locale') porque config es estático y no
     * refleja App::setLocale() en runtime — útil para tests donde se
     * cambia el locale temporalmente.
     */
    private static function resolveLocale(mixed $notifiable): string
    {
        if (is_object($notifiable) && property_exists($notifiable, 'locale')) {
            $locale = $notifiable->locale ?? null;
            if (is_string($locale) && $locale !== '') {
                return $locale;
            }
        }

        $appLocale = App::getLocale();
        if (is_string($appLocale) && $appLocale !== '') {
            return $appLocale;
        }

        return 'es';
    }
}
