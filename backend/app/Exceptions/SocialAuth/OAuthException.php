<?php

declare(strict_types=1);

namespace App\Exceptions\SocialAuth;

use RuntimeException;

/**
 * Base de las excepciones del flujo OAuth.
 *
 * Cada subclase concreta declara:
 *   - `oauthCode()` → código estable que se serializa en la respuesta
 *     (e.g. 'oauth_email_conflict'). Frontend mapea este código a un
 *     i18n key (`auth.login.error.{code}`).
 *   - `extras()` → campos adicionales que se mergean al envelope
 *     (`email`, `provider`, etc.).
 *
 * El `OAuthErrorResource` extrae estos valores vía `from($e)`.
 */
abstract class OAuthException extends RuntimeException
{
    /**
     * Código estable y único por tipo de fallo.
     *
     * El frontend lo usa como i18n key suffix (`auth.login.error.{code}`)
     * y como discriminator para analytics. NO incluir datos dinámicos.
     */
    abstract public function oauthCode(): string;

    /**
     * Campos adicionales para el envelope JSON de error.
     *
     * Default vacío — solo las subclases con datos relevantes lo
     * override (ej. `email`, `provider`).
     *
     * @return array<string, mixed>
     */
    public function extras(): array
    {
        return [];
    }
}
