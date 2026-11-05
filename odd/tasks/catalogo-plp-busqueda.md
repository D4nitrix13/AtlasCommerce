# Catálogo (PLP) + Búsqueda — feature doc ODD (Sesión 2 / vista 2)

> **Worktree**: `~/Code/AtlasCommerce-worktrees/catalogo` · **Rama**: `feat/views/catalogo`
> **Brief madre**: [`vistas-cliente-sessions.md`](vistas-cliente-sessions.md) §Sesión 2.
> **Mockup fuente**: `04-products-list.html` (dentro de `atlascommerce-views.zip`).
> **Detalle del usuario**: "haz lo que me recomiendes y empieza a hacerlo" (2026-11-05) —
> el spec de esta sesión es el brief + mockup 04 + inventario + criterio del agente.

## Objetivo

Que el descubrimiento (listado de productos, filtros, orden, búsqueda) replique el
mockup 04 respetando el patrón **SSR por query params** (links, sin JS).

## Problema

Inventario mockup 04 vs código (2026-11-05): faltan chips de filtros activos,
estados error/categoría inexistente, drawer de filtros en móvil, opciones de sort,
rating en cards; y los filtros color/precio/sort **no llegan al API** (recortan solo
la página actual de 24 filas).

## Scope autorizado

- `frontend/src/pages/[locale]/products/index.astro`
- `frontend/src/pages/[locale]/search/index.astro`
- `frontend/src/components/storefront/products/**`
- `frontend/src/lib/catalog-filters.ts`, `frontend/src/lib/pagination.ts`
- `frontend/src/i18n/locales/{es,en}.ts` + `types.ts` — **solo aditivo**, keys `storefront.catalog.*`

**Fuera de scope**: `backend/**` (reportar como hallazgo), `frontend/src/stores/cart.ts`
(sesión 4), `layouts/**` + `components/storefront/layout/**` (sesión 1), admin.

## Restricciones

- **Worktree propio por sesión** (regla del usuario): nunca tocar el checkout principal
  `/home/d4nitrix13/Code/AtlasCommerce` (tiene trabajo paralelo sin commitear).
- **Sin JS**: filtros y sort navegan por links (`aria-current`, `data-href`). No introducir islas.
- **Full-width**: las vistas ocupan todo el ancho disponible, padding simétrico; nada de
  `max-w` estrechos (regla de proyecto MEMORY.md).
- **bun** (`~/.bun/bin/bun`, no está en PATH; `PATH="$HOME/.bun/bin:$PATH"` para scripts).
  Nunca npm/pnpm. Un comando pesado por bloque (`.ai/rules/tooling/resource-budget.md`).
- **TDD estricto**: fuente `gentle-ai strict-tdd-mode` (enabled); runner `bun run test`
  (vitest) en `<worktree>/frontend`. RED observado → GREEN → REFACTOR.
- Commits work-unit, Conventional Commit en español, prosa plana. **Sin push/PR** salvo pedido.

## Decisiones (2026-11-05, criterio del agente ratificado por el usuario)

1. **Paginación: se mantiene `?page=N`** con links. El "Cargar más productos" del mockup
   se descarta porque requiere JS y el brief exige que el listado funcione sin JS.
2. **`category` sí se envía al API** (`loadCatalogPage`) — el backend ya lo soporta en
   `ProductController.index`; corrige conteos y paginación por categoría.
3. **color/precio/sort quedan como hallazgo H-1** (requieren params nuevos en backend,
   fuera de scope de esta sesión).
4. i18n aditivo: keys nuevas `storefront.catalog.*`; NO renombrar `storefront.products.v3.*`.

## Hallazgos (documentados, sin implementar)

- **H-1**: `ProductController.index` solo acepta `category`/`page`/`per_page`; color/precio/sort
  y sort por rating/novedad filtran solo la página actual vía `applyCatalogFilters`.
  Requiere backend (params + filtros server-side). Reportar, no implementar.
- **H-2**: `.ai/rules/workflow/decisions-storefront-rendering.md` está desactualizado
  (dice CSR island; el código es SSR tras la remoción deliberada de la island,
  `products/index.astro:444-479`). Proponer actualización del doc.
- **H-3**: `GridItem` listado en el brief no existe en el repo.
- **H-4**: el payload del API no incluye rating de producto (cards sin ★); "Mejor
  calificados"/"Más recientes" dependen de datos que hoy no llegan.
- **H-1b** (derivado de WU1): con `category` filtrando en el API, las facetas del sidebar
  (`categoryCounts`, derivadas de `apiProducts`) colapsan a `[Todas, <activa>]` mientras hay
  categoría activa — se requiere doble click para cambiar de categoría. Aceptado: la grilla,
  el conteo y la paginación de la categoría activa ahora son correctos y `Todas` siempre
  está disponible. Resolución definitiva = facetas en backend (familia H-1); posible mitigación
  en WU3.
