# Vistas principales del cliente — brief por sesión

> **Modelo de trabajo (decisión del usuario, 2026-11-05)**: cada sesión
> trabaja **UNA vista, una sola responsabilidad**. Se abren 3-5 sesiones
> en paralelo; primero se definen las tareas (este doc), después el
> usuario entrega los detalles a cambiar por vista.
>
> **Fuente visual**: mockups IA del plan
> [`atlascommerce-views-plan.md`](atlascommerce-views-plan.md) (10 vistas,
> 1440×900 desktop + multi-tenant). Lo que está acá **reemplaza** el
> modelo de ejecución de ese plan (PRs por vista en 3 sprints) por el
> modelo sesión-por-vista.
>
> **Estado**: definido. Detalles del usuario: sesión 3 **recibidos**
> (2026-11-05); resto pendientes (sección `Detalles del usuario` al
> final de cada sesión).

## Antes de editar cualquier archivo (todas las sesiones)

1. `AGENTS.md` raíz → `.ai/rules/index.md` → reglas del scope → `frontend/AGENTS.md`.
2. Frontend usa **`bun`** (`~/.bun/bin/bun`, NO está en PATH). Nunca npm/pnpm.
3. Containers solo por `scripts/docker-stack.sh`. Nunca `docker compose` directo.
4. Migraciones backend (si hicieran falta): `docs/architecture/migration-pattern.md`
   — sin guards `Schema::has*`.

## Protocolo de sesiones en paralelo (importante)

- **Rama**: `feat/views/<vista>` por vista (`feat/views/home`,
  `feat/views/catalogo`, `feat/views/pdp`, `feat/views/compra`,
  `feat/views/cuenta`, `feat/views/b2b`).
- **Si las sesiones corren en paralelo: un worktree propio por sesión**
  (`~/Code/AtlasCommerce-worktrees/<vista>`, nunca `/tmp`) — 3-5 sesiones
  sobre el MISMO worktree se pisan. Si comparten worktree, ejecutar
  **en secuencia**.
- **Archivos compartidos** (tocan varias sesiones — solo aditivo y
  namespaced):
  - `frontend/src/i18n/locales/{es,en}.ts` + `types.ts` → claves propias de
    la vista (`storefront.home.*`, `storefront.catalog.*`,
    `storefront.cart.*`, `storefront.checkout.*`, `storefront.account.*`).
  - `frontend/src/stores/cart.ts` → sesión 4 manda; las demás solo leen.
  - `layouts/StorefrontLayout.astro`, `components/storefront/layout/**` →
    solo sesión 1 (o coordinar).
- **Commits**: convencionales en español, prosa plana, ~400 líneas por PR
  (work-units). **NO** pushear ni mergear sin que el usuario lo pida.

## Cierre común de cada sesión

- `~/.bun/bin/bun run test` · `bun run astro check` · `bun run build`
  (con `PATH="$HOME/.bun/bin:$PATH"` por el script `prebuild`).
- Lint por archivo (`bunx eslint <archivos>`): **0 errores nuevos vs HEAD**
  (el repo tiene baseline enorme — no correr `eslint .` a secas).
- Capturas playwright (firefox): desktop 1440 y mobile 390, Host del
  tenant contra `:5321` (ver `.ai/rules/tooling/dev-domains-and-session.md`).
- Reportar: qué cambió, checks `<comando>: <resultado>`, capturas, y
  qué quedó fuera.

## Fuera de scope de estas sesiones

- **Admin** (`/es/admin/**`) — vistas de gestión interno, otro plan.
- **Backend**: los endpoints que la PDP necesita ya están en master del
  branch (`product_relations`, `recently_viewed_products`). Si una vista
  necesita un endpoint nuevo, reportarlo como hallazgo, no implementarlo
  sin confirmación.

---

## Sesión 1 — HOME / portada

**Responsabilidad única**: la portada del tenant (`/{locale}/`).

**Rutas / archivos en scope**
- `frontend/src/pages/[locale]/index.astro`
- `frontend/src/components/storefront/home/**` (HomeHero, HeroBanner,
  DarkHeroCardB2C, FeaturedCategories, FeaturedProducts, UseCases,
  CustomerStoriesB2C, CtaBanner)
- Layout/header/footer solo si la portada los consume (coordinar).

**Estado actual**: portada Bonfire funcionando con destacados desde API
(`FeaturedProducts`). Plan previo:
[`home-redesign-bonfire-adaptation.md`](home-redesign-bonfire-adaptation.md)
y [`atlascommerce-design-prompt-v3-home.md`](atlascommerce-design-prompt-v3-home.md).

**Tareas**
1. Inventariar secciones actuales vs mockup de portada (multi-tenant:
   Panda teal / Kitsune violeta / …).
