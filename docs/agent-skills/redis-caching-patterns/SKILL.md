<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 -->

---
name: redis-caching-patterns
description: Redis usage in the project (cache, sessions, queues, tenant-prefixed keys). Triggers: config/cache.php, config/queue.php, new jobs, session drivers, any Cache:: facade.
---

# Redis Caching Patterns

## Stack

- **Redis** is used for: cache, sessions, queues (the instance runs inside the Docker network per store).
- **Connection**: `config/database.php::redis` - default `redis` driver for cache.
- **Frontend does NOT use Redis** - only the Laravel backend.

## Tenant prefixing (CRITICAL)

**ALL cache keys must carry the current tenant prefix** - otherwise,
data leaks between stores in the same Redis instance.

```php
// ❌ Bad - shared cache
Cache::remember('top_products', 3600, fn() => Product::query()->limit(10)->get());

// ✅ Good - scoped per tenant
$storeId = StoreContextService::current()->id;
Cache::remember("store:{$storeId}:top_products", 3600, fn() => Product::query()->limit(10)->get());
```text

**Recommended helper** (create if doesn't exist):

```php
// app/Support/TenantCache.php
trait TenantCache {
 public function tenantCache(): string {
 return "store:" . StoreContextService::current()->id;
 }
}
```text

Use as: `Cache::remember("{$this->tenantCache()}:top_products", ...)`.

## Cache tags

Laravel doesn't support `Cache::tags()` with the native Redis driver.
If you need selective invalidation by group, use **prefix + manual flush**:

```php
// Logical tags (not Redis tags)
Cache::put("store:{$id}:products:list", $data, 3600);
Cache::put("store:{$id}:products:detail:{$productId}", $data, 3600);

// Invalidate: delete explicitly
Cache::forget("store:{$id}:products:list");
Cache::forget("store:{$id}:products:detail:{$productId}");
```text

## Sessions

- **Driver**: `config/session.php::driver` - currently `database` (not Redis). If it migrates to Redis, keep tenant scoping.
- **Table**: `sessions` (column `payload` with serialized data).

## Queues

- **Driver**: `config/queue.php::default` - verify `redis` or `database`.
- **Job naming**: `App\Jobs\...` namespace, classes end in `Job`.
- **Tenant context in jobs**: serialize the `store_id` in the job (`public int $storeId`) - DO NOT use `StoreContextService` inside the job (no request context).

```php
class SyncTopProductsJob implements ShouldQueue {
 public function __construct(public int $storeId) {}
 public function handle(): void {
 StoreContextService::setById($this->storeId); // re-hydrate
 // ...
 }
}
```text

## Trigger patterns

Activate when:

- Modifying `config/cache.php`, `config/queue.php`, `config/database.php`.
- Adding a `Cache::` call.
- Creating a `Job` or `Listener`.
- Cache middleware (e.g., `CacheResponse`).
- `php artisan cache:*` or `php artisan queue:*` commands.

## TTLs by convention

| Type | TTL |
|---|---|
| Config / settings | 1h (3600) |
| Catalog listings | 15min (900) |
| Catalog detail | 30min (1800) |
| User/session | session (driver) |
| Theme config | 24h (86400) |
| Aggregations | 5min (300) |

## Anti-patterns

- ❌ `Cache::remember('foo', ...)` without tenant prefix.
- ❌ Job that assumes `StoreContextService` is set (no request).
- ❌ `Cache::flush()` global - deletes ALL keys from ALL tenants.
- ❌ `Cache::tags()` with native Redis driver (doesn't work).
- ❌ TTL without documentation - magic numbers.

## File map

```text
backend/config/cache.php
backend/config/queue.php
backend/config/database.php ← redis connection
backend/config/session.php
backend/app/Jobs/ ← jobs (carry $storeId)
backend/app/Support/ ← tenant helpers (create)
backend/app/Http/Middleware/ ← response cache middleware
```text

## Related skills

- `laravel-multi-tenant-patterns` - tenant context in jobs.
- `docker-stack-orchestration` - Redis per instance.
- `laravel-feature-testing` - cache isolation testing.
