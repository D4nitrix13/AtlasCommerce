# Permisos por Tienda

## Problema que resuelve

En un sistema multi-tienda, no todos los usuarios
administradores deben ver ni modificar datos de todas las
tiendas. Se necesita un mecanismo de control de acceso
que determine qué tienda puede administrar cada usuario,
y con qué rol dentro de esa tienda.

## Modelo de control de acceso

### Relación `UserStore`

Cada usuario administrador se asocia a una o más tiendas
mediante la tabla `user_stores`, que además define el
rol del usuario dentro de cada tienda:

```txt
user_stores
├── id              PK
├── user_id         FK → users
├── store_id        FK → stores
├── role_id         FK → roles
├── active          boolean
├── assigned_at     timestamp
├── created_at      timestamp
└── updated_at      timestamp
```

### Rol global vs rol por tienda

El sistema soporta dos niveles de roles:

```txt
users.role_id       → Rol global del usuario
user_stores.role_id → Rol específico del usuario en una tienda
```

Un usuario puede tener un rol global (por ejemplo,
`super_admin`) que le dé acceso total. Pero también
puede tener un rol diferente por tienda cuando su
acceso es limitado.

### Ejemplo

Usuario: **Carlos**

```txt
En Panda:
  - user_stores.role_id → Facturador
  - Puede crear pedidos y emitir facturas

En Kitsune:
  - user_stores.role_id → Supervisor
  - Puede revisar pedidos y reportes
```

Un mismo usuario tiene roles distintos según la tienda.

## Escenarios de uso

### Dueño / admin global

```txt
User: admin@panda.com
Role: super_admin (users.role_id)
UserStores: [Panda, Kitsune]

→ Puede administrar ambas tiendas
→ Puede asignar usuarios a tiendas
→ Puede definir roles por tienda
→ Puede ver reportes consolidados
```

### Usuario facturador de Panda

```txt
User: carlos@panda.com
Role: facturador (solo en user_stores de Panda)
UserStores: [Panda]

→ Solo accede a Panda
→ Puede crear pedidos si su rol lo permite
→ Puede emitir facturas si su rol lo permite
→ No accede a Kitsune bajo ninguna circunstancia
```

### Usuario supervisor de Kitsune

```txt
User: ana@panda.com
Role: supervisor (solo en user_stores de Kitsune)
UserStores: [Kitsune]

→ Solo accede a Kitsune
→ Puede revisar pedidos y facturas
→ Puede ver reportes de la tienda
→ No accede a Panda
```

### Usuario con ambas tiendas

```txt
User: pedro@panda.com
UserStores:
  - Panda → rol: editor
  - Kitsune → rol: viewer

→ En Panda puede editar productos y contenido
→ En Kitsune solo puede consultar
→ El rol es independiente por tienda
```

## Implementación

### Middleware `CheckStoreAccess`

```php
// Conceptual — debe verificarse en el código
class CheckStoreAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $storeId = $request->header('X-Store-Id')
            ?? $request->input('store_id');

        if ($storeId) {
            $hasAccess = UserStore::where('user_id', $user->id)
                ->where('store_id', $storeId)
                ->where('active', true)
                ->exists();

            if (! $hasAccess) {
                abort(403, 'No tienes acceso a esta tienda');
            }
        }

        return $next($request);
    }
}
```

### Policy por tienda

```php
// Conceptual — debe verificarse en el código
class OrderPolicy
{
    public function viewAny(User $user, Store $store): bool
    {
        return $user->stores()
            ->where('store_id', $store->id)
            ->exists()
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
            $storeId = app()
                ->make(StoreContextService::class)
                ->getStoreId();

            if ($storeId) {
                $query->where('store_id', $storeId);
            }
        });
    }
}
```

## Roles y permisos

El sistema contempla las tablas `roles`, `permissions`
y `role_permissions` según el DER:

```txt
roles           → roles del sistema
permissions     → permisos granulares
role_permissions → relación rol-permiso
user_stores     → asignación de usuario a tienda con rol
```

Ejemplo de roles por tienda:

```txt
super_admin
  - Permisos: Todos
  - Alcance: Ambas tiendas

admin
  - Permisos: CRUD completo
  - Alcance: Tienda asignada

facturador
  - Permisos: Crear pedidos, emitir facturas
  - Alcance: Tienda asignada

supervisor
  - Permisos: Lectura, revisión, reportes
  - Alcance: Tienda asignada

editor
  - Permisos: Lectura + edición de contenido
  - Alcance: Tienda asignada

viewer
  - Permisos: Solo lectura
  - Alcance: Tienda asignada
```

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

```txt
Tabla user_stores:
  - Migración: implementada
  - Modelo UserStore: implementado en
    `App\Models\Stores\UserStore`
  - Lógica CRUD: implementada vía
    `App\Http\Controllers\Api\Admin\UserController`
    + `App\Http\Requests\Admin\UserStoreRequest`
    + `App\Http\Resources\Stores\UserStoreResource`

Middleware ResolveStoreFromDomain:
  - Implementado en
    `App\Http\Middleware\ResolveStoreFromDomain`
  - Registrado en `bootstrap/app.php`

Middleware ValidateStoreIdHeader:
  - Implementado en
    `App\Http\Middleware\ValidateStoreIdHeader`

Scopes globales por store_id (BelongsToStore trait):
  - Implementado y aplicado en modelos multi-tenant
  - RLS activado en PostgreSQL (F5.3 del roadmap) como
    segunda capa de defensa
```

El sistema de autorización está operativo. Validar
cualquier policy nueva contra `policies/` y agregar
test feature en `tests/Feature/`.
