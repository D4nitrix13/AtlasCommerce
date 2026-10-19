# Feature: Asset Pipeline Refactor — Multi-tenant Catalog Migration

> **Status:** IN-PROGRESS — MVP complete (guitars + basses migradas)
> **Owners:** dev (Daniel)
> **Started:** 2026-10-19
> **Last updated:** 2026-10-19

---

## Goal

Refactorizar `assets/` para soportar multi-tenant per-product organization,
eliminar dependencias del LFS que excede quotas, y migrar el dataset
catalog completo de La Voz Instrumentos (400+ productos) desde el staging
gitignored `~/.local/atlascommerce-archive/gibson-images/` hacia el repo.

---

## Por qué ahora

- El cliente real "La Voz Instrumentos" necesita un catálogo musical
  completo (guitarras, bajos, amps, pedales, cuerdas, capos, cases, apparel,
  parts) — el repo solo tenía 11 productos Gibson antes de esta feature.
- El staging `gibson-images/` scrapeado de gibson.com (sesión previa) tiene
  410 productos en 9 categorías pero estaba gitignored sin path al repo.
- La estructura per-tenant necesitaba formalizarse: antes, todos los
  productos vivían flat en `assets/products/{slug}/` sin distinguir a qué
  tienda pertenecían.

---

## Estado verificado (post-Fase MVP)

### Lo que YA está hecho (commits en master)

1. **`ad74103e`** — Backup `gibson-images/` (4.7 GB LFS) movido a
   `~/.local/atlascommerce-archive/`. LFS quota liberada en GitHub.
2. **`ed3b6953`** — Productos Fender removidos del catálogo (respaldados).
3. **`cc9d24f4` + `bcd80680` revert + `74feb606`** — Decisión arquitectural
   del split per-tenant (Opción A → Opción B con manifests → revert →
   Opción C literal). Journey documentado en
   `docs/architecture/assets-naming-convention.md` §12.
4. **`dee87874`** — Subárbol Fender en `instrumentos-musicales-la-voz/fender/`
   removido (La Voz no distribuye Fender).
5. **`bff63eae`** — Script `scripts/migrate-gibson-images.sh` para convertir
   PNGs del staging a WebP multi-size en el repo per-tenant.
6. **`8c61321d`** — Migración guitars: 43 productos (Gibson, Gibson Custom,
   Epiphone, Kramer), 6,773 WebPs. ~432 MB.
7. **`ccc4ffdd`** — Migración basses: 18 productos (Gibson, Epiphone),
   5,653 WebPs. ~318 MB.

### Totales migrados a `assets/products/instrumentos-musicales-la-voz/`

| Marca | Productos | WebPs |
|---|---|---|
| gibson | 31 | 5,590 |
| gibson-custom | 2 | 196 |
| epiphone | 9 | 5,176 |
| kramer | 1 | 1,464 |
| **TOTAL** | **61 productos** | **~12,426 WebPs** (~750 MB) |

### Documentación actualizada (también commiteada)

- `assets/README.md` — Sección "Imagenes de producto (Categoria D)" con
  contrato per-tenant literal + decision record (A→B→C journey).
- `docs/architecture/assets-naming-convention.md` — Changelog Oct 19,
  §11 "Cambios por archivo" actualizado, §12 "Decision record" nuevo.

### Backup preservado

```
~/.local/atlascommerce-archive/
├── gibson-images/      (4.7 GB, 410 productos, 9 categorías — staging preservado)
└── fender-products-v2/ (6.4 MB, 4 productos, 160 webps — gitignored preservado)
```

NO se ha limpiado nada del backup. Es intencional — el staging es la
fuente de verdad para futuras migraciones.

---

## Scope por fase

### ✅ Fase 0 — Cleanup bloqueante (DONE)

- Backup LFS a local archive
- Remoción de productos Fender del catálogo
- Refactor assets/products/ a estructura per-tenant literal
- Subárbol Fender limpiado (post-mistake-de-insercion-en-Opcion-B)

### ✅ Fase 1 — Política durable (DONE via docs)

- Documentación actualizada en `assets/README.md` y
  `docs/architecture/assets-naming-convention.md` §11/§12.
- ⚠️ **Falta**: regla durable `.ai/rules/assets-naming-policy.md` que un agente
  futuro lea antes de tocar `assets/**`. Documentación narrativa en MD
  no es enforcement — un agente AI podría saltarla. **Pendiente**.

