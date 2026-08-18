<?php

declare(strict_types=1);

namespace App\Exceptions\SocialAuth;

/**
 * El `state` del callback NO coincide con el `state` guardado en
 * session durante el redirect.
 *
 * Socialite 5.x hace esta comparación vía `hash_equals()` (defense-in-depth
 * contra timing attacks). Cuando falla lanza `InvalidStateException`
 * que nosotros traducimos a esta para emitir el `Log::warning` y
 * responder 400 (design open Q #6).
 *
 * Defense-in-depth extra en redirect: `$request->hasSession()` debe
 * ser true antes de delegar a Socialite — ver
 * `OAuthStateMissingException`.
 */
final class OAuthStateMismatchException extends OAuthException
{
    public function __construct(public readonly string $provider)
    {
        parent::__construct(
            message: 'La sesión OAuth expiró o fue manipulada. Por favor reintentá el inicio de sesión.',
        );
    }

    public function oauthCode(): string
    {
        return 'oauth_state_mismatch';
    }

    /**
     * @return array<string, string>
     */
    public function extras(): array
    {
        return ['provider' => $this->provider];
    }
}
