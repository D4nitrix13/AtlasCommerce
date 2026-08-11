<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Auth\RecoveryCode;
use App\Models\Auth\Role;
use App\Models\Auth\TwoFactorSetting;
use App\Models\Auth\User;
use App\Services\Auth\RecoveryCodeService;
use App\Services\Auth\TotpService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de los 3 usuarios de prueba de panda-estampados.
 *
 * Cada usuario demuestra un método distinto de recuperación de
 * contraseña para testear el flow completo de los 3 métodos
 * soportados por el sistema:
 *
 *   - zuko@panda-estampados.com:  2FA (TOTP) + 10 recovery codes
 *   - sokka@panda-estampados.com: solo email (default)
 *   - katara@panda-estampados.com: solo recovery codes (10 codes, sin 2FA)
 *
 * Todos son admins de la tienda panda-estampados con la password
 * default 'password' (igual que el admin@<slug>.com de DefaultAdminSeeder).
 *
 * Idempotente: corre múltiples veces sin duplicar datos. Usa
 * firstOrCreate / updateOrCreate para las relaciones.
 *
 * Uso:
 *   php artisan db:seed --class=AuthFlowTestUsersSeeder
 *
 * Para verificar el flow de 2FA en dev:
 *   1. Login con zuko@panda-estampados.com / password → devuelve
 *      { status: '2fa_required', challenge_token: '...' }
 *   2. Calcular TOTP code con el secret expuesto en DB (ver
 *      TwoFactorSetting::secret, decryptable con Crypt::decryptString)
 *   3. POST /auth/2fa/verify-totp-login { challenge_token, code } → token
 *
 * Para verificar el flow de recovery codes:
 *   1. POST /auth/recovery-codes/regenerate con zuko (rotará los codes
 *      actuales; los nuevos codes se devuelven en la respuesta).
 *   2. katara tiene 10 recovery codes disponibles desde el seeder;
 *      usar uno via OtpChallenge::TYPE_RECOVERY_CODE (pendiente de
 *      implementar en PasswordResetTokenController en commit futuro).
 */
final class AuthFlowTestUsersSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password';
    private const SLUG = 'panda-estampados';

    public function run(): void
    {
        $store = \App\Models\Stores\Store::query()
            ->where(column: 'slug', operator: '=', value: self::SLUG)
            ->first();

        if ($store === null) {
            $this->command?->error(
                'AuthFlowTestUsersSeeder: la tienda ' . self::SLUG . ' no existe. '
                . 'Ejecuta DefaultAdminSeeder primero.'
            );
            return;
        }

        $adminRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

        $this->ensureZukoWith2fa($store, $adminRole);
        $this->ensureSokkaWithEmail($store, $adminRole);
        $this->ensureKataraWithRecoveryCodes($store, $adminRole);

        $this->command?->info(
            'AuthFlowTestUsersSeeder: 3 usuarios creados en ' . $store->name
            . ' (zuko: 2FA, sokka: email, katara: recovery codes).'
        );
    }

    private function ensureZukoWith2fa($store, Role $adminRole): User
    {
        $user = $this->ensureUser(
            email: 'zuko@panda-estampados.com',
            firstName: 'Zuko',
            lastName: 'Hot',
            preferredMethod: '2fa',
        );

        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();

        TwoFactorSetting::query()->updateOrCreate(
            attributes: ['user_id' => $user->id],
            values: [
                'secret' => Crypt::encryptString($secret),
                'enabled_at' => now(),
                'recovery_codes_generated_at' => now(),
            ],
        );

        // 10 recovery codes de backup. Para verificar el flow, el
        // admin puede regenerarlos via /auth/recovery-codes/regenerate.
        app(RecoveryCodeService::class)->generateFor($user, count: 10);

        $user->stores()->syncWithoutDetaching(ids: [$store->id]);
        return $user;
    }

    private function ensureSokkaWithEmail($store, Role $adminRole): User
    {
        $user = $this->ensureUser(
            email: 'sokka@panda-estampados.com',
            firstName: 'Sokka',
            lastName: 'Fun',
            preferredMethod: 'email',
        );
        $user->stores()->syncWithoutDetaching(ids: [$store->id]);
        return $user;
    }

    private function ensureKataraWithRecoveryCodes($store, Role $adminRole): User
    {
        $user = $this->ensureUser(
            email: 'katara@panda-estampados.com',
            firstName: 'Katara',
            lastName: 'Water',
            preferredMethod: 'recovery_codes',
        );

        // 10 recovery codes SIN 2FA habilitada (TwoFactorSetting
        // no existe para este user, así que preferred_recovery_method
        // se ignora y se usan los codes directamente).
        app(RecoveryCodeService::class)->generateFor($user, count: 10);

        $user->stores()->syncWithoutDetaching(ids: [$store->id]);
        return $user;
    }

    private function ensureUser(
        string $email,
        string $firstName,
        string $lastName,
        string $preferredMethod,
    ): User {
        return User::query()->updateOrCreate(
            attributes: ['email' => $email],
            values: [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => Hash::make(value: self::DEFAULT_PASSWORD),
                'is_active' => true,
                'role_id' => Role::query()->where(column: 'slug', operator: '=', value: 'admin')->first()?->id,
                'preferred_recovery_method' => $preferredMethod,
            ],
        );
    }
}
