# Cierre de pendientes — opciones de atributos, E1.5 y fotos

Fecha: 2026-11-03. Continuación de `next-session-catalog-admin.md`
(sus puntos 3-frontend, 4 y 5) y de `catalog-attributes-units.md`.

## Objetivo

Cerrar los tres pendientes que quedaron tras el merge a master
(`afa4b88f` valores tipados, `a2f1d82c` diccionario expuesto):

1. **Punto 3 (frontend)** — editor de opciones de un atributo
   `type=select` en el admin (el backend ya expone y valida `options`).
2. **Punto 5** — terminar E1.5 (`is_hover`) para que salga de pausa.
3. **Punto 4** — fotos de producto restantes (16 de 26).

Al cierre, inventario de vistas entregadas (pedido explícito).

## Por qué ahora

El backend del diccionario quedó expuesto pero sin consumidor: el admin
sigue editando el valor de un `select` como texto libre y no puede
gestionar las opciones. E1.5 está en pausa desde `catalog-content-plan.md`
y el pipeline de `is_hover` tiene el runtime completo pero sin UI ni
cobertura que lo cierre.

## Restricciones

- Frontend: `bun` exclusivo (`~/.bun/bin/bun`), un comando pesado por
  bloque (`.ai/rules/tooling/resource-budget.md`).
- Backend: named args, strict_types, Pint (`vendor/bin/pint --dirty`).
- Tests con el comportamiento, no en commit aparte (skill
  `work-unit-commits`).
- Commits Conventional, sin Co-Authored-By. Rama feature si trabajo
  sobre la default.
- **Las fotos de producto las descarga el usuario a mano** (regla del
  proyecto): al agente le toca la lista, la ingesta
  (`scripts/ingest-product-images.sh`) y el seed. NO bulk-download.
- Antes de escribir en `assets/`: `.ai/rules/assets-naming-policy.md`.

## TDD

- **Modo:** activo (directiva `gentle-ai:strict-tdd-mode`).
- **Runner:** `~/.bun/bin/bun run test` (vitest) para lógica pura
  frontend; `php artisan test --compact` en el container para backend.
- RED → GREEN para lógica pura; para wiring Astro/TSX: typecheck + lint
  + tests.

## Tareas

- [x] T1. Editor de opciones de select en el admin (Punto 3 frontend).
      Ruta: delegated-writer. Incluye fix backend `store()` +
      `$fillable` (description).
- [x] T2. Cierre de E1.5 (`is_hover`) — cobertura de tests (37 nuevos)
      + `catalog-content-plan.md` actualizado. Commit `df18752c`. Ruta:
      delegated-writer.
- [x] T3. Fotos restantes (16 de 26): inventario listo (ver abajo);
      `incoming/` sin novedades que ingerir — falta la descarga manual
      del usuario. Ruta: inline.
- [x] T4. Inventario de vistas entregadas + cierre.

## Criterios de aceptación

1. Un atributo `type=select` se administra con editor de opciones
   (alta/baja de opciones) contra `POST/PUT /v1/admin/attributes`, y su
   valor en el producto se elige de esas opciones (no texto libre).
2. E1.5 deja de estar "en pausa": lo que cierre el gap queda verificado
   (tests + checks) y `catalog-content-plan.md` queda actualizado.
3. Estado de fotos documentado: cuáles de las 26 faltan y qué se ingirió.
4. `bun run typecheck` 0 errors; tests filtrados en verde; lint sin
   errores propios.

## Progreso

- (2026-11-03) Documento creado. Rama `feat/catalog-options-and-hover`.
- Gap E1.5 mapeado (explorador): el runtime backend está completo
  (migración `2026_10_23_210000`, `ProductImageController` con
  store/upload/update/reorder, `clearHoverFlag`, exclusividad
  primary↔hover 422, `HoverImageBackfiller`, `ProductPhotoGrouper`);
  el storefront ya lee `isHover`. **Lo que falta para cerrar E1.5 es
  cobertura de tests** (cero tests de is_hover / reorder / backfiller /
  grouper) + actualizar `catalog-content-plan.md`. La UI admin de
  upload/hover/drag-drop es **E2 (fase aparte)**, fuera de E1.5.
- Bug encontrado en el camino: `AttributeController::store()` no
  persiste `unit`/`options`/`applies_to` (solo `update()`), y
  `description` no está en `$fillable`. Va con T1.
- T3 fotos: `incoming/` tiene solo las 10 fuentes ya ingeridas
  (sin novedades). Faltan 16 de 26 — ver checklist abajo. Las fotos
  las descarga el usuario a mano (regla); al agente le toca ingesta.

## Faltantes de fotos (16 de 26, checklist corto)

Ya ingeridos (10 front): playera-manga-corta blanco+negro, taza-11-oz
blanco, hoodie negro, tote-bag blanco (checklist decía `crudo`),
gorra-trucker negro, libreta blanco (checklist decía `kraft`),
llavero-acrylic, playera-anime, taza-tematica (los 3 de Kitsune en
variante `original`).

Faltan en `incoming/{tenant}/{marca}/{producto}/{variante}/back.jpg`:

