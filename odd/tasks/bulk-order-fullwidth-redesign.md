# Feature: bulk-order-fullwidth-redesign

> Estado: ✅ 6/6 — rediseño completo, verificado y entregado

## Objective

Redesign `/es/bulk-order` (Panda B2B volume pricing) to use the full
page width and apply `.interface-design/system.md` tokens consistently.
The current page is cramped at `max-w-6xl` and uses hardcoded slate
colors instead of the committed design system.

## Problem

1. **Ancho**: `main.max-w-6xl.mx-auto` (1152px) deja la página angosta
   en pantallas grandes. `system.md` dice textual: *"Full-width, sin
   `max-w` estrechos, padding simétrico"*. Las vistas ya rediseñadas
   (cart, PDP) usan `w-full px-4 sm:px-6 lg:px-10`.
2. **Tokens**: `slate-*`, `bg-white` plano, `border-slate-200`,
   `emerald-700` hardcodeados en vez de `--ink` / `--ink-muted` /
   `--line` / `--primary` / `--lift-1`.
3. **CTA**: `rounded-full h-11` sin `active:scale` ni
   `focus-visible:ring` — el patrón del sistema es `h-12 rounded-lg`
   con press 0.97 y ring de acento.
4. **Composición rota**: el grid `lg:grid-cols-[minmax(0,1fr)_minmax(0,420px)]`
   tiene **un solo hijo** — la segunda columna queda vacía. El docblock
   prometía "Aside con CTA a /rfq" como composición #4 pero el aside
   quedó fuera del grid.
5. **Ahorro en verde** (`emerald-700`): `system.md` — "Gratis/éxito usa
   el acento, no verde".
6. **Sin ritmo de recibo**: no hay divisor punteado `border-dashed`
   antes del total ni total display Fraunces 32px.
7. **Gap inconsistente**: `lg:gap-3` (12px) en el comparator vs base
   4px/16px del sistema.

## Why

El usuario pidió explícitamente *"mejora su diseño esta horrible"*
y *"ocupa todo el ancho ya que siempre haces las cosas muy angosta"*.
El diseño comprometido ya existe en `.interface-design/system.md`; la
vista no lo aplica. Es convergencia, no exploración.

## Scope

- `frontend/src/pages/[locale]/bulk-order/index.astro` — shell full-width.
- `frontend/src/components/storefront/b2b/TierComparator.astro` — tokens + full-width.
- `frontend/src/components/storefront/b2b/PricingCalculator.astro` — tokens + panel-recibo.

**Fuera de alcance**: i18n (las keys `storefront.b2b.bulk.v3.*` ya
existen — cero solapamiento con la sesión RFQ paralela), backend,
`BulkPricingCalculator.tsx` (island legacy no montada por esta página),
`VolumePricingTable.astro` (no usado por esta página).

## Constraints

- Multi-sesión: worktree propio `~/Code/AtlasCommerce-worktrees/bulk-order`,
  rama `feat/views/bulk-order` desde `origin/master`. NO tocar el checkout
  principal (la sesión RFQ está editando `i18n/*` y `rfq/index.astro` ahí).
- Verificación visual por SSR standalone del worktree contra el backend
  dev (receta `MEMORY-dev-env-ops-traps`): NO re-apuntar el bind mount
  de `:5321` (estado compartido, requiere autorización de todas las
  sesiones).
- Frontend usa `bun` exclusivamente. Comandos pesados uno por bloque.
- Tokens de `shop-canvas` con fallbacks (`var(--primary,#2d9d8e)`) para
  Storybook.

## Authorized scope

Solo los 3 archivos del scope. Sin cambios de i18n, sin archivos
nuevos de componentes (la composición se resuelve con los componentes
existentes), sin dependencias nuevas.

## Tasks

- [x] **T1** Worktree `feat/views/bulk-order` + codegraph init + `bun install`
- [x] **T2** `index.astro`: shell full-width (`w-full px-4 sm:px-6 lg:px-10`),
      hero `shop-hero`, tipografía de sistema, grid real de 2 columnas con
      aside RFQ, cierre con banda de ayuda.
