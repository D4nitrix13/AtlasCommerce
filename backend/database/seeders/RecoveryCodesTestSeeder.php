<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Auth\Role;
use App\Models\Auth\TwoFactorSetting;
use App\Models\Auth\User;
use App\Models\Stores\Store;
use App\Services\Auth\RecoveryCodeService;
use App\Services\Auth\TotpService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de 4 personajes de test, uno por tienda, demostrando las 4
 * combinaciones posibles de 2FA + recovery codes.
 *
 * Cada personaje vive en una tienda distinta y muestra un camino de
 * recuperación distinto. Los códigos se imprimen a stdout en una caja
 * decorada para que el dev los guarde en su password manager — solo se
 * muestran una vez.
 *
 * Tabla:
 *
 * | Email                          | Tienda            | TOTP | Recovery Codes |
 * | ------------------------------ | ----------------- | ---- | -------------- |
 * | admin@panda-estampados.test    | panda-estampados  | sí   | 10             |
 * | ops@mm-boutique.test           | mm-boutique       | no   | 0              |
 * | warehouse@morales-store.test   | morales-store     | sí   | 0              |
 * | admin@kitsune.test             | kitsune           | sí   | 10             |
 *
 * Los stores se crean con `updateOrCreate` (no se asume que
 * `DefaultAdminSeeder` corrió antes). Los dominios se omiten — el
 * objetivo es tener los users para tests E2E/integration, no un
 * setup completo de tenants. Para setup completo usar
 * `tenants:bootstrap`.
 *
 * Idempotente: re-correr no duplica users ni codes (usa `updateOrCreate`
 * para TwoFactorSetting y `generateFor` borra codes anteriores).
 *
 * Uso:
 *   php artisan db:seed --class=RecoveryCodesTestSeeder
 */
final class RecoveryCodesTestSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password';

    /**
     * @var array<int, array{email: string, store_slug: string, totp: bool, recovery_codes: int}>
     */
    private const CHARACTERS = [
        [
            'email' => 'admin@panda-estampados.test',
            'store_slug' => 'panda-estampados',
            'totp' => true,
            'recovery_codes' => 10,
        ],
        [
            'email' => 'ops@mm-boutique.test',
            'store_slug' => 'mm-boutique',
            'totp' => false,
            'recovery_codes' => 0,
        ],
        [
            'email' => 'warehouse@morales-store.test',
            'store_slug' => 'morales-store',
            'totp' => true,
            'recovery_codes' => 0,
        ],
        [
            'email' => 'admin@kitsune.test',
            'store_slug' => 'kitsune',
            'totp' => true,
            'recovery_codes' => 10,
        ],
    ];

    public function run(): void
    {
        $adminRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

        foreach (self::CHARACTERS as $character) {
            $store = $this->ensureStore($character['store_slug']);

            if ($store === null) {
                $this->command?->error(
                    'RecoveryCodesTestSeeder: no se pudo crear el store '
                    .$character['store_slug'].' — saltando personaje.',
                );

                continue;
            }

            $user = $this->ensureUser($character['email'], $adminRole->id);

            if ($character['totp']) {
                $this->ensureTotp($user);
            }

            $codes = [];
            if ($character['recovery_codes'] > 0) {
                $codes = app(abstract: RecoveryCodeService::class)->generateFor(
                    user: $user,
                    count: $character['recovery_codes'],
                );
            }

            $user->stores()->syncWithoutDetaching(ids: [$store->id]);

            $this->printSummary(
                email: $character['email'],
                store: $store->name ?? $character['store_slug'],
                totp: $character['totp'],
                codes: $codes,
            );
        }

        $this->command?->info('RecoveryCodesTestSeeder: 4 personajes listos.');
    }

    private function ensureStore(string $slug): ?Store
    {
        return Store::query()->updateOrCreate(
            attributes: ['slug' => $slug],
            values: [
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'is_active' => true,
            ],
        );
    }

    private function ensureUser(string $email, int $roleId): User
    {
        return User::query()->updateOrCreate(
            attributes: ['email' => $email],
            values: [
                'first_name' => $this->firstNameFromEmail($email),
                'last_name' => 'Test',
                'password' => Hash::make(value: self::DEFAULT_PASSWORD),
                'is_active' => true,
                'role_id' => $roleId,
                'preferred_recovery_method' => 'email',
            ],
        );
    }

    private function ensureTotp(User $user): void
    {
        // Genera un secret nuevo y lo encripta para storage. Si ya
        // existe TwoFactorSetting, lo regeneramos — esto es dev seed,
        // el secret "filtrado" en stdout no es riesgo de producción.
        $secret = app(abstract: TotpService::class)->generateSecret();

        TwoFactorSetting::query()->updateOrCreate(
            attributes: ['user_id' => $user->id],
            values: [
                'secret' => Crypt::encryptString($secret),
                'enabled_at' => now(),
            ],
        );
    }

    /**
     * Imprime una caja decorada con los datos del personaje y sus
     * recovery codes (si los tiene). Va directo a stdout (no usa el
     * canal del comando) para que `php artisan db:seed` lo muestre
     * sin truncar y conserve la decoración Unicode.
     *
     * Estructura de cada línea: ║ + contenido($width chars) + ║.
     * El contenido tiene un prefix (ej: "  RECOVERY CODES — "), después
     * puede haber datos del user, después el ║ final. Calculamos el
     * padding restante para que el total entre los dos ║ sea exactamente
     * $width chars.
     *
     * @param  array<int, string>  $codes
     */
    private function printSummary(string $email, string $store, bool $totp, array $codes): void
    {
        $width = 68;
        $line = str_repeat(string: '═', times: $width);

        // mb_strlen cuenta CARACTERES (no bytes). Los ║/═ son multibyte
        // UTF-8 (3 bytes cada uno) pero ocupan 1 columna visual.

        // Línea de email: "║  RECOVERY CODES — <email>"
        $prefix = '  RECOVERY CODES — ';  // sin el ║ — el ║ se concatena fuera
        $pad = max(0, $width - mb_strlen($prefix) - mb_strlen($email));
        echo '║'.$prefix.str_repeat(' ', $pad).$email."║\n";

        // Línea de tienda: "║  Tienda: <store>"
        $prefix = '  Tienda: ';
        $pad = max(0, $width - mb_strlen($prefix) - mb_strlen($store));
        echo '║'.$prefix.str_repeat(' ', $pad).$store."║\n";

        // Flags
        $flags = '  TOTP: '.($totp ? 'sí' : 'no').'   Recovery codes: '.count($codes);
        $pad = max(0, $width - mb_strlen($flags));
        echo '║'.$flags.str_repeat(' ', $pad)."║\n";

        // Warning
        $warning = '  Guardar en password manager. No se vuelven a mostrar.';
        $pad = max(0, $width - mb_strlen($warning));
        echo '║'.$warning.str_repeat(' ', $pad)."║\n";

        echo "╠{$line}╣\n";

        if ($codes === []) {
            $msg = '  (sin recovery codes)';
            $pad = max(0, $width - mb_strlen($msg));
            echo '║'.$msg.str_repeat(' ', $pad)."║\n";
        } else {
            foreach ($codes as $index => $code) {
                $row = sprintf('  %3d.  %s', $index + 1, $code);
                $pad = max(0, $width - mb_strlen($row));
                echo '║'.$row.str_repeat(' ', $pad)."║\n";
            }
        }

        echo "╚{$line}╝\n";
    }

    private function firstNameFromEmail(string $email): string
    {
        $local = explode('@', $email)[0] ?? 'user';

        return ucfirst(str_replace(['.', '-', '_'], ' ', $local));
    }
}
