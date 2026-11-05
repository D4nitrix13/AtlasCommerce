# Carrito — rediseño visual (sesión 4, vista compra)

> Feature doc ODD. Espejo Engram: **pendiente** (Engram no disponible en
> esta sesión; resincronizar con `odd/cart-view-redesign/tasks` cuando
> vuelva).

## Objetivo

Rediseñar la vista de carrito (`/{locale}/cart`) para que la información
se entienda de un vistazo y la UI se vea cuidada, interactiva y fácil —
sin romper el comportamiento existente del store.

## Problema

El carrito actual (`CartView.tsx`) es plano: jerarquía tipográfica
débil, stepper de cantidad crudo, resumen de totales genérico, espacio
muerto, trust signals sin pulir y nula interacción visual. El usuario lo
describe como "horrible".

## Por qué

El carrito es el paso previo a la conversión; una UI confusa o fea
frena la compra. La sesión 4 es la dueña de `stores/cart.ts` y de esta
vista.

## Scope autorizado

- `frontend/src/pages/[locale]/cart.astro`
- `frontend/src/components/storefront/cart/**`
- `frontend/src/stores/cart.ts` (solo lectura de contrato; cambios solo
  si el diseño lo requiere, sin romper `localStorage['cart.lines']`)
- `frontend/src/i18n/locales/{es,en}.ts` + `types.ts` — claves
  `storefront.cart.*` (aditivo)
- CSS global solo si hace falta una utilidad (`styles/global.css`)

## Restricciones

- Multi-tenant: tokens `--primary/--primary-hover/--primary-soft/
  --paper/--ink/--line` (shop-canvas). Fallback `#475569` para Storybook.
- Full-width, sin `max-w` estrechos; padding simétrico.
- Contratos a preservar: props `CartViewLabels` + slot `children`
  (TrustSignals), `useCart` SSR-empty (primera pintada puede ser vacía),
  keys `productSlug:variantLabel`, `data-reveal-stagger`, hooks
  `data-cart-*` de CartItem.astro si se conserva.
- Límites de archivo (frontend/AGENTS.md): componente simple 150 /
  mediano 250 / página 400 — `CartView.tsx` (292 ln) debe dividirse.
- Frontend usa `bun` (`~/.bun/bin/bun`). Containers por
  `scripts/docker-stack.sh`.
- TDD estricto (config de sesión): RED → GREEN → REFACTOR en cambios de
  comportamiento; refactor visual mantiene la suite verde. Runner:
  `~/.bun/bin/bun run test`.
- NO pushear ni mergear sin pedido del usuario.

## Tareas

- [x] **C1** Setup: worktree `~/Code/AtlasCommerce-worktrees/compra` +
  branch `feat/views/compra`. _Ruta: inline (infra)._ Hecho: worktree
  creado sobre `8e259118`.
- [x] **C2** `CartLineRow` rediseñado: fila táctil (imagen con outline
  inset, nombre, chip de variante, stepper pill 1..stock, total de línea
  tabular, quitar con hover/focus). _Ruta: delegated writer._ Hecho:
  `CartLineRow.tsx` (126 ln) — imagen 88px con inset, chip variante,
  stepper pill 1..99 deshabilitado en bounds, total tabular, quitar
  icon-only con hover rojo. Tests RED→GREEN:
  `tests/unit/components/storefront/cart/CartLineRow.test.tsx` (6 tests,
  stepper clamp 1/max). `bun run test`: 540/540 verde.
- [x] **C3** Resumen tipo recibo: filas subtotal/descuento/envío/
  impuestos, divisor punteado, total display (Fraunces), medidor de
  envío gratis (faltan $X, barra en acento), CTA con press feedback.
  _Ruta: delegated writer._ Hecho: `CartSummaryBlock.tsx` (133 ln) +
  `FreeShippingMeter.tsx` (84 ln). Medidor deriva el umbral de
  `FREE_SHIPPING_THRESHOLD_CENTS` (stores/cart.ts). Tests RED→GREEN:
  `FreeShippingMeter.test.tsx` (6 tests: just-under/at/over 5000).
- [x] **C4** Composición `CartView` + estado vacío + micro-movimiento
  (stagger existente, press scale, `tabular-nums`) + split de archivos
  a límites. _Ruta: delegated writer (mismo que C2-C3)._ Hecho:
  `CartView.tsx` reescrito (292→177 ln) con empty state (círculo
  accent-soft + bag SVG + hint + CTA) y split en CartLineRow /
  CartSummaryBlock / FreeShippingMeter. `data-reveal-stagger` conservado,
  `active:scale-[0.97]` + `motion-reduce`, `transition-[width]` 300ms
  cubic-bezier(0.23,1,0.32,1) en barra. Límites: simple ≤150 / mediano
  ≤250 OK.
- [x] **C5** Responsive: stack mobile + barra sticky inferior (total +
  CTA) cuando hay items. _Ruta: delegated writer._ Hecho:
  `CartMobileBar` en CartView.tsx — `fixed bottom-0 z-40 lg:hidden`,
  bg `--paper/95` + backdrop-blur, total tabular + CTA compacto;
  `pb-28 lg:pb-12` en el main de cart.astro. Grid
  `lg:grid-cols-[minmax(0,1fr)_360px]` stackea en mobile.