- **H-5** (docs): `frontend/AGENTS.md` §16 menciona `i18n/es.json|en.json` — en disco i18n es
  `locales/{es,en}.ts` + unión `TranslationKey` en `types.ts` (agregar claves en los tres).
  Convención de tests: `frontend/tests/unit/**` (los `.astro` se testean por source-matching
  estructural); `bunx eslint` debe correr desde `frontend/` (desde la raíz da errores falsos).

## Checklist (work-units)

- [x] **WU1** — Chips de filtros activos (links para quitar, sin JS) + `category` al API + i18n aditivo + tests — commits `91b438f2` + `92ab5eac`; spot check parent: 37 tests verdes
- [x] **WU2** — Estados PLP: error (API caída ≠ vacío), empty, categoría inexistente — commit `69da5496`; **loading N/A** (PLP SSR, sin gap de fetch)
- [ ] **WU3** — Drawer de filtros en móvil (sidebar → drawer < lg)
- [ ] **WU4** — Sort extra + rating en cards: solo si los datos existen; si no, cerrar H-1/H-4 sin código
- [ ] **WU5** — Búsqueda `/search`: query, resultados, sin resultados (verificar/ajustar)
- [ ] **WU6** — Cierre: checks comunes + capturas playwright 1440/390 + reporte

## Criterios de aceptación

- Filtros funcionan sin JS; deep-links por URL; chips de filtros activos removibles por link.
- Estados: empty / error / éxito visibles (loading N/A en SSR).
- 0 errores nuevos (tests + lint por archivo); cards sin mocks; full-width.
- Capturas desktop 1440 + mobile 390.

## Verificación

- Por WU: tests filtrados (`bun run test -- <archivos>`) + `bunx eslint <archivos tocados>` (0 nuevos vs HEAD).
- Cierre (WU6): `bun run test` · `bun run astro check` · `bun run build` · capturas playwright.
- Nota runtime: `gentle-ai review assess` es inoperable en MiMoCode (`unassessable`);
  verificación = self-verification del writer + spot check del parent (memoria de proyecto).

## Progreso / evidencia

- 2026-11-05: inventario mockup 04 vs código (explorer delegado, verificado contra el código).
  Render mode confirmado SSR; gap principal H-1 verificado en `loadCatalogPage`.
- **WU1 cerrado** (commits `91b438f2` chips, `92ab5eac` category→API; 430 ins / 7 del en 9 archivos):
  TDD RED→GREEN observado (`buildCatalogQuery is not a function` → 21 verdes; luego 11 fallas →
  37 verdes). Lint: 0 errores nuevos vs HEAD (1 error + 3 warnings preexistentes en HEAD).
  Spot check del parent: `bun run test -- tests/unit/lib/catalog-filters.test.ts
  tests/unit/components/storefront/products/FilterChips.test.ts tests/unit/i18n/catalog-chips.test.ts`
  → 37 passed. Hallazgo nuevo: H-1b (facetas del sidebar).
- **WU2 cerrado** (commit `69da5496`; 348 ins / 23 del en 8 archivos): `loadCatalogPage`
  devuelve resultado discriminado (`{ ok: true, data, meta }` / `{ ok: false }`) — el
  catch ya no devuelve `EMPTY_CATALOG_META`, así un API caído renderiza el estado
  ERROR (mensaje i18n + retry `<a href>` a la misma URL, sin JS) y no el empty state.
  El re-fetch del clamp de página también cuenta: fallo en cualquiera de los dos
  fetches = estado de error. Variante "categoría sin resultados" (empty + categoría
  activa): link que limpia la categoría vía `buildCatalogHref` (mecanismo de los
  chips, sin JS) — cubre categoría inexistente Y categoría con 0 productos porque el
  API no distingue ambos casos (comentado en el template). Componente nuevo
  `CatalogStateMessage.astro` (paleta paper/ink/line del empty state existente).
  El fallback Panda ya no enmascara errores del API. **Loading: N/A** — el PLP es
  SSR puro (no hay gap de fetch que renderizar): el estado "cargando" del brief no
  aplica y no se escribió código para ello. TDD RED→GREEN observado por ciclo
  (i18n 3 rojas → 3 verdes; componente ENOENT → 4 verdes; error≠empty 6 rojas →
  6 verdes; categoría vacía 3 rojas → 10 verdes del archivo). Verificación:
  54 tests verdes (37 WU1 + 17 nuevas); `bunx eslint` sobre los 8 archivos
  tocados: 0 problemas.

## Siguiente paso

WU3 — drawer de filtros en móvil (sidebar → drawer < lg) — writer delegado.

## Ruta de ejecución

Delegado direct (un writer por WU). Triggers: writer rule + long-session backstop.
Exploración inicial: delegada (mapper, 4+ archivos). SDD no aplica (no fue pedido).

> Mirror Engram `odd/catalogo-plp-busqueda/tasks`: **pendiente** (Engram MCP no disponible
> en esta sesión) — el progreso vive en este archivo.
