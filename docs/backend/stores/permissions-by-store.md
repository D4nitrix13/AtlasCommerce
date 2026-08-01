# Permisos por Tienda

## Problema que resuelve

En un sistema multi-tienda, no todos los usuarios
administradores deben ver ni modificar datos de todas las
tiendas. Se necesita un mecanismo de control de acceso
que determine qué tienda puede administrar cada usuario.

## Modelo de control de acceso

### Relación `UserStore`

Cada usuario administrador se asocia a una o más tiendas
mediante la tabla `user_stores`:

```txt
Usuario admin global    → user_stores: [Panda, Kitsune]  → Ve ambas tiendas
Usuario de Panda        → user_stores: [Panda]           → Solo ve Panda
Usuario de Kitsune      → user_stores: [Kitsune]         → Solo ve Kitsune
```

### Tabla `user_stores`

- `user_id` — FK — Usuario administrador
- `store_id` — FK — Tienda asociada
- `active` — boolean — Si la asignación está activa
- `assigned_at` — timestamp — Fecha de asignación

## Escenarios de uso

### Dueño / Admin global

```txt
User: admin@panda.com
Role: super_admin
UserStores: [Panda, Kitsune]

→ Puede ver y editar ambas tiendas
→ Puede crear usuarios en cualquier tienda
→ Puede ver reportes consolidados
→ Puede cambiar configuración global
```

### Admin de Panda

```txt
User: panda-manager@panda.com
Role: admin
UserStores: [Panda]

→ Solo puede ver y editar Panda
→ No puede ver datos de Kitsune
→ No puede crear productos en Kitsune
→ No puede ver reportes de Kitsune
```

### Admin de Kitsune

```txt
User: kitsune-manager@panda.com
Role: admin
UserStores: [Kitsune]

→ Solo puede ver y editar Kitsune
→ No puede ver datos de Panda
→ No puede crear productos en Panda
→ No puede ver reportes de Panda
```

## Implementación

### Middleware `CheckStoreAccess`

```php
// Conceptual — pendiente de implementar
class CheckStoreAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $storeId = $request->header('X-Store-Id') ?? $request->input('store_id');

        if ($storeId) {
            $hasAccess = UserStore::where('user_id', $user->id)
                ->where('store_id', $storeId)
                ->where('active', true)
                ->exists();

            if (! $hasAccess) {
                abort(403, 'You do not have access to this store');
            }
        }

        return $next($request);
    }
}
```

### Policy por tienda

```php
// app/Policies/OrderPolicy.php (conceptual)
class OrderPolicy
{
    public function viewAny(User $user, Store $store): bool
    {
        return $user->stores()->where('store_id', $store->id)->exists()
            || $user->hasRole('super_admin');
    }
}
```

### Scope por tienda en modelos

```php
// En modelos con store_id
class Order extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('store', function (Builder $query) {
            $storeId = app()->make(StoreContextService::class)->getStoreId();

            if ($storeId) {
                $query->where('store_id', $storeId);
            }
        });
    }
}
```

## Roles y permisos

El sistema actual usa `roles` y `permibles` (tablas
`roles`, `permissions`, `role_has_permissions`). La
extensión para multi-tienda sería:

- `super_admin`
  - Permisos: Todos
  - Alcance: Ambas tiendas
- `admin`
  - Permisos: CRUD completo
  - Alcance: Tienda asignada
- `editor`
  - Permisos: Lectura + edición
  - Alcance: Tienda asignada
- `viewer`
  - Permisos: Solo lectura
  - Alcance: Tienda asignada

## Flujo de validación

```txt
1. Usuario envía petición con X-Store-Id: 1
2. Middleware ResolveStoreFromDomain resuelve la tienda
3. Middleware CheckStoreAccess valida acceso
4. Controller recibe request con tienda resuelta
5. Model scope filtra datos por store_id
6. Policy valida permisos específicos del recurso
7. Respuesta retorna solo datos de la tienda permitida
```

## Estado

- Tabla `user_stores`
  - Estado: **Pendiente** — migración no creada
- Model `UserStore`
  - Estado: **Pendiente**
- Middleware `CheckStoreAccess`
  - Estado: **Pendiente**
- Policies por tienda
  - Estado: **Pendiente**
- Scopes globales por `store_id`
  - Estado: **Pendiente**

**El sistema de permisos por tienda no está
implementado.** Actualmente todos los usuarios
autenticados ven todos los datos. La implementación
requiere:

1. Crear migración `user_stores`
2. Crear modelo `UserStore`
3. Crear middleware `CheckStoreAccess`
4. Crear o modificar Policies existentes
5. Agregar scopes globales en modelos con `store_id`