### ✅ Fase 3 — Refactor ProductSeeder (PARTIAL)

- El seeder actual (`backend/database/seeders/ProductSeeder.php`) SIGUE
  escribiendo URLs legacy `/images/products/...` que NO existen en disco
  después del refactor per-tenant.
- Los 4 productos Fender fueron removidos del seed pero los 11 Gibson
  actuales son de una selección previa, no de la migración fresca.
- **Pendiente**: re-poblar seed con los 61 productos del repo per-tenant
  usando `StorageKeyDeriver` para generar los storage_keys hasheados.

### ⏸️ Fase 4 — Extender SyncAssetsFromPublicCommand (DEFERRED)

- El comando `php artisan assets:sync-from-public` actual SOLO sincroniza
  `branding/` y `shared/`. NO sincroniza `products/`.
- **Pendiente**: extender el comando para hashear y subir los 12,426 webps
  del repo `assets/products/instrumentos-musicales-la-voz/{brand}/` al FTP
  en `tenants/{slug}/products/{slug}/{hash12}.webp`.
- Riesgo principal: GitHub LFS quota para subir ~750 MB via xargs. Si se
  configura LFS en el repo, hay que pagar el plan o usar GitLab.

### 🚧 Fase 5 — Migración selectiva (PARTIAL)

- **DONE**: guitars (43 productos, 6,773 webps) + basses (18 productos,
  5,653 webps) = 61 productos. Estructura uniforme 4-niveles
  `{cat}/{brand}/{product}/{variant}/`.
- **DEFERRED (por decisión del usuario MVP-first)**: 7 categorías con
  estructura heterogénea:
    - `amps` — sub-categoría `heads|combos` antes de brand
    - `pedals` — sin variant (3 niveles)
    - `strings` — sub-categoría `acoustic|electric|bass` antes de brand
    - `capos` — sin variant (3 niveles)
    - `cases` — sub-categoría `hard|soft` antes de brand
    - `apparel` — sub-categoría `headwear|shirts|sweatshirts`
    - `parts` — sub-categoría `pickups|covers|tailpieces`
  - **Estimado**: ~270 productos, ~2,800 webps adicionales, ~250 MB más.
  - **Requiere**: extender el script con detección de profundidad o crear
    un script variante por estructura.

### ⏸️ Fase 6 — Estrategia uploads admin prod (DEFERRED)

- Bulk upload UX para admin (administrador no debería subir 1-imagen-a-la-vez).
- Opciones documentadas en el chat de planning pero no implementadas:
    - Bulk ZIP upload
    - Worker asíncrono que procesa por cola
    - Admin dashboard drag&drop con preview
- Cualquiera de estas es trabajo separado de varios días.

---

## Constraints

- **Conventional commits** en español, scopes: `assets`, `scripts`, `seeders`,
  `docs`, `backend`.
- **NO** `Co-Authored-By`.
- Tamaños AGENTS.md §1: simple ≤150 líneas, mediano ≤250, página ≤400.
- Las imágenes del repo NO exceden 1 GB para evitar LFS quota issues.
- Storage keys siempre hasheados via `StorageKeyDeriver`
  (no reversibles) y storage path `tenants/{slug}/products/{slug}/{hash12}.webp`.

---

## Acceptance criteria

- [x] Estructura per-tenant implementada y commiteada
- [x] LFS quota liberada (sin dependency de GitHub LFS)
- [x] Documentación actualizada (README + naming-convention §11/§12)
- [x] Script de migración genérico funcional y commiteado
- [x] Guitars + basses migradas (61 productos, ~12.5K webps)
- [x] Backup preservado en `~/.local/atlascommerce-archive/` (no eliminado)
- [ ] **Pendiente**: 7 categorías heterogéneas migradas (amps, pedals, strings,
      capos, cases, apparel, parts) — ~270 productos, ~2,800 webps adicionales
- [ ] **Pendiente**: regla durable `.ai/rules/assets-naming-policy.md`
- [ ] **Pendiente**: refactor `ProductSeeder.php` para usar manifest per-tenant
      + `StorageKeyDeriver`
- [ ] **Pendiente**: extender `SyncAssetsFromPublicCommand` para subir products/
      al FTP hasheado
- [ ] **Pendiente**: estrategia de bulk upload admin (Fase 6)

---

## Decisiones tomadas (registradas)

