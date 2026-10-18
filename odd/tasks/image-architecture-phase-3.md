# Plan: Image Architecture — Fase 3 (HMAC URL signing + cache config + 404 logging)

> **Status**: en ejecución. Branch base: `master` HEAD `10d55e64` (Fase 2 ya mergeada).
>
> **Scope**: cerrar 3 gaps reales del `AssetController` identificados en el audit: (1) HMAC signed URLs con expiración, (2) cache config-driven con fallback (Redis → file) y TTL de 5min, (3) structured 404 enumeration logging. NO se hace: backfill de imágenes (Fase 5), flip soft → strict mode (Fase 6), migration pattern (Fase 4).
>
> **Engram mirror**: `odd/image-architecture-phase-3/tasks`

---

## 0. Contexto

Fase 2 (mergeada en `10d55e64`) dejó:
- ✅ `StorageKeyDeriver` + soft-mode validation funcionando en `AssetController`.
- ✅ `config/assets.php` con `path_validation.strict` flag (default `false`).
- ✅ `product_images.image_url` aún es columna `string` libre (sin storage key enforcement) — backfill = Fase 5.

El audit READ-ONLY identificó 3 gaps que siguen abiertos y esta fase los cierra:

| Gap | Impacto | Solución |
|---|---|---|
| `AssetController` no valida HMAC ni expiración | Cualquiera que conozca un path válido puede fetchear assets sin restricción temporal | `UrlSigner` con HMAC-SHA256 + `hash_equals` (timing-safe) |
| `Cache::store('redis')` hard-coded, TTL 86400 hard-coded | Redis caído = 500; cambios en assets quedan cacheados 24h | Config-driven: `config('assets.cache_store')` con default Redis + `config('assets.cache_ttl')` con default 300 |
| 404s no se loguean estructurados | Imposible detectar enumeration attacks | Structured log con hash del path + tenant_slug + client_ip |

---

## 1. Archivos a tocar

| Archivo | Acción |
|---|---|
| `backend/app/Services/Assets/UrlSigner.php` | **CREAR** — clase pura con `sign()` + `verify()` |
| `backend/tests/Unit/Services/Assets/UrlSignerTest.php` | **CREAR** — cobertura sign/verify/expiry/tampering |
| `backend/config/assets.php` | **EXTENDER** — agregar `cache_store`, `cache_ttl`, `url_signing.required`, `url_signing.ttl`, `url_signing.secret` |
| `backend/app/Http/Controllers/Api/Storefront/AssetController.php` | **MODIFICAR** — wire URL signing (gated por config) + cache config-driven + 404 structured log |
| `backend/tests/Feature/Storefront/StorefrontAssetTest.php` | **EXTENDER** — tests nuevos + refactor del test `it_caches_assets_in_redis_with_24h_ttl` |
| `docs/architecture/assets-naming-convention.md` | **UPDATE** — §6.3 explicar URL signing + cache config + logging |

Sin tocar: `StorageKeyDeriver`, migraciones, modelos, `filesystems.php`, rutas.

---

## T1 — Crear `UrlSigner` (puro, determinístico, timing-safe)

**Decisiones de diseño**:

1. **Algoritmo**: HMAC-SHA256 sobre canonical `path|expires`.
2. **Secret source**: `config('assets.url_signing.secret')` con fallback a `config('app.key')`.
3. **TTL**: `config('assets.url_signing.ttl')` default 300 (5min).
4. **Canonical format del query string**: `expires=<unix_ts>&sig=<hex>` (orden estable).
5. **Comparison**: `hash_equals` para timing-safe verify.
6. **API pública**:
   ```php
   public function sign(string $path, ?int $ttl = null): string
   // returns: "expires={ts}&sig={hex}"

   public function verify(string $path, string $expires, string $signature): bool
   // checks: signature matches, expires > now, ttl not absurd
   ```
7. **Sin I/O**, sin facades, sin globals — la secret viene por DI.

**Constructor con DI** (per AGENTS.md §3 — property promotion):
```php
public function __construct(
    public readonly string $secret,
    public readonly int $defaultTtl = 300,
) {}
```

