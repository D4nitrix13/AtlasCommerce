<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Auth\SocialAccount;
use Core\Tenant\Application\StoreContextService;
use Laravel\Socialite\Contracts\User as ProviderUserContract;

/**
 * Resuelve la identidad OAuth contra el modelo interno.
 *
 * Responsabilidades (PR 1 = solo `findByProviderId`; PR 2 agrega
 * `findOrCreateFromProvider` con todo el flujo de linking + atomic
 * signup + admin rejection).
 *
 * Diseño:
 *   - Store context: cada operación que cree filas nuevas (`PR 2`)
 *     usa `$this->storeContext->getStoreId()` para crear el
 *     `customer_stores` binding al tenant activo.
 *   - Sin eager-write en `findByProviderId`: cero side-effects,
 *     solo lectura.
 */
class SocialAccountService
{
    public function __construct(
        private readonly StoreContextService $storeContext,
    ) {}

    /**
     * Busca un `SocialAccount` por `(provider, provider_user_id)`.
     *
     * Retorna `null` si no existe — el caller decide si crear uno
     * nuevo o rechazar (ver `findOrCreateFromProvider` en PR 2).
     */
    public function findByProviderId(string $provider, string $providerUserId): ?SocialAccount
    {
        return SocialAccount::query()
            ->where(column: 'provider', operator: '=', value: $provider)
            ->where(column: 'provider_user_id', operator: '=', value: $providerUserId)
            ->first();
    }

    /**
     * Stub de `findOrCreateFromProvider` — la implementación completa
     * (con `DB::transaction`, email_verified gate, admin_signup_disabled,
     * email_conflict check) llega en PR 2.
     *
     * Marcada `@phpstan-ignore-next-line` (placeholder intencional) hasta
     * que se implemente.
     *
     * @param  ProviderUserContract  $providerUser  usuario del provider
     * @param  string  $host  host de la request (para isAdminHost check)
     */
    public function findOrCreateFromProvider(ProviderUserContract $providerUser, string $host): \App\Models\Auth\User
    {
        // PR 2: implementar las 7 ramas del spec (A3, A4, A5, A6, A7,
        // A8, A12). Por ahora lanza para que cualquier test que la
        // llame falle ruidosamente.
        throw new \LogicException(
            message: 'findOrCreateFromProvider se implementa en PR 2 (tasks §2.2 task 2.2).',
        );
    }

    /**
     * Crea un `SocialAccount` bound al `$user` desde la respuesta del
     * provider. Implementación completa en PR 2 (task 2.1).
     */
    public function createSocialAccount(\App\Models\Auth\User $user, ProviderUserContract $providerUser): SocialAccount
    {
        throw new \LogicException(
            message: 'createSocialAccount se implementa en PR 2 (tasks §2.2 task 2.1).',
        );
    }
}