| # | Ruta incoming | Foto |
|---|---|---|
| 1 | `panda-estampados/panda/playera-manga-corta/negro/` | back |
| 2 | `panda-estampados/panda/playera-manga-corta/blanco/` | back |
| 3 | `panda-estampados/panda/taza-11-oz/blanco/` | back |
| 4 | `panda-estampados/panda/hoodie/negro/` | back |
| 5 | `panda-estampados/panda/tote-bag/blanco/` | back |
| 6 | `panda-estampados/panda/gorra-trucker/negro/` | back |
| 7 | `panda-estampados/panda/libreta/blanco/` | back |
| 8 | `kitsune/kitsune/llavero-acrylic/original/` | back |
| 9 | `kitsune/kitsune/playera-anime/original/` | back |
| 10 | `kitsune/kitsune/taza-tematica/original/` | back |
| 11-12 | `kitsune/good-smile/figura-escala-1-7/{variante}/` | front + back |
| 13-14 | `kitsune/good-smile/nendoroid/{variante}/` | front + back |
| 15-16 | `kitsune/shueisha/mango-tomo/{variante}/` | front + back |

Cuando el usuario deje las fotos: `SEED=true ./scripts/ingest-product-images.sh`
(un comando pesado por bloque; después `assets:seed-catalog`).

## Evidencia

**T1 cerrado (backend fix + editor de opciones) — commits `73f504b5`
(fix backend) y `207ce4b6` (feat frontend):**
- Backend TDD: RED `store_persists_dictionary_fields` +
  `store_persists_unit_for_number_attributes` (2 failed / 11 passed)
  → fix `AttributeController::store()` (persiste
  `unit`/`options`/`applies_to`) + `description` en
  `ProductAttribute::$fillable` → GREEN 13 passed (38 assertions).
  Pint `--dirty` passed.
- Frontend TDD: RED builders del diccionario (13 failed / 29 passed)
  → `slugify` + `attributeDictionaryPayloadFromForm` +
  `attributeDictionaryToApiBody` en `admin-product-specs.ts` →
  GREEN 42 passed.
- UI: `AttributeOptionsEditor.tsx` (110 l.), `AttributeDictionaryForm.tsx`
  (233 l.), `AttributeDictionaryTable.tsx` (74 l.), isla
  `AttributeDictionaryEditor.tsx` (226 l.), página
  `admin/catalog/attributes/index.astro` (26 l.).
  `ProductSpecsEditor.tsx`: `<select>` con `options` del atributo para
  `type=select`, fallback input texto + hint si no tiene opciones.
- Checks: `bun run typecheck` 0 errors; `bun run test` 465 passed
  (46 files); `bun run lint` 64 errors (== base, ~65 preexistentes),
  0 errores propios nuevos (solo warnings `max-lines-per-function`).
  `AttributeControllerTest` en container: 13 passed.

**T2 cerrado (E1.5) — commit `df18752c`:** 37 tests (22 unit + 15
feature) en `ProductImageControllerTest` (+12), `ProductImageUploadTest`
(+3), `ProductPhotoGrouperTest` (nuevo, 10) y `HoverImageBackfillerTest`
(nuevo, 12). Cubren exclusividad primary↔hover (422, sin escritura),
`clearHoverFlag` en store/update/upload, réplica de `is_hover` en las 4
resoluciones del upload (mismo `sort_order`; `is_primary` solo en el
ancho mayor), `reorder()` por grupos `(variant_id, sort_order)` con
rechazos 422, y la derivación del backfiller (cascada
back→backbody→body desde el `alt_text`). Verde desde la primera
corrida (cobertura faltante, no bugs). `--filter=ProductImage` → 76
passed (295 assertions). Pint passed. Sin cubrir (documentado por el
writer): comando `assets:backfill-image-hover` (sin feature test),
storefront `isHover` vía HTTP (middleware tenant, frágil).

**Nota de entrega:** `admin-product-specs.ts` se había quedado fuera
del commit `207ce4b6` (un pathspec inválido en el mismo `git add` lo
abortó) — entró en `57ed1c2b`. Revisar `git add` con paths de un solo
tiro, sin argumentos inválidos.

**T3 (fotos):** sin cambios que ingerir. Las 16 fotos faltantes están
listadas arriba — las descarga el usuario a `incoming/`, después
`SEED=true ./scripts/ingest-product-images.sh`.

## Inventario de vistas (T4, pedido del usuario)

~79 páginas `.astro` (más la de atributos de esta tanda = 80):

- **Storefront (10):** home, PLP `products/`, PDP `products/[slug]`,
  reviews, search, cart, checkout, RFQ (+confirmación), bulk-order,
  categories.
- **Cuenta (14):** dashboard, login, signup, forgot/reset-password,
  reset-success, 2FA, security, notifications, GDPR, orders (lista +
  detalle), addresses, wishlist.
- **Admin (55):** dashboard, catálogo (productos lista/crear/detalle
  con specs editor, brands, categories, variants, **atributos — nuevo**),
  sales (orders lista/detalle, invoices lista/detalle), inventory
  (stock, movements, warehouses lista/detalle), marketing (coupons
  lista/detalle, promotions), customers, users, roles, settings (index,
  store, theme, taxes, currencies), payments (methods, installments),
  shipping (zones, companies), purchases (suppliers, orders), returns,
  support (tickets lista/detalle, questions, reviews), system (audits,
  notifications), reports.

## Próximo paso

Lo que queda fuera de este batch (para la próxima):

1. **Fotos** — cuando el usuario baje las 16, correr
   `SEED=true ./scripts/ingest-product-images.sh` (un comando pesado
   por bloque).
2. **E2** — UI admin de galería (upload real, marcar primary/hover,
   drag & drop de `sort_order`); `MediaUploader.astro` sigue siendo
   mock. También falta link en el sidebar a
   `/admin/catalog/attributes`.
3. **E1.6** — idempotencia de re-subida de imágenes.
4. Decidir merge de `feat/catalog-options-and-hover` a `master` (y el
   push — el usuario lo pide explícito).
