<?php

declare(strict_types=1);

namespace App\Exceptions\SocialAuth;

/**
 * El email del provider ya está registrado en `users` pero SIN un
 * `SocialAccount` que los conecte.
 *
 * Política de linking (decision #204 / R1 del proposal): NO se hace
 * auto-link silencioso. Si el user quiere vincular el provider a su
 * cuenta, debe hacerlo desde el dashboard de seguridad con un flow
 * explícito. Esta excepción evita account takeover vía colisión de
 * email (un OAuth user podría volverse admin del tenant si la cuenta
 * pre-existente tiene `user_stores`).
 */
final class OAuthEmailConflictException extends OAuthException
{
    public function __construct(public readonly string $email)
    {
        parent::__construct(
            message: "El correo {$email} ya está registrado. Iniciá sesión con tu contraseña o usá el flujo de 'vincular cuenta' desde el dashboard de seguridad.",
        );
    }

    public function oauthCode(): string
    {
        return 'oauth_email_conflict';
    }

    /**
     * @return array<string, string>
     */
    public function extras(): array
    {
        return ['email' => $this->email];
    }
}
