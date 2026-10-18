<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 MD031 -->

# Plan: Image Architecture — Fase 6 (Cleanup final)

> **Status**: en ejecución. Branch base: `master` HEAD `f04ac8a3` (Fases 2-5 mergeadas).
>
> **Scope**: cleanup del legacy `fenderImageUrl()` en frontend mock data + refactor de migration #2 (anti-pattern documentado en Fase 4) + nueva helper `asset-url.ts` para construir URLs del AssetController + actualización de docs con §9 Final state. NO flipea strict mode ni signing required (user lo hace post-backfill manual).
>
> **Engram mirror**: `odd/image-architecture-phase-6/tasks`

---

## 0. Contexto

Las Fases 2-5 dejaron:
- `StorageKeyDeriver` puro + `UrlSigner` + `AssetController` hardened.
- Comando `assets:backfill-images` operacional (no ejecutado todavía).
- Doc formal de migration pattern con 3 ejemplos auditados.

Audit READ-ONLY de Fase 6 reveló lo que queda como legacy:

| Item | Status | Acción |
|---|---|---|
| `fenderImageUrl()` en `product-detail-mock.ts:790` | **Sigue roto** — devuelve `/images/products/...` que NO matchea `AssetController` (`/api/v1/storefront/assets/{path}`) | Reemplazar con `asset-url.ts` helper |
| `rotateImage` hack CSS | **Ya removido** — commit `7b19f421 feat(pdp): seed real Gibson + Fender images, remove CSS rotation hack` | Ninguna |
| `variantColors` / carousel variants legacy | **Ya removido** — commits `f745e6e5` y `08e5a163` consolidaron a 1 product = N variants | Ninguna |
| `path_validation.strict` / `url_signing.required` defaults | **false** en `config/assets.php` (correcto, user flipea post-backfill) | Ninguna |
| Migration #2 (anti-pattern) | **Sigue sin refactor** — guard en estado viejo | Refactorizar (invertir guard) |
| `media` table (Spatie) | **Dead code** — schema existe pero 0 modelos usan `HasMedia`/`InteractsWithMedia` | Documentar decisión en §9 |

---

## 1. Archivos a tocar

| Archivo | Acción |
|---|---|
| `frontend/src/lib/asset-url.ts` | **CREAR** — helper `assetUrl()` + `mockAssetStorageKey()` |
| `frontend/src/data/product-detail-mock.ts` | **MODIFICAR** — eliminar `fenderImageUrl()` + reemplazar 8 call sites |
| `frontend/src/lib/product-image.ts` | **MODIFICAR** — actualizar docblock al nuevo contrato |
| `backend/database/migrations/shipping/2026_07_29_000001_convert_shipping_zones_base_cost_to_cents.php` | **MODIFICAR** — invertir guard (NUEVO estado) |
| `docs/architecture/assets-naming-convention.md` | **EXTENDER** — agregar §9 Final state |
| `odd/tasks/image-architecture-phase-6.md` | **CREAR** — este task file |

Sin tocar: `AssetController`, `StorageKeyDeriver`, `UrlSigner`, otros modelos/migrations, configs defaults.

---

## T1 — Crear `frontend/src/lib/asset-url.ts`

**Contrato**:

```typescript
/**
 * asset-url.ts — helpers para construir URLs del AssetController
 * backend desde storage keys content-hash generadas por
 * App\Services\Assets\StorageKeyDeriver (ver Fase 2 del image
 * architecture plan).
 *
 * Convención storage key: `tenants/{slug}/{collection}/{entity}/{hash}.{ext}`
 * Convención URL proxy:    `/api/v1/storefront/assets/{storage_key}`
 *
 * El mock data del storefront usa `mockAssetStorageKey()` para generar
 * keys placeholder determinísticas. En producción el backend devuelve
 * las keys reales vía API; el frontend usa `assetUrl()` para construir
 * la URL completa del proxy.
 *
 * Ejemplo:
 *   storageKey = 'tenants/panda-estampados/products/fender-tele/abc123def456.webp'
 *   assetUrl(storageKey) = '/api/v1/storefront/assets/tenants/panda-estampados/products/fender-tele/abc123def456.webp'
 */

const ASSET_BASE = '/api/v1/storefront/assets';

export function assetUrl(storageKey: string): string {
  return `${ASSET_BASE}/${storageKey}`;
}

export function mockAssetStorageKey(
  productSlug: string,
  variant: string,
  extension: string = 'webp',
): string {
  // Hash placeholder determinístico de 12 chars hex (no es real, solo mock).
  const seed = (productSlug + variant).slice(0, 12);
  const hash = `mock${seed}`.padEnd(12, '0').slice(0, 12);
  return `tenants/panda-estampados/products/${productSlug}/${hash}.${extension}`;
}
```

