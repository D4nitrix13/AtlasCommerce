# `Store Context` — Resolución de Tienda Actual

## Problema que resuelve

El backend necesita saber qué tienda está siendo
visitada o administrada en cada petición. La resolución
varía según el contexto:

- **Storefront (público):** La tienda se resuelve desde
  el dominio de origen.
- **Panel admin:** La tienda se selecciona manualmente
  desde un selector.
- **API interna:** La tienda se pasa como parámetro o
  header.

## Estrategias de resolución

### 1. Por dominio (storefront)

El middleware `ResolveStoreFromDomain` extrae el dominio
del request y busca la tienda asociada:

```http
Request: GET https://kitsune.com/products
Host: kitsune.com
→ Buscar en store_domains: domain = "kitsune.com"
→ Resultado: Store{id: 2, name: "Kitsune Store"}
```

```php
// Middleware conceptual (pendiente de implementar)
class ResolveStoreFromDomain
{
    public function handle(Request $request, Closure $next)
    {
        $domain = $request->getHost();

        $storeDomain = StoreDomain::where('domain', $domain)
            ->where('active', true)
            ->with('store')
            ->first();

        if (! $storeDomain) {
            abort(404, 'Store not found for this domain');
        }

        app()->instance('currentStore', $storeDomain->store);

        return $next($request);
    }
}
```

### 2. Por header (admin)

El panel administrativo envía un header `X-Store-Id`
con el ID de la tienda seleccionada:

```http
GET /api/orders
Authorization: Bearer abc123...
X-Store-Id: 1
```

El backend extrae el `store_id` del header y filtra los
datos.

### 3. Por parámetro (API)

Para endpoints específicos, el `store_id` se pasa como
parámetro de query:

```http
GET /api/products?store_id=2
```

## Implementación recomendada

### Middleware `ResolveStoreFromDomain`

```php
// app/Http/Middleware/ResolveStoreFromDomain.php

class ResolveStoreFromDomain
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Intentar resolver por header (admin)
        if ($request->hasHeader('X-Store-Id')) {
            $storeId = $request->header('X-Store-Id');
            $store = Store::findOrFail($storeId);
            app()->instance('currentStore', $store);
            return $next($request);
        }

        // 2. Intentar resolver por dominio (storefront)
        $domain = $request->getHost();
        $storeDomain = StoreDomain::where('domain', $domain)
            ->where('active', true)
            ->first();

        if ($storeDomain) {
            app()->instance('currentStore', $storeDomain->store);
            return $next($request);
        }

        // 3. Sin contexto de tienda
        app()->instance('currentStore', null);
        return $next($request);
    }
}
```

### `StoreContextService`

Un servicio que encapsula la lógica de resolución:

```php
// app/Services/Stores/StoreContextService.php (futuro)

class StoreContextService
{
    public function getCurrentStore(): ?Store
    {
        return app()->instance('currentStore');
    }

    public function getStoreId(): ?int
    {
        $store = $this->getCurrentStore();
        return $store?->id;
    }

    public function requireStore(): Store
    {
        $store = $this->getCurrentStore();

        if (! $store) {
            abort(400, 'Store context required');
        }

        return $store;
    }
}
```

### Uso en controladores

```php
class OrderController extends Controller
{
    public function index(Request $request, StoreContextService $context)
    {
        $storeId = $context->getStoreId();

        $orders = Order::when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->get();

        return OrderResource::collection($orders);
    }
}
```

### Uso en modelos (scope global)

```php
// En el modelo Order
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

## Evitar mezclar datos

La resolución de tienda debe ser **consistente y
automática**. Si un controlador olvida filtrar por
`store_id`, los datos de Panda y Kitsune se mezclan.

### Reglas

1. **Todo endpoint transaccional** debe filtrar por
   `store_id`.
2. **El middleware** debe resolver la tienda antes de
   llegar al controlador.
3. **Los scopes globales** aplican el filtrado
   automáticamente en modelos.
4. **Las policies** validan que el usuario tenga acceso
   a la tienda solicitada.
5. **Las migraciones** deben incluir `store_id` en
   tablas transaccionales.

### Tablas que necesitan `store_id`

```txt
orders, invoices, carts, wishlists, coupons, promotions,
product_reviews, product_questions, support_tickets,
notifications, audits, product_stores
```

### Tablas que NO necesitan `store_id`

```txt
users, roles, permissions, currencies, brands, categories,
warehouses, payment_methods, shipping_companies, taxes,
products (el producto es global, se publica vía product_stores)
```

## Registro del middleware

En Laravel moderno, el registro de middlewares se
realiza en `bootstrap/app.php`:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'store.resolve' => ResolveStoreFromDomain::class,
    ]);
})
```

## Estado

```txt
ResolveStoreFromDomain:
  - Creado como base estructural en
    app/Http/Middleware/ResolveStoreFromDomain.php

StoreContextService:
  - Creado como base estructural en
    app/Services/Stores/StoreContextService.php

Integración con rutas admin:
  - Pendiente: validación con X-Store-Id

Validación de acceso con UserStore:
  - Pendiente

Policies por tienda:
  - Pendientes

Scopes por store_id:
  - Pendientes
```

Los componentes base están creados. La integración
completa con rutas administrativas, policies y
scopes debe verificarse en el código.
