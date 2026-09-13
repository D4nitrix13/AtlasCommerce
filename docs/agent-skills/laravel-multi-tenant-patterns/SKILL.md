<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 -->

---
name: laravel-multi-tenant-patterns
description: Multi-tenant architecture (Panda Estampados, MM-Boutique, Morales Store, Kitsune). Tenant resolution, DB isolation, global vs store_id entities, RLS context, Sanctum tokens scoped per tenant, user_stores, customer_stores. Triggers: editing middleware, migrations, store-domain bindings, RlsContextService, StoreContextService, models under Stores/, deciding if a table is global or per-tenant.
---

# Laravel Multi-Tenant Patterns

Source: `docs/architecture/multi-store.md` + `backend/app/Http/Middleware/ResolveStoreFromDomain.php`.

## Stack

- **Tenants**: 4 stores (`panda-estampados`, `kitsune`, `mm-boutique`, `morales-store`) - scripts/seeders can add more.
- **Single Laravel app**, multiple `store_domains` rows. **No** separate DB per tenant (single DB with RLS-ready context).
- **Tenant resolution**: `App\Http\Middleware\ResolveStoreFromDomain` reads `request->getHost()`, looks up `store_domains` (with `is_active=true`), loads the related `Store` and injects it into two services:
- `App\StoreContextService` → `Astro.locals.theme` and downstream middlewares.
- `Core\Tenant\Application\RlsContextService` → sets global `store_id` for queries (RLS-ready, doesn't enable RLS per query manually).

## Two stores, same platform

Panda Estampados and Kitsune are **two brands with separate identity**
that share **operations, inventory, and administration**.

| Tenant | Domain | Audience |
|---|---|---|
| **Panda Estampados** | `panda-estampados.com` | B2B, wholesale, custom products |
| **Kitsune Store** | `kitsune.com` | B2C, retail, anime/games |
| **MM-Boutique** | `mm-boutique.com` | (3rd tenant) |
| **Morales Store** | `morales-store.com` | (4th tenant) |

## Global vs store_id entities

### Tables with `store_id` (per-tenant)

```txt
product_stores → which products each store publishes
orders → orders per store
invoices → invoices per store
carts → carts per store
wishlists → wishlists per store
coupons → coupons per store
promotions → promotions per store
product_reviews → reviews per store
product_questions → questions per store
support_tickets → tickets per store
notifications → notifications per store
audits → audits per store
```text

### Tables without `store_id` (global)

```txt
users → global users (associated via user_stores)
roles → global roles
permissions → global permissions
currencies → global currencies
brands → global brands
categories → global categories
warehouses → global warehouses (shared inventory)
payment_methods → global payment methods
shipping_companies → global shipping companies
taxes → global taxes
```text

### Relationship tables (joins)

```txt
store_domains → domains per store
store_social_links → social networks per store
user_stores → which users manage which store
customer_stores → which customers belong to which store
product_stores → product publication per store
sales_channels → sales channels
```text

### Rule of thumb

> Is the data **specific to a commercial operation** (orders, invoices
> from this store's customers)? → `store_id`.
>
> Is the data **shared across all stores** (admin user, role, currency,
> warehouse)? → without `store_id`.
>
> Is it an **N:M relationship** between user/customer/product and store?
> → pivot table (`user_stores`, `customer_stores`, `product_stores`).

## Shared inventory

> **Decision (ADR-005)**: Shared inventory. The `inventories` and
> `warehouses` tables **do NOT** have `store_id`.

- Stock is decremented globally when any store sells.
- If per-store inventory is needed in the future, add `warehouse_stores`
 or similar.

## Price per store

The same product can have **different public price** in each store.

- `products.price` = reference price / cost.
- `product_stores.public_price` = override per store.

## Permissions per store

`user_stores` controls:

- Which store the user can manage.
- With what role within that store.

```txt
User: Carlos

Panda:
 - Role: Invoicer
 - Can create orders and issue invoices

Kitsune:
 - Role: Supervisor
 - Can review operations, but not necessarily
 issue invoices
```text

> A user can have **different role per store** via `user_stores.role_id`.

## Administrative traceability

`orders.user_creator_id` and `invoices.user_issuer_id` record who did the operation:

```txt
orders.user_creator_id
 → Can be null if the customer created the order from storefront
 → Or the admin's ID who created it from the panel

invoices.user_issuer_id
 → ID of the admin who issued the invoice
```text

## Trigger patterns

Activate when touching:

- `app/Http/Middleware/ResolveStoreFromDomain.php` - tenant resolver.
- `app/Http/Middleware/CheckPermission.php` - uses `store_id` to verify cross-tenant permissions.
- `app/Models/Stores/*` - `Store`, `StoreDomain`, `StoreTheme`, `StoreSetting`, `StoreSocialLink`, `UserStore`, `CustomerStore`, `ProductStore`, `SalesChannel`, `StoreFeature`.
- `app/Core/Tenant/Application/*` - `StoreContextService`, `RlsContextService`.
- Any migration touching `store_domains`, `store_settings`, `store_themes`, `store_social_links`, `user_store`, `customer_store`, `product_store`, `sales_channels`.
- Tests in `tests/Feature/Http/MultiTenantHttpIsolationTest.php` - template for any multi-tenant test.
- **Deciding** if a new table should have `store_id` or not.

## Conventions

- **Tenant context must always be set BEFORE** running queries. If a controller doesn't receive the middleware, fail 404, don't return default data.
- **`RlsContextService::clear()` ALWAYS in `finally`** (see `ResolveStoreFromDomain::handle`). Don't allow `store_id` leaks between requests.
- **Don't hardcode `store_id` in Controller queries** - always use `StoreContextService::current()` or Eloquent relations with scope.
- **Migrations that add tables with tenant scope** must include `store_id` (FK to `stores.id`, `ON DELETE CASCADE`) + composite index.
- **Multi-tenant tests** use `MultiTenantHttpIsolationTest` as template. Pattern: create 2 stores, request domain A, assert you only see data from A.

## Migration pattern

```php
Schema::create('orders', function (Blueprint $table) {
 $table->id();
 $table->foreignId('store_id')
 ->constrained('stores')
 ->cascadeOnDelete();
 $table->foreignId('user_id')->nullable()->constrained();
 $table->foreignId('customer_id')->constrained();
 $table->decimal('total', 12, 2);
 $table->timestamps();

 // Composite index for queries with scope
 $table->index(['store_id', 'status']);
});
```text

## Model pattern

```php
class Order extends Model
{
 use HasFactory;
 use BelongsToStore; // automatic global scope

 protected $fillable = [/* ... */];

 public function store(): BelongsTo
 {
 return $this->belongsTo(Store::class);
 }
}
```text

The `BelongsToStore` trait automatically filters by the context's `store_id`.

## Controller pattern

```php
public function index(): JsonResponse
{
 $storeId = $this->storeContext->current()->id;

 $orders = Order::query()
 ->where('store_id', $storeId)
 ->with('customer')
 ->get();

 return OrderResource::collection($orders);
}
```text

> DO NOT use `Order::all()` - always filter by the context's `store_id`.

## Anti-patterns

- ❌ `Store::query()->where('id', $storeId)->first()` when you can use `StoreContextService::current()`.
- ❌ Calling `setStoreId()` without corresponding `clear()`.
- ❌ Assuming a test runs "with the default store" - always set the explicit domain.
- ❌ Cache that ignores the tenant - `Cache::remember('foo', ...)` without domain prefix filters data cross-tenant.
- ❌ New table without deciding if it's `store_id` or global.
- ❌ `store_id` nullable in tables that **should** be per-tenant.
- ❌ Migration without `cascadeOnDelete()` on `store_id` FK.
- ❌ Tests without setting context - pass locally but fail in CI with another tenant.

## File map

```text
backend/app/Http/Middleware/ResolveStoreFromDomain.php ← entry point
backend/app/Http/Middleware/CheckPermission.php ← uses store_id
backend/app/Core/Tenant/Application/StoreContextService.php
backend/app/Core/Tenant/Application/RlsContextService.php
backend/app/Models/Stores/ ← all Store-* models
backend/app/Models/Concerns/BelongsToStore.php ← scope trait
backend/app/Models/Auth/User.php → user_stores relationship
backend/app/Models/{Sales,Marketing,Support}/* → per-tenant models
backend/tests/Feature/Http/MultiTenantHttpIsolationTest.php ← test template
backend/database/migrations/stores/ ← tenant migrations
docker/instances/development/ ← dev per-tenant envs
docker/instances/production/ ← prod per-tenant envs
Caddyfile ← routes by host
```text

## Related skills

- `docker-stack-orchestration` - dev/prod per-tenant setup.
- `caddyfile-multi-tenant-routing` - routes requests by host.
- `laravel-feature-testing` - patterns for Feature tests.
- `laravel-architecture-decisions` - Service vs Controller in multi-tenant.
- `redis-caching-patterns` - tenant-prefixed cache keys.
- `astro-themes-and-palette` - frontend counterpart.
