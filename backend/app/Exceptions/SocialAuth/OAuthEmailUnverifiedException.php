<?php

declare(strict_types=1);

namespace App\Exceptions\SocialAuth;

/**
 * El provider reportó `email_verified = false` para Google.
 *
 * Facebook no expone este flag — se trata como verificado cuando
 * el email viene presente (decisión R9 del proposal). Si el email
 * viene null en Facebook, se lanza `OAuthEmailMissingException` (futuro).
 *
 * Defense-in-depth contra account takeover: alguien con control
 * temporal de un email no verificado no debería poder crear cuenta
 * ni linkearla.
 */
final class OAuthEmailUnverifiedException extends OAuthException
{
    public function __construct(public readonly string $provider)
    {
        parent::__construct(
            message: 'El proveedor reportó que tu correo no está verificado. Verificá tu correo en Google y reintentá.',
        );
    }

    public function oauthCode(): string
    {
        return 'oauth_email_unverified';
    }

    /**
     * @return array<string, string>
     */
    public function extras(): array
    {
        return ['provider' => $this->provider];
    }
}
