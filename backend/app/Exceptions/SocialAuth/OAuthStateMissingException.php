<?php

declare(strict_types=1);

namespace App\Exceptions\SocialAuth;

/**
 * El redirect endpoint fue llamado sin session cookie (Sanctum
 * stateful no presente).
 *
 * Defense-in-depth contra CSRF: aunque Socialite guarda el state en
 * session, si entramos al redirect sin sesión, ese state se va a
 * escribir a una sesión que NO existe cuando llegue el callback, y
 * la validación va a fallar con `OAuthStateMismatchException` — un
 * error confuso para el usuario. Esta excepción corta el flujo
 * temprano con un mensaje claro.
 */
final class OAuthStateMissingException extends OAuthException
{
    public function __construct(public readonly string $provider)
    {
        parent::__construct(
            message: 'Tu sesión expiró. Por favor habilitá las cookies en tu navegador y reintentá.',
        );
    }

    public function oauthCode(): string
    {
        return 'oauth_state_required';
    }

    /**
     * @return array<string, string>
     */
    public function extras(): array
    {
        return ['provider' => $this->provider];
    }
}
