<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use Illuminate\Database\Seeder;

/**
 * Catálogo base de permisos y roles del sistema.
 *
 * Convenciones:
 *  - Slug del permiso: `{modulo}.{accion}` (ej. `shipments.ship`).
 *  - Cada módulo agrupa permisos para facilitar asignación al rol.
 *  - El rol `admin` (slug) bypasea el chequeo de permisos en el middleware.
 *
 * Idempotente: usar `updateOrCreate` por (module, slug) para que pueda
 * correrse múltiples veces sin duplicar registros.
 */
class PermissionsCatalogSeeder extends Seeder
{
    /**
     * Catálogo declarativo. Mantener ordenado por módulo.
     *
     * @var array<string, array<int, string>>
     */
    private array $catalog = [
        'auth' => [
            'auth.users.manage',
            'auth.roles.manage',
            'auth.permissions.manage',
        ],
        'sales' => [
            'sales.orders.view',
            'sales.orders.manage',
            'sales.orders.cancel',
            'sales.invoices.view',
            'sales.invoices.manage',
        ],
        'customers' => [
            'customers.view',
            'customers.write',
        ],
        'payments' => [
            'payments.payments.view',
            'payments.payments.create',
            'payments.refunds.view',
            'payments.refunds.process',
        ],
        'shipping' => [
            'shipping.shipments.view',
            'shipping.shipments.create',
            'shipping.shipments.ship',
            'shipping.shipments.deliver',
            'shipping.shipments.cancel',
        ],
        'returns' => [
            'returns.return_requests.view',
            'returns.return_requests.approve',
            'returns.return_requests.reject',
            'returns.refunds.view',
            'returns.refunds.process',
        ],
        'inventory' => [
            'inventory.view',
            'inventory.movements.view',
            'inventory.manage',
        ],
        'catalog' => [
            'catalog.view',
            'catalog.manage',
        ],
        'marketing' => [
            'marketing.view',
            'marketing.manage',
        ],
        'stores' => [
            'stores.view',
            'stores.manage',
        ],
    ];

    public function run(): void
    {
        foreach ($this->catalog as $module => $slugs) {
            foreach ($slugs as $slug) {
                Permission::query()->updateOrCreate(
                    attributes: ['slug' => $slug],
                    values: [
                        'name' => $this->humanize(slug: $slug),
                        'module' => $module,
                        'description' => "Permiso para {$slug}",
                    ],
                );
            }
        }

        // Crea el rol admin (bypasea chequeos) si no existe.
        Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: [
                'name' => 'Administrador',
                'description' => 'Acceso total al sistema (bypasea chequeo de permisos).',
                'is_active' => true,
            ],
        );

        // Rol staff: permisos operativos básicos (NO incluye admin-bypass).
        $staffPermissions = [
            'sales.orders.view',
            'sales.orders.cancel',
            'sales.invoices.view',
            'payments.payments.view',
            'payments.refunds.view',
            'shipping.shipments.view',
            'shipping.shipments.ship',
            'shipping.shipments.deliver',
            'returns.return_requests.view',
            'returns.return_requests.approve',
            'returns.return_requests.reject',
            'customers.view',
            'customers.write',
            'inventory.view',
            'inventory.movements.view',
            'stores.view',
        ];

        $staffRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'staff'],
            values: [
                'name' => 'Operador',
                'description' => 'Personal operativo con permisos de consulta y gestión de envíos/devoluciones.',
                'is_active' => true,
            ],
        );

        $staffRole->permissions()->sync(
            ids: Permission::query()
                ->whereIn(column: 'slug', values: $staffPermissions)
                ->pluck(column: 'id')
                ->all(),
        );
    }

    /**
     * Convierte `shipments.ship` → `Shipments Ship`.
     */
    private function humanize(string $slug): string
    {
        $parts = explode('.', $slug);
        $tail = array_pop($parts);
        $module = implode(' ', array_map('ucfirst', $parts));

        return ucfirst($tail).' '.ucfirst(str_replace('_', ' ', $module));
    }
}