1. **Opción C (per-tenant literal)** sobre Opción B (`_images/` + `_manifest/`):
   por simplicidad del filesystem + reflección directa del ownership en
   cada carpeta.
2. **Hash determinístico (SHA-256 prefix 12)** vs nombres en crudo:
   hash resuelve el anti path-traversal y el cache-busting en runtime.
3. **4 sizes WebP (400/800/1200/1600)** vs single resolution: cada tenant
   puede pedir cualquier size via `imageSrcset()` del frontend.
4. **Naming `{role}-{WxH}.webp`** vs `{WxH}-{role}.webp`: mantuve la convención
   existente del repo para no romper el frontend actual.
5. **MVP con guitars + basses** vs migración completa: el usuario priorizó
   cerrar el sprint y dejar las 7 categorías heterogéneas para sesión futura.

---

## Cómo retomar esta feature (sesión futura)

1. `mem_context --project atlascommerce` — leer contexto reciente
2. `mem_search "catalog-migration-midsprint"` — encontrar este plan
3. Revisar `.local/atlascommerce-archive/` (debe tener ~4.8 GB totales)
4. Para Fase 2 (regla durable): crear `.ai/rules/assets-naming-policy.md`
5. Para Fase 5 (migración heterogénea): extender `scripts/migrate-gibson-images.sh`
   con detección de profundidad o crear `scripts/migrate-flexible.sh`
6. Para Fase 3 (ProductSeeder): refactor `backend/database/seeders/ProductSeeder.php`
   para leer el manifest de tenant y usar `StorageKeyDeriver`

---

## Commits de esta feature (en orden cronologico)

```
290ecfc9  feat: add Gibson reference image archive (LFS)            [OBSOLETO]
ad74103e  feat: remover gibson-images del repo LFS tracking         [Fase 0]
ed3b6953  feat: remover productos Fender del catalog                [Fase 0]
cc9d24f4  feat(assets): refactor _images/ + _manifest/ (Opcion B)   [REVERTIDO]
bcd80680  Revert "feat(assets): refactor _images/ + _manifest/"    [decision]
74feb606  feat(assets): refactor per-tenant literal (Opcion C)     [Fase 0/1]
dee87874  feat(assets): remover subarbol fender/                    [Fase 0]
bff63eae  feat(scripts): add migrate-gibson-images.sh                [Fase 5]
8c61321d  feat(assets): migrar guitars del backup                    [Fase 5]
ccc4ffdd  feat(assets): migrar basses del backup                     [Fase 5]
```

Logline del journey de esta sesión:

```
Fase 0: 290ecfc9 → ad74103e → ed3b6953 → cc9d24f4 → bcd80680 → 74feb606 → dee87874
Fase 5 (parcial): bff63eae → 8c61321d (guitars) → ccc4ffdd (basses)
```

---

## Riesgos y mitigaciones

| Riesgo | Mitigación actual |
|---|---|
| LFS quota GitHub free tier (1 GB) | Backup movido a `~/.local/`, LFS quota liberada |
| Storage del repo crece (+750 MB) | Aceptable en GitHub regular; GitLab LFS sería 10 GB |
| Script de migración aborted | Tiene fallback para roles desconocidos + no aborta batch |
| 7 categorías no migradas | Decisión MVP — pendiente sesión futura |
| Backup externo se llene el disco | Monitorear espacio en `/home` (55 GB libre, ~5 GB usado) |
| Documentación desactualizada | `assets/README.md` y naming-convention §12 actualizados |
| `ProductSeeder` PHP desactualizado | Pendiente Fase 3 — funcionalidad rota al eliminar Fender |

---

## Future work (después del MVP)

1. **Fase 5-extended**: extender script para amps/pedals/strings/cases/apparel/parts
   (~270 productos, ~2,800 webps adicionales, ~250 MB)
2. **Fase 2**: crear `.ai/rules/assets-naming-policy.md` para enforcement
3. **Fase 3**: refactor `ProductSeeder.php` para usar manifest + StorageKeyDeriver
4. **Fase 4**: extender `SyncAssetsFromPublicCommand` para subir products/ al FTP
5. **Fase 6**: estrategia de bulk upload admin (varias opciones a evaluar)

---

## Refs

- `assets/README.md` — convención operativa
- `docs/architecture/assets-naming-convention.md` §11/§12 — decisión arquitectónica
- `scripts/migrate-gibson-images.sh` — herramienta de migración
- Engram topic `state/catalog-migration-midsprint-oct19` — estado runtime