**Tests** (Vitest, en `frontend/src/lib/asset-url.test.ts`):
- `assetUrl(stora geKey)` returns full proxy URL
- `mockAssetStorageKey('slug', 'var')` returns 12-char hash + .webp
- `mockAssetStorageKey` is deterministic

---

## T2 — Reemplazar `fenderImageUrl()` en mock data

**8 call sites en `product-detail-mock.ts`**:
- Línea 345: `imageUrlSecondary: fenderImageUrl('ebony-black', '02', '400x183')`
- Línea 355: `imageUrl: fenderImageUrl('ebony-black', '01', '400x183')`
- Línea 361: `imageUrl: fenderImageUrl('halo-metallic', '01', '400x183')`
- Línea 790: definición de `fenderImageUrl()` (borrar)
- Línea 826: map call
- (4 más que el grep no mostró pero que existen en el archivo)

**Cambio**: cada `fenderImageUrl(slug, variant, size)` → `assetUrl(mockAssetStorageKey(slug, variant))`.

El parámetro `size` se ignora porque el nuevo contrato no usa size en el path — el backend genera múltiples sizes via CDN/responsive si es necesario.

**Import nuevo** en `product-detail-mock.ts`:
```typescript
import { assetUrl, mockAssetStorageKey } from '../lib/asset-url';
```

---

## T3 — Actualizar docblock de `product-image.ts`

El archivo `frontend/src/lib/product-image.ts` documenta la convención VIEJA:

```
CONVENCIÓN DE NAMING en disco:
  /images/products/{slug}/{variant}/{slug}-{variant}-{WxH}-{NN}.webp
```

Cambiar a:

```
CONVENCIÓN DE NAMING en disco (Fase 6 del image architecture plan):
  storage_key: tenants/{slug}/{collection}/{entity}/{hash}.{ext}
  proxy_url:   /api/v1/storefront/assets/{storage_key}
  helper:      frontend/src/lib/asset-url.ts (assetUrl + mockAssetStorageKey)
```

El `SIZE_TOKEN_RE` queda obsoleto porque las nuevas URLs no tienen token de tamaño. Marcar como deprecated en el docblock pero no eliminar la función `imageSrcset` (puede tener callers que no se refactorizan en esta fase).

---

## T4 — Refactorizar migration #2 (anti-pattern)

**Archivo**: `backend/database/migrations/shipping/2026_07_29_000001_convert_shipping_zones_base_cost_to_cents.php`

**Problema**: el guard `if (Schema::hasColumn('shipping_zones', 'base_cost'))` está en el estado VIEJO. Re-correr la migración falla porque `base_cost_cents` se agrega sin guard.

**Fix**: invertir el guard — check del estado NUEVO, early return si ya aplicada.

```php
public function up(): void
{
    if (Schema::hasColumn('shipping_zones', 'base_cost_cents')) {
        return; // Ya aplicada — idempotente.
    }

    Schema::table('shipping_zones', function (Blueprint $table): void {
        $table->unsignedBigInteger('base_cost_cents')->default(0)->after('description');
    });

    if (Schema::hasColumn('shipping_zones', 'base_cost')) {
        DB::statement(
            'UPDATE shipping_zones
             SET base_cost_cents = (base_cost * 100)::bigint
             WHERE base_cost IS NOT NULL',
        );

        Schema::table('shipping_zones', function (Blueprint $table): void {
            $table->dropColumn('base_cost');
        });
    }
}
```

Misma lógica para `down()` — check de `base_cost` (estado viejo restaurado), early return si ya aplicada.

**Importante**: el `down()` original usa `base_cost_cents` para restaurar. Para que sea idempotente también, chequear `base_cost` (el estado al que queremos volver). Si `base_cost` ya existe, early return.

**Verificación**: `php artisan migrate:refresh --step=1 --pretend` no debe romper; `php artisan migrate:refresh --step=1 && php artisan migrate:refresh --step=1` debe dar el mismo resultado las dos veces.