2. Aplicar los **detalles del usuario** (ver sección final).
3. Regla full-width: nada de `max-w` estrechos; padding simétrico.
4. i18n es/en (claves `storefront.home.*`), stories de los componentes tocados.
5. Cierre común (checks + capturas 1440/390).

**Aceptación**: portada responsive, 0 errores nuevos, destacados reales
(sin mocks), acentos por tenant correctos.

---

## Sesión 2 — CATÁLOGO (PLP) + BÚSQUEDA

**Responsabilidad única**: discovery — listado de productos, filtros,
orden y búsqueda.

**Rutas / archivos en scope**
- `frontend/src/pages/[locale]/products/index.astro`
- `frontend/src/pages/[locale]/search/index.astro`
- `frontend/src/components/storefront/products/`: FiltersSidebar,
  SortDropdown, ProductGrid, ProductCardCatalog, ProductCard, GridItem
- `frontend/src/lib/catalog-filters.ts` (+ `pagination`, tipos de card)

**Estado actual**: PLP con filtros **SSR por query params** (links, no
islas: `FiltersSidebar` con `aria-current`, `SortDropdown` por `data-href`)
— patrón decidido y testeado; **no romperlo**. Cards reales desde API.
Convención: `workflow/decisions-storefront-rendering.md`.

**Tareas**
1. Inventariar vs mockup 04 (Catálogo): grilla, sidebar de filtros,
   chips activos, sort, paginación, conteo de resultados.
2. Aplicar **detalles del usuario**.
3. Estados: vacío (sin resultados), cargando, error de API, categoría
   inexistente.
4. Búsqueda (`/search`): query, resultados, sin resultados.
5. Responsive (sidebar → drawer en móvil), i18n `storefront.catalog.*`.
6. Cierre común.

**Aceptación**: filtros funcionan sin JS, deep-links por URL, 0 errores
nuevos, cards sin mocks.

---

## Sesión 3 — DETALLE DE PRODUCTO (PDP)

**Responsabilidad única**: la ficha de producto y lo que el usuario
quiera cambiar en ella.

**Rutas / archivos en scope**
- `frontend/src/pages/[locale]/products/[slug].astro` (+ `[slug]/reviews.astro`)
- `frontend/src/components/storefront/products/**` (galería, barra de
  compra, tabs, rieles de recomendación)
- `frontend/src/lib/{product-detail,product-specs,pdp-tabs,purchase-quantity,product-relations,guest-token}.ts`
- `frontend/src/lib/server/product-recommendations.ts`

**Estado actual (cerrado el 2026-11-05, branch
`feat/views/pdp-purchase-recommendations`, 6 commits)**
- `e577a97b` — layout full-width: galería `aspect-square` (sin pillarbox),
  grid `1.05fr/0.95fr`, PandaProductDetail sin `max-w-6xl`.
- `bcea346d` — barra de compra: cantidad 1..stock de la variante,
  "Añadir al carrito" + "Comprar ya" → `/{locale}/checkout`.
- `2d38cecd` — tabs accesibles Descripción | Ficha técnica | Reseñas (N) |
  Preguntas (N) | Envíos (atributos EAV + tallas enchufados).
- `0ff4b199` + `69e60903` + `db6b7ffb` — rieles en BD: Frecuentemente
  Comprados Juntos (con "Agregar todo"), También Te Puede Gustar
  (curados + fallback categoría), Visto recientemente (SSR, cookie
  `rv_guest`, cap 20 permanente).
- 525 tests frontend verdes · backend 25 tests verdes · build OK ·
  capturas en `output/playwright/pdp-*`.

**Plan del lote 2 (2026-11-05 — detalles del usuario, ver sección final)**
Worktree `~/Code/AtlasCommerce-worktrees/pdp` · rama `feat/views/pdp`.
Una work-unit commit por tarea (convencional en español, sin push/merge
sin pedido del usuario). TDD estricto (config global), runner
`bun test` (frontend): RED previo en lógica (swap/preview/quantity);
layout se valida con tests de clases (estilo `catalog-grid-css.test.ts`)
+ capturas. Guía ~400 líneas autoreadas por work-unit.

1. ✅ **Tabs a la sección de datos** — mover `ProductDetailTabs` del
   bloque aparte (`[slug].astro:1348`, bajo el grid) a la sección de
   datos del producto (columna derecha), reemplazando la descripción
   suelta; tab bar compacta. [delegated writer — 2 archivos]
   → `4630a6f7`: tabs al final del `<aside>`, bloque suelto y párrafos
   duplicados eliminados; `pdp-tabs-placement.test.ts` (4 tests,
   RED→GREEN). 529 tests OK · astro check OK · eslint 0 nuevos.
   Assess: medium, `under_budget` (230 líneas; slice pendiente).
   Nota abierta: el `<aside>` `lg:sticky` queda alto con los paneles
   adentro — revisar reachabilidad del sticky en work-unit de layout.
