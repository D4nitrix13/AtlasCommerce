<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 MD031 -->

# Plan: Refactor `assets/` — centralización de imágenes

> **Status**: en ejecución. Branch base: `master` HEAD `f04ac8a3` (Fases 2-5 mergeadas).
> Refactor motivado por audit estructural de Fase 6 que reveló 3 ubicaciones distintas para imágenes con duplicados e inconsistencias.
>
> **Scope**: consolidar todas las imágenes del proyecto en una sola carpeta `assets/` en la raíz del repo, accesible tanto por el backend (vía filesystem disk + dev fallback) como por el frontend (vía symlink + Astro `public/`). Eliminar duplicados, actualizar mocks, seeders, comando Artisan de sync, y docs.
>
> **Engram mirror**: `odd/assets-refactor/tasks`

---

## 0. Contexto — audit estructural de Fase 6

| Folder | Contenido | Readers | Status actual |
|---|---|---|---|
| `frontend/public/images/` | `products/`, `tenants/panda-estampados/branding/`, `amico/auth/`, favicons | Astro estático (rutas `/images/*`) | Commiteado, disperso |
| `brand-assets/` | `frontend/amico/auth/`, `shared/icons/social/`, `store/{4 tenants}/{favicon,logo}.webp` | Artisan `SyncAssetsFromPublicCommand` migra a FTP | Commiteado, fuente de verdad para branding |
| `backend/public/images/stores/` | `stores/{4 tenants}/{banners,brand,products,social}/` | Nadie (`.gitignore` línea 42) | Fantasma, regenerado |

Problemas identificados:
- 3 ubicaciones, 3 organizaciones distintas.
- Duplicados: `frontend/public/images/amico/auth/*.svg` y `brand-assets/frontend/amico/auth/*.svg` son los mismos archivos.
- Cobertura inconsistente: 4 tenants en `brand-assets/store/`, solo 1 en `frontend/public/images/tenants/`. **La Voz Instrumentos** (primer cliente según el plan original) no tiene carpeta propia en ningún lado.
- `panda-estampados` hardcoded en mock data de `product-detail-mock.ts::fenderImageUrl()` cuando el dev tenant puede variar.

---

## 1. Estructura objetivo

```
AtlasCommerce/
├── backend/                     # Laravel code (sin imágenes commiteadas)
├── frontend/                    # Astro code
│   ├── public/
│   │   ├── assets -> ../../assets   # SYMLINK para dev
│   │   └── favicon.ico              # (se queda en public, no es asset de marca)
├── database/                    # Migrations + ER
├── assets/                      # ← NUEVO, raíz del repo
│   ├── README.md
│   ├── branding/                # Logos + favicons por tenant
│   │   ├── panda-estampados/
│   │   ├── kitsune/
│   │   ├── mm-boutique/
│   │   ├── morales-store/
│   │   └── instrumentos-musicales-la-voz/   # NUEVO (antes no existía)
│   ├── frontend/                # Ilustraciones + icons del sistema (no branded)
│   │   ├── amico/auth/
│   │   └── icons/
│   ├── products/                # Imágenes de productos (Fender/Gibson/etc.)
│   │   ├── fender-american-professional-stratocaster/
│   │   │   ├── 3-color-sunburst/
│   │   │   └── ...
│   │   └── gibson-mod-collection-es-335-smooth-sepia/
│   │       └── smooth-sepia/
│   └── shared/                  # Cross-tenant shared (social icons, etc.)
│       └── icons/social/
└── docker/
```

Convenciones de naming dentro de `assets/`:
- `branding/{tenant}/{file}` — logos + favicons del tenant.
- `frontend/{category}/{file}` — ilustraciones del sistema (no específicas de tenant).
- `products/{brand-slug}/{product-slug}/{variant}/{file}.webp` — imágenes de producto.
- `shared/{category}/{file}` — assets compartidos entre tenants.

---

## 2. Archivos a tocar (estimación)

| Archivo | Acción |
|---|---|
| `brand-assets/` | **ELIMINAR** después de migrar contenido |
| `frontend/public/images/` | **MOVER** contenido a `assets/`, eliminar directorio |
| `backend/public/images/stores/` | **ELIMINAR** (gitignored, regenerado, no commiteado) |
| `assets/` | **CREAR** + poblar |
| `frontend/public/assets` | **CREAR** como symlink a `../../assets` |
| `backend/config/filesystems.php` | **EXTENDER** con disk `local-assets` para fallback dev |
| `backend/app/Console/Commands/SyncAssetsFromPublicCommand.php` | **MODIFICAR** — leer de `assets/` en vez de `brand-assets/` |
| `docker/instances/development/panda-estampados-and-kitsune/docker-compose.yml` | **MODIFICAR** — comentario sobre source of truth |
| `frontend/src/data/product-detail-mock.ts` | **MODIFICAR** — `fenderImageUrl()` → helper nuevo con tenant env-injected |
| `frontend/src/data/la-voz-mock.ts` | **MODIFICAR** — usar helper nuevo |
| `frontend/src/data/tenants.ts` | **EXTENDER** — agregar `instrumentos-musicales-la-voz` al `TenantSlug` |
| `frontend/src/lib/asset-url.ts` | **CREAR** — helper con tenant env-injected |
| `frontend/tests/unit/lib/asset-url.test.ts` | **CREAR** — tests del helper |
| `docs/architecture/assets-naming-convention.md` | **EXTENDER** con §11 Refactor estructural |