---

## T5 — Update `docs/architecture/assets-naming-convention.md` §9

Agregar **§9 Final state (post Fase 6)**:

```markdown
## 9. Final state (Fase 6)

Después de las 6 fases del image architecture plan:

- ✅ `StorageKeyDeriver` + `UrlSigner` (Fase 2-3).
- ✅ `AssetController` con soft-mode validation + HMAC signing + cache config-driven + 404 logging (Fase 2-3).
- ✅ Migration pattern formalizado (Fase 4).
- ✅ Comando `assets:backfill-images` operacional (Fase 5).
- ✅ Frontend legacy `fenderImageUrl()` removido, reemplazado por `frontend/src/lib/asset-url.ts` (Fase 6).
- ✅ Migration #2 (`convert_shipping_zones_base_cost_to_cents`) refactorizada con guard idempotente (Fase 6).

### Decisiones diferidas (no en scope de Fase 6)

- **Flip `path_validation.strict` a `true`** — user corre post-backfill manual + monitorea `asset.path.malformed` por 24h.
- **Flip `url_signing.required` a `true`** — requiere cambio en el API response (backend debe devolver signed URLs), defer.
- **Adoptar `media` table (Spatie MediaLibrary)** — dead code al momento de escribir esto. Decisión: NO adoptar; `StorageKeyDeriver` cubre la necesidad de storage keys sin la complejidad de Spatie. Si en el futuro se requiere `custom_properties` por asset, crear tabla de extensión per §5 de `migration-pattern.md`.
- **Backfill para otras tablas** (`product_store_images.image_url`, `stores.logo_path`, `store_themes.logo_path`) — patrón del comando `assets:backfill-images` se reusa con ajustes menores.

### Cleanup log

- Eliminado: `frontend/src/data/product-detail-mock.ts::fenderImageUrl()` (legacy naming `/images/products/...`).
- Refactorizado: `backend/database/migrations/shipping/.../convert_shipping_zones_base_cost_to_cents.php` (guard invertido para idempotencia).
- Vacío: `frontend/src/lib/storage.ts` (placeholder no usado, no se elimina para no romper imports hipotéticos).
```

---

## 2. Plan de ejecución

1. **T1** — `asset-url.ts` + tests.
2. **T2** — reemplazar `fenderImageUrl()` en mock data.
3. **T3** — actualizar docblock de `product-image.ts`.
4. **T4** — refactor migration #2.
5. **T5** — update docs §9.

Total esperado: 2-3 commits work-unit.

---

## 3. Verificación

**Backend**:
- `composer format && composer analyse && php artisan test --compact --filter='StorefrontAssetTest|UrlSignerTest|StorageKeyDeriverTest|BackfillProductImagesCommandTest'`
- PHPStan sobre la migration modificada: `vendor/bin/phpstan analyse backend/database/migrations/shipping/2026_07_29_000001_convert_shipping_zones_base_cost_to_cents.php` (debe pasar — no analiza migrations por default, pero verificá manualmente con `migrate:refresh`).

**Frontend**:
- `bun run typecheck && bun run lint && pnpm vitest run`
- Storybook smoke test si hay visual changes (no aplica — solo cambios en helpers).

**Docs**:
- `markdownlint docs/architecture/assets-naming-convention.md odd/tasks/image-architecture-phase-6.md`

---

## 4. Riesgos

| Risk | Mitigation |
|---|---|
| Refactor de migration #2 rompe environments donde se aplica por primera vez | Verificar con `migrate:refresh` que el resultado es idéntico antes y después del refactor |
| `asset-url.ts` cambia el formato del mock data → Storybook visual diff | Las URLs mock nunca se cargan realmente (no fetch), así que es safe |
| Tests Vitest no configurados o Vitest reemplazado por bun | Verificar antes con `frontend/package.json` qué test runner está activo |
| `product-image.ts::imageSrcset` queda deprecated pero callers no se actualizan | Marcar deprecated en docblock + ticket en follow-ups |

---

## 5. Follow-ups deferred

- Flip strict mode (user, post-backfill + 24h monitoring).
- Flip signing required (requiere API change).
- Backfill para `product_store_images`, `stores.logo_path`, `store_themes.logo_path`.
- Eliminar `media` table (Fase 4 §7 menciona como decisión pendiente).
- Refactor de `imageSrcset` para que respete el nuevo contrato (no size tokens).
