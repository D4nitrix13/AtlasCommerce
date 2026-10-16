# Plan — Vistas esenciales AtlasCommerce (actualizado con 10 mockups IA)

> **Status**: 10 mockups de vistas recibidos (excluyendo Login/Signup). Pendiente que el usuario comparta el contenido del ZIP + HANDOFF.md para empezar a replicar.
> **Source del plan**: `odd/tasks/atlascommerce-design-prompt-v1.md` (prompt v1) + ZIP `atlascommerce-views.zip` (mockups).
> **Branch de trabajo**: `feat/views/<view-slug>` (uno por vista, merge a master al cerrar).

## Vistas recibidas (10)

Las 10 vistas generadas por la IA externa con el prompt v1. Cada una incluye mockup 1440×900px desktop, lista de componentes sugeridos, y vista multi-tenant (Panda teal / Kitsune violeta / MM pink / Morales azul).

| # | Vista | Ruta | Tenant scope | Prioridad |
|---|---|---|---|---|
| 03 | Account dashboard | `/es/account` | Todos | P1 |
| 04 | Catálogo | `/es/products` | Todos | P1 |
| 05 | Detalle de producto | `/es/products/[slug]` | Todos | P2 |
| 06 | RFQ (cotización) | `/es/rfq` | **Solo B2B Panda** | P1 |
| 07 | Bulk order | `/es/bulk-order` | **Solo B2B Panda** | P2 |
| 08 | Carrito | `/es/cart` | Todos | P2 |
| 09 | Checkout multi-step | `/es/checkout` | Todos | P2 |
| 10 | Mis órdenes | `/es/account/orders` | Todos | P2 |
| 11 | Admin dashboard | `/es/admin` | B2B + admin | P3 |
| 12 | Admin orders | `/es/admin/sales/orders` | B2B + admin | P3 |

**Pendiente de mockup** (no incluidos en el ZIP): Login (01) y Signup (02). La IA sugirió rehacerlas en lenguaje Bonfire como sprint aparte para cerrar el sistema.

## Orden de ejecución

10 vistas organizadas en 3 sprints. Cada vista = 1 PR con 2-5 commits work-unit, scope-limited para no superar el budget de 400 líneas por PR.

### Sprint 1 (P1) — Auth + dashboard + RFQ + Catálogo

| PR | Branch | Vista | Commits estimados | LOC estimado |
|---|---|---|---|---|
| 1 | `feat/views/account-dashboard` | Account dashboard | 3-5 | 300-500 |
| 2 | `feat/views/products-list` | Catálogo | 3-4 | 300-500 |
| 3 | `feat/views/rfq-b2b` | RFQ B2B Panda | 3-4 | 250-450 |

**Por qué este orden**: dashboard es la primera impresión post-login (alta frecuencia), catálogo es el entry point de discovery (tráfico alto), RFQ es la conversión B2B core.

### Sprint 2 (P2) — Detalle producto + Carrito + Checkout + Órdenes

| PR | Branch | Vista | Commits estimados | LOC estimado |
|---|---|---|---|---|
| 4 | `feat/views/product-detail` | Detalle de producto | 3-5 | 350-500 |
| 5 | `feat/views/cart` | Carrito | 2-3 | 200-350 |
| 6 | `feat/views/checkout` | Checkout multi-step | 3-5 | 350-500 |
| 7 | `feat/views/my-orders` | Mis órdenes | 2-3 | 200-350 |

**Por qué este orden**: el detalle de producto es la conversión de catálogo. Carrito + checkout son la conversión final. Órdenes cierra el loop post-compra.

### Sprint 3 (P3) — Admin + Bulk order B2B

| PR | Branch | Vista | Commits estimados | LOC estimado |
|---|---|---|---|---|
| 8 | `feat/views/bulk-order-b2b` | Bulk order B2B Panda | 2-3 | 200-300 |
| 9 | `feat/views/admin-dashboard` | Admin dashboard | 4-6 | 400-600 |
| 10 | `feat/views/admin-orders` | Admin orders | 4-6 | 400-600 |

**Por qué este orden**: bulk order es menos frecuente que admin dashboard. Admin dashboard y orders son las vistas de management que necesita el equipo interno.

### Sprint 4 (futuro) — Login + Signup en lenguaje Bonfire

Pendiente hasta que la IA rehaga los mockups o hasta que se decida en otro sprint.

| PR | Branch | Vista |
|---|---|---|
| 11 | `feat/views/login-bonfire` | Login |
| 12 | `feat/views/signup-bonfire` | Signup |

## Antes de empezar a ejecutar

El usuario aún no me ha confirmado si yo tengo acceso al ZIP o solo a la descripción textual de la respuesta de la IA. Antes de empezar a codificar la primera vista:

1. **HANDOFF.md**: la IA mencionó que el ZIP incluye un HANDOFF.md con tokens nuevos (`--cream-*`, `--slate2-*`, `--primary-soft/ring/hover`) y la advertencia de no usar la utilidad `ring` de Tailwind para el foco. Necesito leerlo antes de empezar.
2. **Mockup de Account dashboard**: el primero a implementar. Necesito ver el screenshot real o, en su defecto, las descripciones de layout exacto.
3. **Verificación del bug de product_mockup**: el ProductMockup.astro que tengo en el repo (creado en sprint Bonfire visual fidelity) tiene definiciones SVG. Necesito verificar que las paths de las 4 variantes (shirt/hoodie/mug/hat) están correctas antes de usar en Account dashboard o cualquier otra vista.
4. **Token de TenantSlugs**: para mantener el switcher multi-tenant, las páginas deben respetar `Astro.locals.tenant` y renderizar condicionalmente según `tenantSlug`. Account dashboard es la primera vista que necesito verificar este patrón.

## Estructura típica de cada commit (work-unit pattern)

Para cada vista, los commits siguen este orden:
1. **feat: primitives nuevos** — si la vista necesita componentes Astro nuevos (cards, tables, forms), se crean aquí. Incluyen sus stories de Storybook.
2. **feat: i18n keys** — todas las strings de la vista se agregan a `frontend/src/i18n/locales/{es,en}.ts` + `types.ts`.
3. **feat: composition** — la página `[locale]/...` se actualiza para componer los primitives con data mock del tenant.
4. **feat: polish** — focus rings, hover states, scroll-triggered animations si aplica.

Cada commit verificado con: `bun run astro build && bun run astro check && bun run test && bun run storybook:build`.

## Convenciones que voy a aplicar (per frontend/AGENTS.md)

- Componentes ≤ 250 LOC (si excede, extraigo sub-componente)
- PascalCase filenames, camelCase vars, UPPER_SNAKE_CASE constants
- Props con `interface Props`, TypeScript strict
- Tailwind class order: layout → position → size → spacing → borders → bg → text → effects → responsive
- HTML semántico: `<section>`, `<header>`, `<h2>`, `<details>/<summary>` para accordions
- Decorativos con `aria-hidden="true"` y `pointer-events-none`
- Storybook stories obligatorias para cada componente visual
- Conventional commits en español, prosa plana
- No emojis en código
- No usar la utilidad `ring` de Tailwind para focus (la IA lo advirtió); usar `focus-visible:ring-2` con clase explícita

## Riesgos identificados

1. **Multi-tenant switcher**: el mockup de la IA muestra un switcher vertical a la derecha. Si lo replico, tengo que decidir si es un componente `<TenantThemeSwitcher>` o un dev tool que NO va al build de producción. **Decisión**: omitirlo del build de producción. Solo visible en dev mode. O no incluirlo y que el usuario cambie de tenant via DNS.

2. **AI generativa sin repo**: la IA asumió valores aproximados. Cada decisión que tomó la IA (radius, spacing, breakpoints) hay que verificarla contra los componentes existentes.

3. **Bug de product_mockup**: la IA lo encontró y "lo arregló en su código". Mi repo tiene su propio `ProductMockup.astro`. Necesito verificar que las paths SVG son correctas en MI versión antes de empezar a usarlo en Account dashboard.

4. **Tenant-aware data**: cada vista debería usar data mockeada del tenant actual. Account dashboard en Panda muestra métricas B2B; en Kitsune métricas B2C. Esto requiere i18n con keys multi-tenant o ramas condicionales en index.astro.

5. **400 LOC budget por PR**: cada PR de vista tiene que caber en 400 LOC netas. Si excede, se splitea en 2 PRs (mismo sprint, branches secuenciales).

## Tracking

- Cada PR con title `feat(frontend): <view-name> visual fidelity Bonfire`
- Conventional commits en español, prosa plana
- Cada PR con check: `astro build`, `astro check`, `tests`, `storybook:build` verdes
- Cada PR con Storybook stories para componentes nuevos
- Cada PR con i18n keys completas es + en + TranslationKey union
- Cada PR con tests actualizados para nuevas props/componentes

## Resumen de sprints

| Sprint | Vistas | PRs | LOC total estimado |
|---|---|---|---|
| Sprint 1 (P1) | Dashboard, Catálogo, RFQ | 3 | 850-1450 |
| Sprint 2 (P2) | Detalle, Carrito, Checkout, Mis órdenes | 4 | 1100-1700 |
| Sprint 3 (P3) | Bulk order, Admin dashboard, Admin orders | 3 | 1000-1500 |
| Sprint 4 (futuro) | Login, Signup Bonfire-style | 2 | 400-800 |
| **Total** | **12 vistas** | **12 PRs** | **3350-5450 LOC** |