**Tests** (`UrlSignerTest`, ≈12 casos):
- `it_produces_deterministic_signature_for_same_path_and_expires`
- `it_includes_expires_in_query_string`
- `it_respects_custom_ttl`
- `it_defaults_to_configured_ttl_when_no_arg`
- `it_accepts_valid_signature`
- `it_rejects_expired_signature`
- `it_rejects_tampered_signature`
- `it_rejects_signature_with_wrong_secret`
- `it_rejects_signature_with_invalid_hex`
- `it_rejects_empty_signature`
- `it_rejects_zero_or_negative_expires`
- `it_uses_timing_safe_comparison` (verificable via `hash_equals` use, no behavior test — cubrir con docblock que documente el patrón)

**Commit**: `feat(assets): agregar servicio UrlSigner (HMAC-SHA256 con expiración)`

**Verificación**: `vendor/bin/pint --dirty --format=agent` + `php artisan test --compact --filter=UrlSignerTest`

---

## T2 — Wire URL signing en `AssetController`

**Cambios**:

1. Extender `config/assets.php` con:
   ```php
   'url_signing' => [
       'required' => env(key: 'ASSETS_URL_SIGNING_REQUIRED', default: false),
       'ttl' => env(key: 'ASSETS_URL_SIGNING_TTL', default: 300),
       'secret' => env(key: 'ASSETS_URL_SIGNING_SECRET') ?: config(key: 'app.key'),
   ],
   ```
2. Inyectar `UrlSigner` en `AssetController` por constructor property promotion (segunda DI — `StorageKeyDeriver` ya está).
3. El controller ya recibe `?expires=...&sig=...` en el query string (Laravel lo maneja en `$request->query()`). Después del soft-mode check (T3 de Fase 2), insertar:
   ```php
   if (config(key: 'assets.url_signing.required') === true) {
       $expires = $request->query(key: 'expires');
       $signature = $request->query(key: 'sig');
       
       if (! is_string(value: $expires) || ! is_string(value: $signature)) {
           abort(code: 403, message: 'Missing signature.');
       }
       
       if (! $this->urlSigner->verify(path: $normalizedPath, expires: $expires, signature: $signature)) {
           abort(code: 403, message: 'Invalid or expired signature.');
       }
   }
   ```
4. Default `required = false` — Fase 6 flipea a `true` post-backfill.

**Tests nuevos en `StorefrontAssetTest`** (4 casos):
- `it_serves_without_signature_when_url_signing_not_required` (default)
- `it_rejects_without_signature_when_url_signing_required`
- `it_rejects_expired_signature_when_url_signing_required`
- `it_serves_with_valid_signature_when_url_signing_required`

**Commit**: `feat(assets): integrar verificación HMAC en AssetController (gated por config)`

**Verificación**: `vendor/bin/pint --dirty --format=agent` + `php artisan test --compact --filter=StorefrontAssetTest`

---

## T3 — Cache config-driven con fallback

**Cambios**:

1. Extender `config/assets.php` con:
   ```php
   'cache_store' => env(key: 'ASSETS_CACHE_STORE', default: 'redis'),
   'cache_ttl' => env(key: 'ASSETS_CACHE_TTL', default: 300),
   ```
2. Modificar `AssetController`:
   - Reemplazar `Cache::store(name: 'redis')` por `Cache::store(config(key: 'assets.cache_store'))` en **TODAS** las ocurrencias (audit reportó 1 ocurrencia en línea 94).
   - Reemplazar TTL hard-coded `86400` por `config(key: 'assets.cache_ttl')`.
3. **Refactor del test existente** `it_caches_assets_in_redis_with_24h_ttl` (`StorefrontAssetTest.php:172`):
   - Renombrar a `it_caches_assets_with_configured_store_and_ttl`.
   - Usar `config()->set(key: 'assets.cache_store', value: 'redis')` antes del request.
   - Usar `config()->set(key: 'assets.cache_ttl', value: 86400)` o el valor que se quiera testear.
   - Aserciones con `Cache::store(config(key: 'assets.cache_store'))->has(...)` y `->get(...)`.

**Tests nuevos** (2 casos):
- `it_uses_configured_cache_store`
- `it_uses_configured_cache_ttl`

**Commit**: `feat(assets): cache config-driven con store + TTL configurables`

