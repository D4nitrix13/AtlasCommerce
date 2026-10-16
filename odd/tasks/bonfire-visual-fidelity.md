# Plan: Bonfire visual fidelity para Panda Estampados (B2B)

> **Status**: en ejecución. Branch `feat/bonfire-visual-fidelity` desde master.
> **Source**: el usuario me pasó el design system COMPLETO de bonfire.com scrapeado por una IA externa. Doc preservado en `docs/frontend/design-references-bonfire.md` (~150 líneas con todos los tokens, tipografía, shapes, lenguaje decorativo, y el prompt para IA).
> **Goal**: aplicar el lenguaje visual de Bonfire (tokens, shapes, decorativos, mockups SVG de productos, avatares SVG) al home B2B Panda de 9 secciones que ya tenemos del sprint anterior.
> **Adaptación**: NO copio los colores branded de Bonfire. Mapeo neutrales a Tailwind slate-50/100/200/900/600, y los verdes Bonfire a Panda teal (#2D9D8E). Los pasteles y decorativos (mint/peach/amber) se portan tal cual.

## 1. Lo que YA existe (no rehacer)

- 8 componentes B2B del sprint anterior: TenantHero, StatsBar, ProcessSteps, ClientLogos, MaterialsCard, CustomerStories, FaqAccordion, plus FeaturedCategories existente.
- Composición en `index.astro` rama B2B lista.
- 30+ keys i18n en `storefront.home.b2b.*`.

## 2. Lo que falta para fidelidad Bonfire

### 2.1 Tokens de diseño (Tailwind + CSS vars)

- Card radius (16px), inner (8px), pill (999px) — ya se usan algunos, falta sistematizar.
- Shadow azul-fría de 3 capas (nunca negra).
- Escala tipográfica H1 66-90px (ya se acerca con `text-4xl/5xl/6xl` de Tailwind), H2 50px, H3 38px.
- Custom utilities para `border-radius: 62% 38% 45% 55%` (blobs orgánicos).

### 2.2 Primitivas decorativas (4 nuevos componentes en `frontend/src/components/ui/`)

Estos se usan en TODO el home para inyectar el lenguaje Bonfire:

1. **`Squiggle.astro`** — trazo a mano ámbar `#F6C36F`, 3px stroke, parcialmente fuera del frame. Props: `class?`, `variant?: 'arc' | 'wave' | 'loop'`. ~30 líneas + story.

2. **`Blob.astro`** — fondo orgánico melocotón/ámbar/menta con `border-radius: 62% 38% 45% 55%`. Props: `color?: 'peach' | 'mint' | 'amber'`, `size?: 'sm' | 'md' | 'lg'`. ~40 líneas + story.

3. **`ProductMockup.astro`** — SVG silueta de playera/hoodie/mug/hat con un logo estampado genérico al centro. Props: `variant: 'shirt' | 'hoodie' | 'mug' | 'hat'`, `accentColor?: string`, `logoText?: string`. ~120 líneas + story con 4 variants.

4. **`AvatarCircle.astro`** — círculo con iniciales + gradiente del tenant, con prop de rotación `rotation?: number` (-8° a +13°). Props: `name: string`, `role?: string`, `accentColor?: string`, `rotation?: number`. ~60 líneas + story.

### 2.3 Refactor visual de componentes existentes

Cada componente recibe enriquecimientos decorativos AÑADIENDO SVG inline + clases nuevas al markup existente. NO se renombran props ni se rompe la API. La estrategia es **agregar** capas decorativas detrás del contenido (z-index bajo, `aria-hidden`).

1. **TenantHero** — agregar:
   - 3 ProductMockup flotando alrededor del hero (rotación -8° a +13°)
   - 2 Blob detrás del hero (mint + peach)
   - 1 Squiggle cerca del CTA
   - Ajustar H1 a la escala Bonfire (mantener `text-4xl/5xl/6xl` Tailwind que ya da ~36-60px)

2. **StatsBar** — agregar:
   - 1 Squiggle decorativa a la derecha
   - Stats más grandes (números pasan de `text-base` a `text-4xl/5xl`)

3. **ProcessSteps** — agregar:
   - Números circulares más grandes con gradiente del tenant
   - 1 Squiggle decorativa

4. **ClientLogos** — reemplazar iconos por **"logos" SVG más ricos** (rectángulos con icono + label tipo badge redondeado, fondo blanco, sombra azul-fría).

5. **CustomerStories** — reemplazar avatares iconos por **AvatarCircle** con iniciales y rotación.

6. **FaqAccordion** — agregar:
   - El `+` en círculo teal (`#415F62` Bonfire → Panda teal oscuro) según design system
   - Dividers `#E6E0D6` → slate-200

7. **MaterialsCard** — agregar:
   - 1 ProductMockup de tela estampada al lado derecho
   - 1 Squiggle decorativa

### 2.4 Componentes NUEVOS que Bonfire tiene y el sprint anterior omitió

- **`CtaFinal.astro`** — sección full-width `#1B1E24` (→ slate-900), CTA de ancho completo con borde blanco 2px. Contenido: "Lleve su estampado al siguiente nivel" + botón outline blanco → `/rfq`. Replica el "Take your custom merch to the next level" de Bonfire.
- **`DarkHeroCard.astro`** — card oscura con eyebrow + H3 blanco + CTA blanco + decorativos (squiggle ámbar, mockup de producto). Equivalente al verde bosque `#234338` de Bonfire. Para Panda: fondo `#1F4F47` (derivado del teal oscuro). Contenido: "Estampado para tu equipo — sin inventario, sin MOQ mínimo".

### 2.5 Integración en `index.astro` rama B2B

Insertar `CtaFinal` antes de cerrar `</>`, y `DarkHeroCard` entre `ProcessSteps` y `ClientLogos` (o donde tenga más sentido narrativo).

## 3. i18n sync

- Agregar keys nuevas para `CtaFinal` y `DarkHeroCard` (~8 keys cada uno).
- Posibles keys nuevas para los headings rebalanceados (~4 keys, si cambia copy).

## 4. Plan de commits (6 commits work-unit, secuenciales en una rama)

| # | Commit | Incluye | LOC |
|---|---|---|---|
| 1 | feat(ui): primitives decorativas Bonfire | Squiggle + Blob + ProductMockup + AvatarCircle (4 componentes, 4 stories, ninguna key i18n nueva) | ~350 |
| 2 | feat(frontend): enriquecimiento visual TenantHero + StatsBar | agregar decorativos a los 2 componentes existentes | ~150 |
| 3 | feat(frontend): enriquecimiento visual ProcessSteps + ClientLogos + MaterialsCard | ClientLogos pasa de iconos a "logos" SVG, MaterialsCard agrega mockup | ~200 |
| 4 | feat(frontend): enriquecimiento visual CustomerStories + FaqAccordion | CustomerStories usa AvatarCircle rotados, FaqAccordion aplica + teal oscuro | ~150 |
| 5 | feat(frontend): agregar DarkHeroCard y CtaFinal | 2 componentes nuevos + 2 stories + 16 keys i18n | ~300 |
| 6 | feat(frontend): componer decorativos y secciones nuevas en home B2B Panda | modifica index.astro rama B2B | ~100 |
| **Total** | | **6 commits** | **~1250** |

## 5. Verificación

- `cd frontend && pnpm exec astro build --outDir /tmp/astro-build-test` — exit 0.
- `cd frontend && pnpm storybook:build` — exit 0.
- `cd frontend && pnpm test --run` — verde.
- `cd frontend && pnpm exec astro check` — 0 errors.
- Inspección visual: la home de Panda debería parecerse al patrón visual Bonfire (no idéntica, pero misma estética: pastel neon decorado sobre crema, hierarchy sobria, sombras azul-frías, mockups SVG en lugar de iconos planos).

## 6. Riesgos y honestidad

- **No copio al Bonfire exactamente** — el acento de Panda es teal, no verde Bonfire. La paleta neutral (cremas, slate) SÍ se porta casi literal. Los pasteles (mint/peach/amber) también. Lo que cambia es solo el verde.
- **Mockups de productos son SVG simples**, no fotos realistas. Representan playera/hoodie/mug/hat con un logo genérico. Es honest disclosure — no parecen fotos reales de producto.
- **CtaFinal replica Bonfire solo en color** (footer charcoal full-width, CTA outline blanco). El contenido sigue siendo del tenant Panda con su propio copy en español.
- **Avatares SVG con iniciales + gradiente del tenant** — no son fotos de personas reales. Privacidad-safe.
- **Squiggles y blobs son decorativos**, `aria-hidden`, sin contenido semántico.

## 7. Pendiente (futuro, NO en este sprint)

- Aplicar el mismo tratamiento visual a Kitsune B2C (su home actual es más pobre). Esta es otra rama/PR separada.
- Aplicar a MM-Boutique y Morales Store (sus use cases sí están en el sprint anterior, pero sin la fidelidad visual).

## 8. Cómo continuar en otra sesión

Si esta sesión se interrumpe aquí, el branch `feat/bonfire-visual-fidelity` queda con N commits done + M por hacer. Revisar `git log master..HEAD` para saber dónde quedó.

El doc `docs/frontend/design-references-bonfire.md` es la referencia permanente. Cualquier sprint futuro de fidelidad visual empieza leyéndolo.