2. ✅ **Talla + Cantidad** — `ProductSizeGrid.astro` (botones grandes en
   grilla) → pills compactas inline; `<select>` nativo de
   `ProductPurchaseBar.tsx` → stepper − / + (semántica de
   `purchase-quantity.ts`, patrón de `ProductQuantitySelector.astro`).
   Respetar `aria-pressed`, hidden input y `?size=`. [delegated]
   → `beef67b8`: pills `h-9 rounded-full` en flex-wrap (sin grilla) y
   stepper −/+ con `tabular-nums` y clamp 1..stock; `quantityStepperState`
   en `purchase-quantity.ts` + `pdp-purchase-controls.test.ts` (12 tests
   nuevos, RED→GREEN). 541 tests OK · astro check OK · eslint 0 nuevos.
   Assess: medium, `under_budget` (561 líneas; slice pendiente).
   Desviación resuelta: semántica real de tallas = `role="radio"` /
   `aria-checked` (no `aria-pressed`) — preservada como estaba.
3. ✅ **Cards de rieles parejas** — `ProductRelated.astro` (y FBT /
   RecentlyViewed por consistencia): `h-full` + footer `mt-auto` y media
   `aspect-square` con `object-cover` (hoy `object-contain` deja
   huecos). Sin estirar ni deformar imágenes. [inline si 1 archivo]
   → `c3a2388f`: stretch chain `h-full` en `li`/`article` de los 3
   rieles (patrón `ProductCard.astro:108`), `object-contain` →
   `object-cover` en ProductRelated, `items-stretch` + `self-center`
   del separador `+` en FBT; `pdp-rail-card-heights.test.ts` (11 tests,
   RED→GREEN). 552 tests OK · astro check OK · eslint 0 nuevos.
   Assess: medium, `under_budget` (715 líneas; slice pendiente).
   Nota: `ProductRelated.astro` llegó a 398 líneas (sobre límite) —
   T3.4/T3.5 deben extraer el body de la card a subcomponente.
4. **Acabados: color real + hover cambia imagen** — selector ACABADO
   (`[slug].astro:1115-1190`): swatches hex en vez de thumbnails
   (hoy "parecen imágenes repetidas"); hover/focus del swatch cambia la
   imagen (cards: `setupVariantPreview` existe pero no se percibe —
   fix; galería: preview de la variante). i18n de "Acabado"/"Precio"
   si se tocan esas líneas. [delegated]
5. **Hover imagen → trasera en cards de rieles** — cablear
   `product-card-swap` (`deriveProductCardSwap`, `imageUrlSecondary`,
   CSS `img[data-role='back']` como `ProductCard.astro`) en
   ProductRelated / FBT / RecentlyViewed (hoy no lo usan). [delegated]
6. **Cierre común** — `bun run test`, `astro check`, `build`, lint por
   archivo (0 errores nuevos vs HEAD), capturas 1440/390.

