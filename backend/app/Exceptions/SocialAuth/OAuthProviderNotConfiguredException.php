<?php

declare(strict_types=1);

namespace App\Exceptions\SocialAuth;

/**
 * El provider está aprobado pero sus credenciales (`GOOGLE_CLIENT_ID`,
 * `FACEBOOK_CLIENT_ID`, etc.) están vacías en `config('services.*')`.
 *
 * Fail-closed: en lugar de 500 genérico, respondemos 503 con código
 * específico para que el operador sepa qué variable de entorno falta
 * sin tener que leer un stack trace.
 *
 * La verificación se hace en ambos `redirect()` y `callback()` — el
 * callback también fail-closed para no escribir filas si el provider
 * no está bien configurado (R6 del proposal: localhost/dev).
 */
final class OAuthProviderNotConfiguredException extends OAuthException
{
    public function __construct(public readonly string $provider)
    {
        parent::__construct(
            message: "El proveedor '{$provider}' no está configurado en este entorno. Contactá al administrador.",
        );
    }

    public function oauthCode(): string
    {
        return 'oauth_provider_not_configured';
    }

    /**
     * @return array<string, string>
     */
    public function extras(): array
    {
        return ['provider' => $this->provider];
    }
}
