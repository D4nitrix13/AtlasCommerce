<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Stores\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea el usuario admin inicial del sistema.
 *
 * Entorno controlado (desarrollo + staging). En producción este
 * seeder NO debe correrse — el password es débil a propósito y
 * está hardcoded. La idea es tener un usuario funcional al boot
 * para poder probar el login inmediatamente sin tinker.
 *
 * Credenciales:
 *   email:    admin@panda-estampados.test
 *   password: password
 *   role:     admin (bypass total vía middleware CheckPermission)
 *
 * Multi-tenancy: se asocia automáticamente a TODAS las tiendas
 * activas del sistema, para que el admin de desarrollo pueda
 * entrar a admin.pandas-estampados.com y admin.kitsune.com sin
 * configuración adicional.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

        $user = User::query()->updateOrCreate(
            attributes: ['email' => 'admin@panda-estampados.test'],
            values: [
                'first_name' => 'Admin',
                'last_name' => 'Panda',
                'password' => Hash::make(value: 'password'),
                'is_active' => true,
                'role_id' => $adminRole->id,
            ],
        );

        // Asocia a todas las tiendas activas. Es idempotente: usa
        // syncWithoutDetaching para no duplicar si se corre varias veces.
        $storeIds = Store::query()
            ->where(column: 'is_active', operator: '=', value: true)
            ->pluck(column: 'id')
            ->all();

        if ($storeIds !== []) {
            $user->stores()->syncWithoutDetaching(ids: $storeIds);
        }

        $this->command?->info('Admin user seeded: admin@panda-estampados.test / password');
    }
}
