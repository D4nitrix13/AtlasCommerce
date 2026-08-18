<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\SocialAuth\OAuthAdminSignupDisabledException;
use App\Exceptions\SocialAuth\OAuthEmailConflictException;
use App\Exceptions\SocialAuth\OAuthEmailUnverifiedException;
use App\Models\Auth\SocialAccount;
use App\Models\Auth\User;
use App\Models\Customers\Customer;
use App\Models\Stores\CustomerStore;
use App\Support\Auth\OAuthRedirectTarget;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProviderUserContract;

/**
 * Resuelve la identidad OAuth contra el modelo interno.
 *
 * Responsabilidades:
 *   - `findByProviderId()` (PR 1): lookup puro por (provider, provider_user_id)
 *   - `findOrCreateFromProvider()` (PR 2a): flujo completo que cubre
 *     los 7 escenarios del spec (A3-A8 + A12):
 *       - existing social → return bound user (sin writes)
 *       - email_verified=false → throw OAuthEmailUnverifiedException
 *       - email ya existe en users → throw OAuthEmailConflictException
 *       - admin subdomain + no user match → throw OAuthAdminSignupDisabledException
 *       - verified + no match → DB::transaction (User + Customer + CustomerStore)
 *       - transaction falla → rollback completo (0 rows)
 *
 *   - `createSocialAccount()` (PR 2a): persiste el binding user ↔ provider
 *
 * Decisiones de diseño:
 *   - R9: Google verifica `email_verified` del payload raw; Facebook se
 *     trata como verificado cuando el email viene presente.
 *   - R1: NO auto-link de email (decisión P-C del proposal #204). Si
 *     el email ya existe en users, se rechaza con `oauth_email_conflict`
 *     para que el user use el flow explícito de "vincular cuenta".
 *   - P-C: nuevo OAuth user → role=null + customer_store para el
 *     tenant activo. NO crea user_stores (eso es admin-only).
 *   - R7: password random bcrypt placeholder (`Str::random(64)`).
 *     El cast `hashed` del User model hashea automáticamente.
 *   - R12: unique composite `(provider, provider_user_id)` enforce
 *     via DB. La transacción aísla la inserción.
 */
class SocialAccountService
{
    public function __construct(
        private readonly StoreContextService $storeContext,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * Busca un `SocialAccount` por `(provider, provider_user_id)`.
     */
    public function findByProviderId(string $provider, string $providerUserId): ?SocialAccount
    {
        return SocialAccount::query()
            ->where(column: 'provider', operator: '=', value: $provider)
            ->where(column: 'provider_user_id', operator: '=', value: $providerUserId)
            ->first();
    }

    /**
     * Crea un `SocialAccount` que vincula al `User` con la identidad externa.
     *
     * Asume que el binding `(provider, provider_user_id)` no existe aún
     * (la transacción en `findOrCreateFromProvider` garantiza esto).
     */
    public function createSocialAccount(User $user, ProviderUserContract $providerUser, string $provider): SocialAccount
    {
        return SocialAccount::create(attributes: [
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => (string) $providerUser->getId(),
        ]);
    }

    /**
     * Resuelve o crea el `User` interno desde una respuesta de provider.
     *
     * Branches (en orden de evaluación):
     *   1. `social_accounts` tiene fila → return bound user (no writes)
     *   2. `email_verified = false` (Google only) → OAuthEmailUnverifiedException
     *   3. `users.email` ya existe → OAuthEmailConflictException (R1)
     *   4. host es admin.* + no existing match → OAuthAdminSignupDisabledException
     *   5. OK → DB::transaction create User + Customer + CustomerStore + SocialAccount
     *
     * @throws OAuthEmailUnverifiedException
     * @throws OAuthEmailConflictException
     * @throws OAuthAdminSignupDisabledException
     */
    public function findOrCreateFromProvider(
        ProviderUserContract $providerUser,
        string $host,
        string $provider,
    ): User {
        $providerUserId = (string) $providerUser->getId();
        $email = $providerUser->getEmail();

        // 1. Existing social binding wins.
        $existing = $this->findByProviderId(provider: $provider, providerUserId: $providerUserId);
        if ($existing !== null) {
            return $existing->user;
        }

        // 2. Email-verified gate (R9).
        if (! $this->isEmailVerified($provider, $providerUser)) {
            throw new OAuthEmailUnverifiedException(provider: $provider);
        }

        // 3. Email conflict detection (R1) — solo si el provider
        // devolvió email. Si email es null, skip (Google puede no
        // compartir email si el scope es limitado).
        if ($email !== null) {
            $existingByEmail = User::query()
                ->where(column: 'email', operator: '=', value: $email)
                ->first();
            if ($existingByEmail !== null) {
                throw new OAuthEmailConflictException(email: $email);
            }
        }

        // 4. Admin signup disabled (P-C del proposal).
        if (OAuthRedirectTarget::isAdminHost(host: $host)) {
            throw new OAuthAdminSignupDisabledException(provider: $provider);
        }

        // 5. Atomic create (R12).
        return $this->db->transaction(function () use ($providerUser, $provider, $email): User {
            $name = $providerUser->getName() ?? 'OAuth User';
            $nameParts = explode(separator: ' ', string: trim($name), limit: 2);
            $firstName = $nameParts[0] ?? 'OAuth';
            $lastName = $nameParts[1] ?? 'User';

            $user = User::create(attributes: [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                // R7: random bcrypt placeholder (User cast hashea).
                'password' => Str::random(length: 64),
                'is_active' => true,
                'registered_at' => now(),
            ]);

            // `email_verified_at` no está en el fillable del User
            // model — usamos forceFill para bypassear. Decisión de
            // seguridad: OAuth-created users siempre están verificados
            // (R9 + R1: si el email colisiona, OAuthEmailConflictException
            // se lanza ANTES de llegar a este punto).
            $user->forceFill(attributes: ['email_verified_at' => now()])->save();

            $customer = Customer::create(attributes: [
                'user_id' => $user->id,
                'customer_type' => 'retail',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'is_active' => true,
            ]);

            $storeId = $this->storeContext->getStoreId();
            if ($storeId === null) {
                // Sin tenant context el binding falla — propagamos
                // como RuntimeException para que el controller decida.
                throw new \RuntimeException(
                    message: 'Store context no resuelto — el middleware store.domain no se ejecutó antes del callback OAuth.',
                );
            }

            CustomerStore::create(attributes: [
                'customer_id' => $customer->id,
                'store_id' => $storeId,
            ]);

            // P-C policy: nuevos OAuth customers necesitan user_store
            // para que `hasAccessToStore()` los deje pasar el tenant
            // gate del callback (mismo check que `AuthController::login`).
            // Sin este row, el controller responde 422 y el user no
            // puede finalizar el flow.
            $user->stores()->attach($storeId, ['role_id' => null]);

            $this->createSocialAccount(user: $user, providerUser: $providerUser, provider: $provider);

            return $user;
        });
    }

    /**
     * Determina si el email devuelto por el provider está verificado.
     *
     * R9 del proposal:
     *   - Google: `$providerUser->user['email_verified'] === true`
     *   - Facebook: no expone el flag — se trata como verificado
     *     cuando el email viene presente.
     */
    private function isEmailVerified(string $provider, ProviderUserContract $providerUser): bool
    {
        if ($provider === 'google') {
            $rawUser = $providerUser->user ?? [];

            return ($rawUser['email_verified'] ?? false) === true;
        }

        // Facebook (y cualquier provider futuro): email presente == verificado.
        return $providerUser->getEmail() !== null && $providerUser->getEmail() !== '';
    }
}
