<?php

declare(strict_types=1);

namespace App\Exceptions\SocialAuth;

/**
 * OAuth no permite crear cuentas de admin en subdominios `admin.*`.
 *
 * Política (decision #204 / P-C del proposal): un OAuth user nuevo
 * solo puede ser `customer` del tenant activo. Para staff/admin, el
 * admin ya debe existir (pre-asignado vía `DefaultAdminSeeder` o un
 * invite flow). Esto cierra el vector de privilege escalation: un
 * usuario cualquiera con Google no puede volverse admin de Panda
 * haciendo clic en "Continuar con Google" desde `admin.panda-estampados.com`.
 */
final class OAuthAdminSignupDisabledException extends OAuthException
{
    public function __construct(public readonly string $provider)
    {
        parent::__construct(
            message: 'El inicio de sesión con redes sociales no está disponible para cuentas administrativas. Pedile al administrador que cree tu cuenta.',
        );
    }

    public function oauthCode(): string
    {
        return 'oauth_admin_signup_disabled';
    }

    /**
     * @return array<string, string>
     */
    public function extras(): array
    {
        return ['provider' => $this->provider];
    }
}
