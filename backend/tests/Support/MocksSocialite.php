<?php

declare(strict_types=1);

namespace Tests\Support;

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Helper para mockear el facade `Socialite` en tests Feature.
 *
 * Razón de existir (tasks §2.1 task 1.8, design §3.1): `Socialite::fake()`
 * es la API oficial de Socialite 5.x pero tiene un coverage limitado
 * para variantes como `email_verified = false` o para override del
 * `redirectUrl()` per-host. Este trait encapsula el patrón Mockery
 * facade (consistente con el resto de `tests/`) y queda como el único
 * seam de testing para el flujo OAuth.
 *
 * Convenciones:
 *   - `mockSocialiteRedirect($provider, $url)` → para tests del
 *     endpoint `/redirect`. Devuelve un 302 a `$url` cuando el
 *     controller llama `Socialite::driver($p)->redirectUrl($u)->redirect()`.
 *   - `mockSocialiteUser(...)` → para tests del endpoint `/callback`.
 *     Configura `user()` para devolver un `SocialiteUser` con los
 *     atributos provistos (incl. `email_verified` que vive en
 *     `$user->user['email_verified']` según discovery #210).
 */
trait MocksSocialite
{
    /**
     * Stub el redirect de Socialite para que el controller reciba
     * un `RedirectResponse` apuntando a `$url`.
     *
     * Llamadas encadenadas cubiertas: `driver($provider)->redirectUrl($url)->redirect()`.
     */
    protected function mockSocialiteRedirect(string $provider, string $url): void
    {
        $driver = $this->createSocialiteDriverMock(provider: $provider);
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('redirect')->andReturn(redirect()->to($url));
    }

    /**
     * Stub `Socialite::driver($provider)->user()` para devolver un
     * `SocialiteUser` con email_verified controlable.
     *
     * Devuelve el objeto creado para que el test pueda inspeccionarlo
     * después si lo necesita.
     */
    protected function mockSocialiteUser(
        string $provider,
        string $providerId,
        string $email,
        string $name = 'Test User',
        ?string $avatar = null,
        bool $emailVerified = true,
    ): SocialiteUser {
        $oauthUser = new SocialiteUser();
        $oauthUser->id = $providerId;
        $oauthUser->nickname = null;
        $oauthUser->name = $name;
        $oauthUser->email = $email;
        $oauthUser->avatar = $avatar;
        // `email_verified` vive en el array `user` del Socialite raw
        // payload. Google provider lo lee desde acá (discovery #210).
        $oauthUser->setRawAttributes(attributes: [
            'id' => $providerId,
            'nickname' => null,
            'name' => $name,
            'email' => $email,
            'avatar' => $avatar,
            'avatar_original' => null,
            'user' => [
                'email_verified' => $emailVerified,
                'id' => $providerId,
            ],
        ]);

        $driver = $this->createSocialiteDriverMock(provider: $provider);
        $driver->shouldReceive('user')->andReturn($oauthUser);

        return $oauthUser;
    }

    /**
     * Helper interno — mock del provider instance (lo que devuelve
     * `Socialite::driver($provider)`).
     */
    private function createSocialiteDriverMock(string $provider): \Mockery\MockInterface
    {
        Socialite::shouldReceive('driver')->with($provider)->andReturnSelf();

        return Socialite::shouldReceive('driver')->with($provider);
    }
}