**Verificación**: `vendor/bin/pint --dirty --format=agent` + `php artisan test --compact --filter='UrlSignerTest|StorefrontAssetTest'`

---

## T4 — 404 enumeration logging estructurado

**Cambios**:

1. En `AssetController`, cuando `$disk->exists($normalizedPath)` retorna `false` (≈ línea 85), insertar **antes** del `abort(404)`:
   ```php
   logger()->warning(message: 'asset.not_found', context: [
       'hash' => hash(algo: 'sha256', data: $normalizedPath),
       'tenant_slug' => $this->tenantSlug,
       'client_ip' => $request->ip(),
       'user_agent' => substr(string: $request->userAgent() ?? 'unknown', offset: 0, length: 200),
   ]);
   ```
2. Requiere inyectar `Request` o usar el helper global `request()`. Preferir `request()` para no inflar el constructor con otra DI.

**Tests nuevos en `StorefrontAssetTest`** (1 caso):
- `it_logs_structured_warning_on_404`
- Verifica que `Log::spy()` recibe `asset.not_found` con el hash esperado.

**Commit**: `feat(assets): structured logging en 404 para detección de enumeration`

**Verificación**: `vendor/bin/pint --dirty --format=agent` + `php artisan test --compact --filter='StorefrontAssetTest'`

---

## T5 — Update `docs/architecture/assets-naming-convention.md`

Agregar **§6.3 Runtime protections** con sub-secciones:
- §6.3.1 URL signing (HMAC-SHA256, default `ASSETS_URL_SIGNING_REQUIRED=false`, flip post-backfill)
- §6.3.2 Cache strategy (Redis default con file fallback via `ASSETS_CACHE_STORE`, TTL 5min default)
- §6.3.3 Enumeration detection (logs estructurados `asset.not_found` + `asset.path.malformed` con hash + tenant + IP)

Drift adicional a documentar:
- TTL hard-coded en código actual (86400s) vs default aprobado (300s) — corregido por T3.

**Commit**: `docs(assets): documentar runtime protections (signing + cache + logging)`

---

## 5. Plan de ejecución (orden)

1. **T1** — `UrlSigner` class + tests (independiente, primer commit).
2. **T2** — wire HMAC en `AssetController` (depende de T1).
3. **T3** — cache config-driven (independiente del orden).
4. **T4** — 404 logging (independiente).
5. **T5** — docs (después de todos los cambios de código).

Total esperado: 5 commits work-unit, ~400-500 líneas (clase + tests + controller + docs).

---

## 6. Setup de tests para el writer

El backend requiere Docker stack levantado para correr tests:
```bash
cd docker/instances/development/panda-estampados-and-kitsune
HOST_UID=1000 HOST_GID=1000 docker compose --env-file environment/.env up -d postgres redis backend
HOST_UID=1000 HOST_GID=1000 docker compose --env-file environment/.env exec -T backend php artisan test --compact --filter='UrlSignerTest|StorefrontAssetTest'
```

**Cache usa Redis DB1** (no DB0). Antes de tests de cache: `redis-cli -n 1 FLUSHALL`.

Containers quedan levantados del work de Fase 2 — el writer puede iterar sin pagar startup cost.

---

## 7. Riesgos

| Risk | Mitigation |
|---|---|
| Refactor del test `it_caches_assets_in_redis_with_24h_ttl` rompe cobertura existente | El test sigue cubriendo lo mismo (cache write), solo cambia el assertion shape |
| `ASSETS_URL_SIGNING_SECRET` no seteado en `.env.example` | Fallback a `app.key` evita breakage; el writer debe agregar la línea al `.env.example` |
| HMAC signing de URL cambia el shape de URLs servidas | `required=false` por default — no breaking change. Flip post-backfill |
| 404 logging emite volumen alto en tests con paths inexistentes | `Log::spy()` no emite a stdout; el código real loguea solo en producción |

---

## 8. Follow-ups deferred (no en este plan)

- Flip `url_signing.required` a `true` (Fase 6, post-backfill)
- Flip `path_validation.strict` a `true` (Fase 6, post-backfill)
- Backfill de imágenes existentes (Fase 5)
- Migration pattern universal+extension (Fase 4)
- Cleanup legacy frontend (Fase 6)
- LP Supreme Blueberry Burst seeder (pre-arquitectura)