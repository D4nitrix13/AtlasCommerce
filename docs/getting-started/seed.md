# Seed — Datos iniciales

Después de correr migraciones, los seeders crean los datos mínimos
para que el sistema funcione. Incluyen tiendas, monedas, roles,
permisos y un usuario admin.

## 1. Correr los seeders

```bash
cd backend
php artisan db:seed
```

Esto ejecuta todos los seeders registrados en `DatabaseSeeder`:

- `DatabaseSeeder` (orquesta los demás).
- `PermissionsCatalogSeeder` (roles + permisos).
- `AdminUserSeeder` (usuario admin + asociación a stores).

## 2. Datos que se crean

```bash
php artisan tinker --execute='
echo "Currencies: " . App\Models\Settings\Currency::count() . "\n";
echo "Stores: " . App\Models\Stores\Store::count() . "\n";
echo "StoreDomains: " . App\Models\Stores\StoreDomain::count() . "\n";
echo "Roles: " . App\Models\Auth\Role::count() . "\n";
echo "Permissions: " . App\Models\Auth\Permission::count() . "\n";
echo "Users: " . App\Models\Auth\User::count() . "\n";
'
```

Salida esperada:

```txt
Currencies: 2 (USD, NIO)
Stores: 2 (Panda, Kitsune)
StoreDomains: 6 (3 por tienda)
Roles: 2 (admin, staff)
Permissions: 26 (agrupados por módulo)
Users: 1 (admin@panda-estampados.test)
```

## 3. Credenciales del admin

- `email`: `admin@panda-estampados.test`
- `password`: `password`
- `role`: admin (bypass total)
- `store_ids`: ambas tiendas (Panda + Kitsune)

## 4. Login de prueba

```bash
curl -s -X POST http://panda-estampados.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@panda-estampados.test","password":"password"}'
```

Respuesta:

```json
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "email": "admin@panda-estampados.test",
    "full_name": "Admin Panda",
    "role": { "id": 1, "slug": "admin", "name": "Administrador" },
    "store_ids": [1, 2],
    "is_active": true,
    "permissions": []
  }
}
```

## 5. Crear usuarios adicionales

Desde tinker:

```bash
php artisan tinker
```

```php
$user = App\Models\Auth\User::create([
    'first_name' => 'Juan',
    'last_name' => 'Pérez',
    'email' => 'juan@example.com',
    'password' => bcrypt('SecurePass123'),
    'is_active' => true,
]);

// Asignar a una tienda
$user->stores()->attach(1);

// Asignar rol
$user->update(['role_id' => 2]);
```

## 6. Crear tiendas adicionales

Desde tinker:

```php
$store = App\Models\Stores\Store::create([
    'name' => 'Tienda Nueva',
    'slug' => 'tienda-nueva',
    'currency_id' => 1,
    'is_active' => true,
]);

App\Models\Stores\StoreDomain::create([
    'store_id' => $store->id,
    'domain' => 'tienda-nueva.com',
    'type' => 'domain',
    'base_path' => '/',
    'is_active' => true,
    'is_primary' => true,
]);
```

Después agregar el dominio a `Caddyfile` y a `STORE_BRANDS` en el
frontend.

## 7. Permisos disponibles

26 permisos agrupados por módulo:

- `auth`: `users.manage`, `roles.manage`, etc.
- `sales`: `orders.view`, `orders.create`, `orders.cancel`.
- `payments`: `payments.view`, `payments.process`.
- `shipping`: `shipments.ship`, `shipments.deliver`, `shipments.cancel`.
- `returns`: `return_requests.approve`, `refunds.process`.
- `inventory`: `inventory.view`, `inventory.adjust`.
- `catalog`: `products.view`, `products.create`, `products.edit`.

Listar todos:

```bash
php artisan tinker --execute='App\Models\Auth\Permission::all()->pluck("slug")->each(fn($s) => print($s . "\n"));'
```

## 8. Re-correr seeders

Los seeders usan `updateOrCreate` por lo que son idempotentes. Se
pueden correr múltiples veces sin duplicar:

```bash
php artisan db:seed
```

## 9. Reset completo

```bash
php artisan migrate:fresh --seed
```

¡Cuidado! Borra todos los datos y deja solo los del seeder.

## Próximo paso

[development/](../development/README.md "development/README.md") —
Workflow diario de desarrollo.