---

## 3. Plan de ejecución (commits work-unit)

### Commit 1 — Crear `assets/` + migrar contenido de `brand-assets/`

```bash
mkdir -p assets/{branding,frontend,products,shared}
git mv brand-assets/store assets/branding
git mv brand-assets/shared/* assets/shared/
git mv brand-assets/frontend assets/frontend
rm -rf brand-assets
```

Cuerpo del commit: explica el por qué del cambio y la nueva estructura.

### Commit 2 — Migrar `frontend/public/images/` → `assets/` + symlink

```bash
git mv frontend/public/images/products assets/products
git mv frontend/public/images/tenants/panda-estampados assets/branding/_legacy_panda  # o absorbed
git mv frontend/public/images/amico assets/frontend/amico
rm -rf frontend/public/images
ln -s ../../assets frontend/public/assets
```

Nota: `frontend/public/images/tenants/panda-estampados/` solo tiene 2 archivos (favicon, logo) que ya están en `assets/branding/panda-estampados/` (migrados desde `brand-assets/store/`). Se eliminan los duplicados.

### Commit 3 — Backend dev fallback en `filesystems.php`

Agregar disk `local-assets` que apunta a `assets/` en la raíz del repo. En dev, el `AssetController` usa este disk si el FTP no está disponible. En producción sigue usando FTP.

```php
'local-assets' => [
    'driver' => 'local',
    'root' => base_path(path: '../assets'),
    'throw' => false,
],
```

### Commit 4 — Actualizar `SyncAssetsFromPublicCommand`

Cambiar `BRAND_ASSETS_BASE` de `'brand-assets'` a `'assets'` (path relativo a la raíz del repo). Ajustar las clases de assets según la nueva estructura (`frontend/amico/*` ahora vive en `assets/frontend/amico/*`, `shared/*` en `assets/shared/*`).

### Commit 5 — Helper `asset-url.ts` con tenant env-injected

```typescript
const DEFAULT_TENANT =
  (import.meta.env.PUBLIC_DEFAULT_TENANT as string | undefined) ?? 'panda-estampados';

export function assetUrl(storageKey: string): string {
  return `/assets/${storageKey}`;
}

export function tenantAssetPath(
  tenantSlug: string = DEFAULT_TENANT,
  collection: 'products' | 'branding' | 'marketing',
  entity: string,
  contentHash: string,
  extension: string = 'webp',
): string {
  return `tenants/${tenantSlug}/${collection}/${entity}/${contentHash}.${extension}`;
}
```

### Commit 6 — Actualizar mocks (`product-detail-mock.ts`, `la-voz-mock.ts`)

Reemplazar todas las llamadas a `fenderImageUrl()` con `assetUrl(tenantAssetPath(...))`. El slug del tenant viene del env, no hardcoded.

### Commit 7 — Agregar `instrumentos-musicales-la-voz` al `TenantSlug`

```typescript
export type TenantSlug =
  | 'panda-estampados'
  | 'kitsune'
  | 'mm-boutique'
  | 'morales-store'
  | 'instrumentos-musicales-la-voz';
```

Crear `assets/branding/instrumentos-musicales-la-voz/` con placeholder logo + favicon (o agregar un README.md indicando que está pendiente).

### Commit 8 — Update docs §11 Refactor estructural

Agregar §11 al `assets-naming-convention.md` documentando la nueva estructura y el por qué del refactor.

---

## 4. Riesgos

| Risk | Mitigation |
|---|---|
| Symlink rompe en Windows | Documentar; alternativa: copiar assets en build step |
| `import.meta.env.PUBLIC_DEFAULT_TENANT` no configurado en `.env` | Default a `panda-estampados` (dev tenant actual) |
| Archivos movidos rompen imports / referencias | Grep antes/después de cada `git mv` para validar |
| `SyncAssetsFromPublicCommand` cambia comportamiento en producción | Test en dev con `php artisan sync-assets --dry-run` antes de merge |
| `assets/` agranda el repo (imágenes son binarios grandes) | Considerar Git LFS si supera 100MB; documentar alternativa |

---

## 5. Verificación

**Después de cada commit:**
- `cd frontend && pnpm vitest run tests/unit/lib/asset-url.test.ts` — nuevo helper
- `cd backend && composer lint:dirty && composer analyse && php artisan test --compact --filter='StorefrontAssetTest|SyncAssetsFromPublic'`
- `cd frontend && pnpm typecheck && pnpm lint src/lib/asset-url.ts src/data/`

**Final:**
- `pnpm vitest run` — full suite
- `php artisan test` — backend full suite
- Manual: `pnpm storybook` y verificar que un componente de imagen (ej. ProductCard) sigue renderizando con la nueva ruta.

---

## 6. Follow-ups deferred

- Git LFS para `assets/` si supera umbral.
- Script de upload inicial a FTP (re-sync después del refactor).
- Auditoría de páginas que referencian paths hardcoded de imágenes (ej. `<img src="/images/products/...">` en componentes `.astro`).
- Test E2E con Playwright para flujo de imagen.