- [x] **T3** `TierComparator.astro`: tokens `--line`/`--ink`/`--lift-1`,
      gap 16px, card popular como foco (`--lift-2` + `border-2` acento),
      precios `tabular-nums`, CTA `rounded-lg` con press/ring.
- [x] **T4** `PricingCalculator.astro`: panel con `--lift-1`, controles con
      tokens, resumen tipo recibo (filas muted/tabular → divisor punteado →
      total display Fraunces 32px → CTA `h-12 rounded-lg`), ahorro en acento.
- [x] **T5** Verificación visual 1440px + 390px (SSR standalone worktree).
- [x] **T6** `bun run typecheck` + `lint` + format, work-unit commit,
      push + PR + merge según `.ai/rules/workflow/multi-session-isolation.md` §3.

## Acceptance criteria

1. La página ocupa el ancho disponible: sin `max-w` en el `main`; padding
   simétrico `px-4 sm:px-6 lg:px-10` igual que cart/PDP.
2. Cero clases `slate-*` / `emerald-*` en los 3 archivos — todo pasa por
   tokens `--ink*` / `--line` / `--primary*` / `--lift*`.
3. Los 3 CTAs siguen el patrón `rounded-lg` + `active:scale-[0.97]` +
   `focus-visible:ring-2` con acento.
4. El grid de calculadora + aside tiene 2 columnas ocupadas (sin hueco).
5. El total de la calculadora es display Fraunces 32px `tabular-nums` con
   divisor punteado arriba.
6. Jerarquía legible al squint test: el precio por unidad del tier popular
   es el foco de la vista; lo demás se demota con peso/opacidad.
7. `bun run typecheck` y `bun run lint` sin errores nuevos.

## Applicable checks

- `bun run typecheck` (astro check, TS strict)
- `bun run lint` (eslint)
- `bunx prettier --check` sobre los archivos tocados
- Verificación visual desktop 1440 + mobile 390 con Playwright
- Squint test / swap test / token test del skill interface-design

## Progress / verification evidence

- **T2-T4** (escritura delegada, actor `general-1`): 3 archivos
  reescritos con tokens del sistema. `bunx prettier --check` clean;
  `bun run typecheck` 0 errors / 0 warnings (40 hints preexistentes);
  `bun run lint` scoped a los 3 archivos: 0 errores (1 warning
  `max-lines-per-function` en `tiers.map`, deuda preexistente verificada
  con `git stash` baseline).
- **T5** (verificación visual, SSR standalone del worktree en :4399
  contra backend dev `:9000`, sin tocar el bind mount de :5321):
  - `main` = 1440.0px a viewport 1440, `max-width: none`, padding
    simétrico 40px/40px → full-width confirmado (la queja #1 del usuario).
  - Auditoría de paleta en el DOM: `slate-*` = 0, `emerald-*` = 0.
  - `border-dashed` × 1 (divisor punteado del recibo), `tabular-nums` × 13,
    `font-display` × 1 (total del recibo).
  - CTAs primarios: 3 × `border-radius 8px` (rounded-lg), heights 40/48/48,
    `transition-property: transform` (no `all`) → patrón del sistema.
  - Foco: tier 100+ con precio 36px/700 vs 30px/600 del resto + borde
    teal 2px + badge + lift → jerarquía por peso/escala/color/elevación.
  - **Fix aplicado tras medir**: a 1024px las 5 cards con `lg:grid-cols-5`
    desbordaban (precio popular 48px necesitaba 229px en una caja de 124px).
    Corrección: `lg:grid-cols-3 xl:grid-cols-5` + precio popular
    `text-3xl sm:text-4xl font-bold` y base `text-2xl sm:text-3xl`.
    Re-medido: 1024px → 0 overflow (cards 302px, 3 col);
    1280px → 0 overflow (5 col × 227px); 1440px → main 1440px.
  - Capturas finales: `output/playwright/bulk-order-final-desktop.png`
    (1440×1829) y `bulk-order-final-mobile.png` (390×3871). Antes:
    `bulk-order-before-desktop.png` / `-before-mobile.png`.
- **T6** prettier + typecheck + lint ok; work-unit commit Conventional
  Commit; push + PR + merge a master según
  `.ai/rules/workflow/multi-session-isolation.md` §3.

## Next step

T2 — reescribir el shell de `index.astro`.