- [x] **C6** i18n es/en: claves nuevas `storefront.cart.*` (medidor
  envío gratis, trust signals i18n — hoy hardcodeados en cart.astro).
  _Ruta: delegated writer._ Hecho: 9 keys nuevas en types.ts/es.ts/en.ts
  (`v3.freeShippingProgress` {amount}, `v3.freeShippingReached`,
  `v3.steps.bag/checkout/done`, `v3.trust.shipping/guarantee/returns/
  secure`); trust labels de cart.astro movidos a i18n; `emptyHint`
  reusa `storefront.cart.emptyHint`. Guard:
  `tests/unit/i18n/cart-v3.test.ts` RED→GREEN.
- [x] **C7** Tests + stories — tests de comportamiento en RED→GREEN:
  `CartLineRow.test.tsx` (clamp 1..99), `FreeShippingMeter.test.tsx`
  (fronteras 5000¢), `cart-v3.test.ts` (i18n). Stories: **N/A por
  convención** — el repo solo tiene stories para componentes Astro
  (`@storybook/astro`); los componentes nuevos son islas React y las
  islas React existentes (AddToCart, CouponInput, CartView viejo) nunca
  tuvieron stories. `CartItem.stories.ts`/`CartSummary.stories.ts`
  (gemelas Astro sin uso) intactos. _Ruta: delegated writer (tests)._
- [x] **C8** Cierre común — observado 2026-01-05:
  `bun run test`: 55 files / 540 tests passed · `bun run astro check`:
  0 errors / 0 warnings · `bun run build`: OK · `bunx eslint <13
  archivos>`: 0 errores nuevos · capturas playwright firefox en
  `output/playwright/cart-redesign-{empty,filled,freeship}-1440.png`
  + `cart-redesign-filled-390.png` (revisadas: jerarquía, medidor en
  ambos estados, totales correctos, barra sticky mobile).
  _Ruta: per-action workers._

## Criterios de aceptación

1. Flujo completo carrito → CTA checkout navega a `/{locale}/checkout`.
2. Totales sin JS roto (subtotal/envío/impuestos/total coinciden con
   `getCartTotals`; envío gratis ≥ $50).
3. Stepper respeta 1..99 (o stock si aplica) con `aria-label`.
4. Acento por tenant correcto (Panda teal / Kitsune violeta).
5. 0 errores nuevos de lint/tests vs HEAD; tests existentes en verde.
6. Capturas 1440 y 390 sin overlap ni texto ilegible.

## Presupuesto / delivery

- Forecast autoral: ~500-700 líneas (C2-C7) → supera ~400. Estrategia
  `ask-on-risk`: preguntar estrategia de cadena (stacked vs single-pr)
  antes del commit si el running count confirma el exceso.

## Checks aplicables

`~/.bun/bin/bun run test` · `bun run astro check` · `bun run build` ·
`bunx eslint <archivos>` (0 errores nuevos) · playwright firefox
1440/390 contra `:5321`.

## Progreso / evidencia

- C1: worktree `~/Code/AtlasCommerce-worktrees/compra`, branch
  `feat/views/compra`, base `8e259118`.
- C2–C6 (sesión rediseño, delegated writer): componentes nuevos
  `CartLineRow.tsx` (126), `CartSummaryBlock.tsx` (133),
  `FreeShippingMeter.tsx` (84); `CartView.tsx` reescrito (177);
  `cart.astro` (100, step indicator + labels i18n);
  `TrustSignalsCompact.astro` → chips con tokens. Tests nuevos RED→GREEN:
  FreeShippingMeter (6), CartLineRow (6), i18n cart-v3 (3).
- Verificación (desde `frontend/`, `PATH=$HOME/.bun/bin:$PATH`):
  - `bun run test` → 55 archivos / 540 tests, todo verde.
  - `bun run astro check` → 0 errores, 0 warnings, 41 hints.
  - `bun run build` → Complete! (server built OK).
  - `bunx eslint <13 archivos tocados>` → 0 errores, 6 warnings
    `max-lines-per-function` (clase presente en baseline del repo).
- Contratos preservados: props de CartView (extensión aditiva de
  labels), SSR-empty tolerado, keys `productSlug:variantLabel`,
  `data-reveal-stagger`, totales vía `getCartTotals`, umbral del medidor
  = `FREE_SHIPPING_THRESHOLD_CENTS`. CSS verificado en build: los
  tokens con opacidad (`--ink/60`, `--line/60`, `--paper/40`) compilan
  con `color-mix`.

## Próximo paso

C7 y C8 observados (ver checkboxes; capturas en
`output/playwright/cart-redesign-*.png`, revisadas visualmente).
Pendiente: commit work-unit en `feat/views/compra` — running count
~800 líneas autoralas supera la guía ~400 → decisión de entrega
(preguntada al usuario).
