# Plan — imágenes propias, vistas de cliente y header estilo Nike

Fecha: 2026-11-03. Pedido del usuario (cambios de plan): dejar de
buscar fotos en internet (impráctico, inconsistente), centrarse en las
vistas de cliente priorizando las de producto, subir imágenes al FTP
con seeders, y un header parecido al de Nike que use todo el ancho.

## Decisiones del usuario (nuevas, pisan a las anteriores)

1. **Las fotos NO se buscan en internet.** El usuario entrega imágenes
   "random" (cualquier origen, cualquier nombre). El agente las
   **renombra**, les **cambia el formato a WebP** con el script de
   conversión (ya existe: `scripts/ingest-product-images.sh`) y sigue
   el pipeline de siempre: árbol canónico `assets/products/…` →
   `assets:seed-catalog` → hash + **FTP** + `product_images`.
2. **Foco en vistas de cliente, prioridad: las de producto.** Y con
   esas imágenes crear los seeders de datos y subirlo todo.
3. **Header estilo Nike** (referencia adjunta del usuario): una fila,
   logo izquierda, nav centro, acciones a la derecha. El actual se ve
   "horrible" y rompe líneas ("Mi cuenta" en dos).
4. **Regla de ancho completo** (duradera, ya en `MEMORY.md` ## Rules):
   las vistas ocupan TODO el ancho disponible; nada de `max-w` estrechos
   con márgenes muertos. En esta sesión se verificó que esa instrucción
   **no se había persistido** en una sesión anterior — se guardó ahora.

## Estado verificado (pre-sesión de ejecución)

- `scripts/ingest-product-images.sh` **existe** (incoming → WebP
  1600/1200/800/400 q85 → árbol canónico → `SEED=true` = seed-catalog
  con hash + FTP). `scripts/convert-product-images.sh` también existe
  (otra convención, legacy La Voz). **No hay que crear script de
  conversión** — lo nuevo es la asignación/renombre de imágenes random.
- Header real: `frontend/src/components/storefront/layout/StorefrontHeader.astro`
  (126 líneas) + `StorefrontHeaderActions.astro` (62). Causa del
  desperdicio de ancho: `max-w-[1280px]` en el contenedor interno
  (línea 74). Causa del wrap: textos largos ("Mi cuenta", "Cotizar",
  banderas ES/EN) en la misma fila.
- `assets:seed-catalog` + `CatalogAssetsSeeder` + `PandaKitsuneCatalogSeeder`
  ya cubren hash/subida FTP/BD para el árbol `assets/products/`.

## Fases

### F1 — Flujo de imágenes random → WebP → FTP (previo a las vistas)

- [ ] T1. Convención de drop: el usuario deja archivos sueltos en
  `incoming/_drop/` (o los adjunta). El agente los **asigna** a
  `tenant/marca/producto/variante/rol` (rol del vocabulario
  `assets-naming-policy` §3: front/back/side/beauty…), los renombra a
  `incoming/{tenant}/{brand}/{producto}/{variante}/{rol}.{ext}` y corre
  `./scripts/ingest-product-images.sh` (o `SEED=true …` para subir).
  **Pregunta abierta (una sola):** cómo se entera el agente de qué
  producto/variante corresponde cada imagen random.
- [ ] T2. Seeders con esos datos: verificar que el catálogo de las
  vistas de producto está sembrado (productos/variantes/tallas/
  atributos de Panda y Kitsune) y ajustar `PandaKitsuneCatalogSeeder`
  o crear seeders si el usuario trae datos nuevos. Al final:
  `assets:seed-catalog` (hash + FTP + `product_images`).

### F2 — Vistas de cliente, prioridad producto

Definición propuesta (orden de trabajo):

| # | Vista | Qué se resuelve |
|---|---|---|
| 1 | **PDP** `products/[slug]` | galería con las imágenes reales + hover/`is_hover`, swatches de variante, picker de talla/capacidad, ficha técnica EAV, add-to-cart |
| 2 | **PLP** `products/index` | grilla full-width, cards con foto real (primary+hover), filtros/orden SSR que ya existen |
| 3 | **Home** `[locale]/index` | hero + destacados del catálogo real |
| 4 | **Cart / checkout** | flujo con los ítems reales (ya client-side) |
| 5 | B2B (RFQ/bulk-order) | después; no es prioridad |

- [ ] T3. Auditoría de ancho: quitar caps `max-w-*` estrechos en las
  vistas de cliente (home, PLP, PDP, cart, checkout) y dejar el layout
  full-width con padding simétrico. Regla nueva ya en `MEMORY.md`.
- [ ] T4. PDP con imágenes reales (F1) + `is_hover` + specs.
- [ ] T5. PLP full-width con cards de foto real.
- [ ] T6. Home con destacados reales.

### F3 — Header estilo Nike (imagen-to-code: target = screenshot del usuario)

- [ ] T7. `StorefrontHeader.astro` + `StorefrontHeaderActions.astro`:
  - Una sola fila, **full-bleed** (sin `max-w-[1280px]`), padding
    simétrico `px-6 lg:px-10`.
  - Izquierda: logo + marca. Centro: nav en links limpios (sin
    dropdowns pesados que rompan la fila). Derecha: **search pill +
    iconos** (wishlist, cuenta, carrito) — estilo Nike: solo iconos,
    sin texto que haga wrap.
  - Locale: toggle compacto ES/EN (no dos pills con bandera).
  - "Cotizar" (RFQ B2B): pill primaria compacta o ítem de nav — sin
    romper la fila.
  - Estados: hover/focus/active en cada control; hit area ≥40px.
  - Verificación visual en browser (1440/1280/768) con playwright.

## Restricciones

- Frontend `bun` (`~/.bun/bin/bun`); containers por
  `scripts/docker-stack.sh`; un comando pesado por bloque.
- Antes de escribir en `assets/`: `.ai/rules/assets-naming-policy.md`.
- Imágenes: solo WebP q85, 4 widths, kebab-case, 0 PNG/JPG en `assets/`.
- i18n ES+EN en strings nuevas; TypeScript strict; Tailwind por
  intención; estados loading/empty/error.
- Header: `className` en TSX (nunca `class`), componentes ≤250 líneas.
- TDD para lógica pura (asignación/renombre de imágenes, mapping de
  datos); UI → typecheck + lint + verificación visual real.

## Criterios de aceptación

1. Imágenes random del usuario → WebP canónico → FTP + `product_images`
   sin intervención manual de renombre.
2. PDP y PLP muestran fotos reales del catálogo con hover y variantes.
3. Header en una fila, estilo Nike, sin wrap, a todo el ancho.
4. Ninguna vista de cliente con márgenes muertos por `max-w` estrecho.
5. `bun run typecheck` 0 errors; tests en verde; verificación visual
   documentada.

## Progreso

- (2026-11-03) Plan creado por pedido del usuario. Regla de ancho
  completo persistida en `MEMORY.md` (faltaba de una sesión previa).
- (2026-11-03) F1 en marcha: fotos viejas borradas (commit `f3b7de1f`).
  **Hallazgo:** las 79 imágenes del usuario son wallpapers abstractos
  (fractales, paisajes, sci-fi), no fotos de producto — se usan igual
  como galería del catálogo por decisión del usuario. Asignación
  round-robin a 14 slots producto×finish (roles front/back/body/beauty/
  side/backbody) → `incoming/` → `ingest-product-images.sh`: 79/79
  convertidas, 316 WebP (commit `cb4d73bd`). Fuentes (136MB) fuera
  del git.
- **Hallazgo importante:** `product_images` estaba VACÍO — la ingesta
  previa nunca corrió `assets:seed-catalog` (solo convirtió WebP). Y
  NO hay que correrlo para este catálogo: su esquema de SKU (`PAND-…`)
  duplicaría las 27 variantes de `PandaKitsuneCatalogSeeder`
  (`PANDA-ESTAMPADOS-…`) y zeraría precios. Por eso se crea un seeder
  dedicado de imágenes (`assets:seed-images`) que matchea por
  slug/finish y solo toca FTP + `product_images` (writer en curso).
  El PDP filtra galería por `variantId` exacto
  (`products/[slug].astro:690`) → las filas se crean por CADA variante
  de talla del finish.
- **F1 CERRADA** — commits `f3b7de1f` (borrado), `cb4d73bd` (316 webp),
  `c0c37419` (seeder `assets:seed-images`). Corrida real: 316 binarios
  subidos al FTP, 620 filas en `product_images` (27 primaries = una por
  variante, 108 hover = las filas de `back`), 12 productos / 27
  variantes. Verificado en vivo: `GET /api/v1/storefront/products/
  taza-ceramica` devuelve `images` con `isPrimary`/`isHover`/
  `variantId`/`srcset` y el binario responde HTTP 200 (485KB) desde
  `/api/v1/storefront/assets/tenants/...`. Nota operativa: el container
  backend no monta `assets/` — se copia temporalmente a
  `backend/.seed-assets/` y se pasa `--asset-base` (borrar después).
  `--storage-disk ftp` explícito (el default es `local`).
- **F3 CERRADA** — commit `f3d9775e`: header estilo Nike (fila única
  full-bleed, nav limpio, search pill + iconos + `ES | EN` compacto,
  Cotizar pill compacta). Verificado en browser a 1440px.
- **F2 CERRADA (prioridad producto)** — commit `4b7430d5`: PLP/PDP/
  cart/checkout y grillas del home a todo el ancho (PLP hasta 5
  columnas), + proxy dev de `/api` en astro.config (las URLs de
  imágenes son relativas y el dev server no las proxyea;
  `changeOrigin: false` porque el middleware de tenant resuelve por
  host). Verificado en browser: PLP y PDP muestran las imágenes reales
  del catálogo (galería del PDP con las 6 fotos por variante, swatches
  de acabado, precios reales). Lint == baseline (284/64), typecheck 0
  errors, 465/465 tests.
- Nota de datos: los 2 `Sample product (Cliente)` tienen órdenes que
  los referencian (FK `order_items`) — no se borran; se les quitó la
  fila de `product_stores` (invisibles en el storefront, preserva la
  historia de órdenes).

## Progreso — cerrado

Los 3 frentes del plan están implementados y verificados. Commits en
`feat/product-views-and-header`: `f3b7de1f`, `cb4d73bd`, `c0c37419`,
`4b7430d5`, `f3d9775e` (+ docs). Pendientes para la próxima sesión:
home con destacados desde la API (verificación), E2 (UI admin de
galería), E1.6 (idempotencia de re-subida), y decidir merge/push.

## Cierre de la segunda tanda (mismo día)

- **Home con destacados desde la API** — commit `1701986f`: se extrae
  el adapter backend→`Product` a `lib/backend-product-mapper.ts` (antes
  local del PLP) y el home fetchea `?per_page=8` con fallback a mocks.
  Verificado en browser ("Destacados de la semana" con fotos reales).
- **E2 galería admin** — commit `a094c63f`: `ProductGalleryEditor`
  (upload multipart, toggle primary/hover radio, eliminar, drag&drop →
  reorder) sobre la lógica pura de `admin-product-gallery.ts` (15
  tests TDD). `MediaUploader` (mock) eliminado. Suite 484 passed.
  **No verificado en browser**: la isla admin pide sesión; queda como
  smoke manual.
- **Merge a `master` + push** — pedido explícito del usuario.

## Próximo paso

Responder la pregunta del drop de imágenes (T1) y arrancar F1 → F2 →
F3 en ese orden (el header puede ir en paralelo a F2 si conviene).
