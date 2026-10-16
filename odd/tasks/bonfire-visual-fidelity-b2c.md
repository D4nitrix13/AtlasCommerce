# Plan: Bonfire visual fidelity para B2C (Kitsune/MM/Morales)

> **Status**: en ejecución. Branch `feat/b2c-visual-fidelity` desde master (a9f43a3).
> **Source**: replica la mecánica del sprint Panda visual fidelity (PR #151), adaptado al B2C. Mismo design system Bonfire (extraído y preservado en `docs/frontend/design-references-bonfire.md`).
> **Tenant accent**: Kitsune `#7C3AED` (violet). MM-Boutique y Morales Store también usan violet en su composición actual (la paleta del frontend).
> **Goal**: enriquecer visualmente las 5 secciones existentes de la rama B2C (`index.astro` `!isPandaB2B`) + agregar las secciones que Bonfire tiene y faltan en B2C (CustomerStories, DarkHeroCard adaptado, CtaFinal, FaqAccordion B2C).

## 1. Lo que ya existe (no rehacer)

Las primitives decorativas del sprint Panda ya están en `components/ui/`:
- `Squiggle.astro`, `Blob.astro`, `ProductMockup.astro`, `AvatarCircle.astro`
- Reutilizarlas importándolas. NO duplicar.

La rama B2C actual tiene 5 secciones:
- `HomeHero` (con trustBadges del sprint B2C previo)
- `FeaturedCategories` con `CATEGORIES` (B2C)
- `FeaturedProducts` con `getFeaturedProducts(4)`
- `UseCases` con per-tenant use cases
- `CtaBanner` de cierre

i18n keys shared B2C (`storefront.home.*`) y per-tenant (`storefront.home.{kitsune,mm,morales}.useCase.*`).

## 2. Lo que falta para fidelidad Bonfire en B2C

### 2.1 Componentes NUEVOS para B2C (3)

1. **`CustomerStoriesB2C.astro`** (hermano B2C del `CustomerStories` B2B):
   - Props: `heading`, `subheading`, `stories: readonly Story[]` con quote + customer + métrica destacada.
   - Layout: 3 cards grandes estilo Bonfire testimonials (foto rotada + quote Soleil + stats).
   - Para Kotatsu (B2C otaku/ropa): stories de fans, clientes, etc.
   - Mock data: 3 fans con métricas (ej: "300 pedidos del primer mes", "98% reviews 5 estrellas", "4.9 avg rating").
   - ~120 líneas + story.

2. **`DarkHeroCardB2C.astro`** (variante B2C del DarkHeroCard):
   - Idéntico al B2B pero:
     - Background `#0F172A` (slate-900) — charcoal limpio.
     - Mockup de playera con estampado (ProductMockup variant="shirt").
     - Squiggle ámbar.
   - Props: `eyebrow`, `heading`, `body`, `ctaLabel`, `ctaHref`.
   - Contenido B2C: "Para fans y coleccionistas — envíos express, packaging premium".
   - ~80 líneas + story.

3. **`FaqAccordion.astro`** (REUSAR el B2B existente en `components/storefront/b2b/`):
   - Reubicarlo a `components/storefront/FaqAccordion.astro` (shared) ya que aplica a ambos B2B y B2C.
   - Mismo componente, idéntica API. Solo cambia la ubicación.

### 2.2 Refactor visual de componentes B2C existentes (5)

Cada componente recibe la decoración Bonfire AÑADIENDO SVG inline + clases. NO se rompe la API.

1. **HomeHero** — agregar:
   - 3 ProductMockups flotando alrededor (rotación -8° / 0° / +12°). Para Kitsune: el variant="shirt" estampado.
   - 2 Blobs detrás (mint + peach).
   - 1 Squiggle cerca del CTA.
   - Layout 2-col desktop (copy izq, mockups der).
   - Mantener API. Solo enriquecer markup interno.

2. **FeaturedCategories** — ya tiene images, agregar:
   - 1 Squiggle decorativa en la esquina.
   - Sombra azul-fría en cada card (border-radius 16, light shadow).

3. **FeaturedProducts** — agregar:
   - 1 Blob decorativa.
   - Hover state: scale 1.02 + sombra elevada.

4. **UseCases** — agregar:
   - Avatares (no los iconos planos). Cada UseCase ahora muestra un AvatarCircle con iniciales del vertical.
   - Rotación entre -8° a +13° (mezclados).
   - 1 Squiggle.

5. **CtaBanner** — agregar:
   - 1 Squiggle decorativa.
   - Background más contrastado (slate-900 charcoal en lugar de accent primario).

## 3. i18n keys nuevas

- 6 keys para `DarkHeroCardB2C`:
  - es: `darkHero.eyebrow: 'Tu merch personalizado'`, `darkHero.heading: 'Diseña y entrega'`, `darkHero.body: 'Sin pedido mínimo, sin inventario. Desde tu idea hasta tu puerta.'`, `darkHero.ctaLabel: 'Empezar a diseñar'`.
  - en: equivalences.

- 6 keys para `CustomerStoriesB2C`:
  - es: `stories.heading: 'Lo que dicen nuestros clientes'`, `stories.subheading: '...'`, etc.
  - en: equivalences.

Total: ~12 keys nuevas.

## 4. Plan de commits (5 commits work-unit)

| # | Commit | Incluye | LOC |
|---|---|---|---|
| 1 | `feat(frontend): enriquecer visual HomeHero y UseCases B2C` | HomeHero con mockups + UseCases con AvatarCircle rotados | ~200 |
| 2 | `feat(frontend): enriquecer visual FeaturedCategories y FeaturedProducts B2C` | sombras + hover + decoración | ~80 |
| 3 | `feat(frontend): agregar CustomerStoriesB2C y DarkHeroCardB2C` | 2 componentes nuevos + 2 stories + 12 keys i18n | ~400 |
| 4 | `feat(frontend): reubicar FaqAccordion a shared y enriquecer CtaBanner B2C` | FaqAccordion de b2b/ a shared/, CtaBanner charcoal + squiggle | ~150 |
| 5 | `feat(frontend): componer decorativos y secciones nuevas en home B2C` | modifica index.astro rama B2C | ~50 |
| **Total** | | **5 commits** | **~880** |

## 5. Verificación

- `pnpm exec astro build` (workaround perms) — exit 0.
- `pnpm storybook:build` — exit 0.
- `pnpm test --run` — 182 tests passing.
- `pnpm exec astro check` — 0 errors.
- Visual: el home B2C (Kitsune/MM/Morales) debería verse con el mismo lenguaje visual que Panda, mapeado a violet en lugar de teal.

## 6. Mapeo Kitsune (CRÍTICO)

| Rol | Kitsune hex |
|---|---|
| CTA primario | `#7C3AED` (violet del tema) |
| CTA hover | `#6D28D9` (variante más oscura) |
| Dark card hero | `#4C1D95` (violet oscuro, derivado) |
| Neutrales (slate-50/100/200/600/900) | mismos que Panda |

**Mismas reglas** que Panda:
- NO copiar amarillo/rust de Bonfire (branded).
- Las neutrales y pasteles sí se portan.
- Squiggles usan ámbar `#F6C36F` o khaki `#D1C59E` (decorativos universales).

## 7. Riesgos y honestidad

- **MM-Boutique y Morales Store** también se beneficiarán de estos cambios (comparten la rama B2C). Verificar que su tenant accent (si tienen uno) no choque con violet. Por ahora en `data/tenants.ts` solo hay B2C use cases data; el tenant accent se aplica vía `--primary` que ya viene del tema.
- **Hero copy B2C sigue siendo tenant-aware** (eyebrow "Kitsune Store · Cultura japonesa" del sprint previo). NO se generaliza en este sprint — queda para commit futuro.
- **CustomerStories en B2C** tiene copy más "fan/community" que "cliente corporativo" (B2B). Mockear con cuidado para no sonar forzado.