**Diferido (fuera de este lote)**: port del backend Fase 8
(`product_faqs`, `product_extended_descriptions`, `product_highlights`;
PR #169 cerrado por conflicto) — `product_grouped_specs`/
`product_spec_values` requieren decisión de diseño vs atributos EAV.

**Aceptación**: sin regresiones (525 tests siguen verdes), detalles del
usuario aplicados, capturas nuevas.

---

## Sesión 4 — CARRITO + CHECKOUT

**Responsabilidad única**: la compra — carrito y checkout multi-step.

**Rutas / archivos en scope**
- `frontend/src/pages/[locale]/cart.astro`, `checkout.astro`
- `frontend/src/components/storefront/cart/**` (CartView, CartItem,
  CartSummary, CouponInput, TrustSignalsCompact)
- `frontend/src/components/storefront/checkout/**` (CheckoutStepperV3,
  CheckoutFormV3, CheckoutOrderSummary, CheckoutSuccess)
- `frontend/src/stores/cart.ts` (dueña de esta sesión)

**Estado actual**: carrito en `stores/cart.ts` (memoria + localStorage,
`useSyncExternalStore`) y checkout 3 pasos (Envío/Pago/Revisión) estilo
Bonfire con `?step=`. Totales **placeholder** (tax 15%, envío $4 / gratis
> $50). "Comprar ya" del PDP ya aterriza en `/{locale}/checkout`.
Mockups 08 (carrito) y 09 (checkout).

**Tareas**
1. Inventariar vs mockups 08/09 y aplicar **detalles del usuario**.
2. Carrito: líneas (variante + talla), qty con stock, eliminar, cupón,
   totales, estado vacío → CTA a catálogo.
3. Checkout: stepper, validación de formularios, resumen pegajoso,
   estados de error del backend (`CheckoutController`), pantalla de éxito.
4. Coherencia de moneda/locale por tenant (`formatCurrency`).
5. Responsive, i18n `storefront.cart.*` / `storefront.checkout.*`.
6. Cierre común.

**Aceptación**: flujo completo carrito → checkout → éxito, sin JS roto
en totales, 0 errores nuevos.

---

## Sesión 5 — CUENTA + AUTH

**Responsabilidad única**: el post-login del cliente (auth, pedidos,
perfil, seguridad).

**Rutas / archivos en scope**
- `frontend/src/pages/[locale]/account/**`: index (dashboard), orders
  (`index`, `[id]`), login, signup, 2fa, forgot/reset-password,
  reset-success, security, notifications, addresses, wishlist, gdpr
- `frontend/src/components/storefront/auth/**` y `account/**` (los que
  existan)

**Estado actual**: flujo auth funcionando con **2FA TOTP**. Contrato
completo en `.ai/rules/workflow/browser-auth-contract.md` (challenge →
`/es/account/2fa?token=`, verify público `verify-totp-login`). Credenciales
dev: `admin@panda-estampados.test` / `password` (con TOTP; recipe en la
regla). Mockups 03 (account dashboard) y 10 (mis órdenes).

**Tareas**
1. Inventariar vs mockups 03/10 y aplicar **detalles del usuario**.
2. Dashboard: resumen de pedidos, accesos rápidos, datos de cuenta.
3. Mis órdenes: listado + detalle (`orders/[id]`), estados de pedido.
4. Auth: login/signup/2fa/reset — respetar el contrato de la regla
   (no renombrar endpoints ni el flujo del challenge).
5. Seguridad/notificaciones/direcciones/wishlist/gdpr: lo que el usuario
   decida incluir (algunos pueden quedar fuera).
6. Cierre común.

**Aceptación**: login → dashboard → pedido navega completo, 2FA intacto,
0 errores nuevos.

---

## Sesión 6 (opcional, solo Panda B2B) — RFQ + BULK ORDER

**Responsabilidad única**: cotizaciones y pedido por volumen (tenant
Panda).

**Rutas / archivos en scope**
- `frontend/src/pages/[locale]/rfq/**` (index, confirmation)
- `frontend/src/pages/[locale]/bulk-order/index.astro`
- componentes `storefront/b2b/**` (los que existan)

**Estado actual**: rutas creadas (mockups 06 y 07). Solo activas para
Panda (features B2B del tenant).

**Tareas**
1. Aplicar **detalles del usuario** sobre RFQ (form + confirmación) y
   bulk-order (grilla de pedido rápido por SKU/cantidad).
2. Validaciones, estados vacío/error, i18n `storefront.rfq.*` /
   `storefront.bulk.*`.
3. Cierre común.

---

## Detalles del usuario (por vista)

> El usuario entregará acá (o por mensaje) los cambios concretos por
> vista. Cada sesión aplica los de SU sección y nada más.

- **Sesión 1 (Home)**: _pendiente_
- **Sesión 2 (Catálogo + Búsqueda)**: _pendiente_
- **Sesión 3 (PDP)**: **recibidos 2026-11-05** (worktree
  `~/Code/AtlasCommerce-worktrees/pdp`, rama `feat/views/pdp`):
  1. Las tabs (Descripción | Ficha técnica | Envíos) van en la **misma
     sección donde están los datos del producto** — hoy están aparte y
     "es horrible". Objetivo general: **"aprovechar cada espacio"**.
  2. Rediseñar el componente de **Tamaño/talla** ("representa su
     información de otra manera") y el de **Cantidad** ("se ve horrible").
  3. En **"También Te Puede Gustar"** una card se ve más alta que otra:
     corregir alturas — "evita estirar o redimensionar la imagen ya que
     eso generaría huecos".
  4. **Acabados**: "parecen imágenes repetidas"; además, al poner el
     cursor sobre el circulito del acabado **no se cambia la imagen**
     (debe cambiar).
  5. "cuando pongo el cursor sobre una imagen de producto debe mostrarse
     ese mismo producto pero **la imagen trasera**" (semántica
     `isHover`/`back` ya existente en catálogo).
- **Sesión 4 (Carrito + Checkout)**: _pendiente_
- **Sesión 5 (Cuenta + Auth)**: _pendiente_
- **Sesión 6 (B2B, opcional)**: _pendiente_
