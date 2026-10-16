# Plan: Cerrar la brecha Bonfire en Panda Estampados (B2B)

> **Status**: en ejecución. Branch `feat/panda-bonfire-complete` desde master.
> **Source del gap**: tras PR #148 mergeado, el usuario confrontation con screenshots de bonfire.com: la home actual de panda-estampados.com/es NO refleja el patrón Bonfire. Mi plan anterior §1 sub-escopó a 5 secciones; Bonfire real tiene ~15.
> **Goal**: replicar el patrón Bonfire en la rama B2B de Panda Estampados con 6 componentes nuevos + integración en `index.astro`.

## 1. Lo que ya existe (no rehacer)

- `frontend/src/components/storefront/b2b/TenantHero.astro` — hero B2B con eyebrow + título + 4 bullets + 2 CTAs. SE MANTIENE tal cual.
- `frontend/src/components/storefront/home/FeaturedCategories.astro` — grid visual B2C con imágenes.
- `frontend/src/components/storefront/home/FeaturedProducts.astro` — grid productos destacados.
- `frontend/src/components/ui/Icon.astro` — set fijo de paths SVG (validar nombres antes de delegar).

## 2. Componentes a crear (6 nuevos para B2B Panda)

### 2.1 `frontend/src/components/storefront/b2b/StatsBar.astro`

Replica la banda de stats de Bonfire (tipo "500+ empresas atendidas / 10k+ estampados producidos / 24h respuesta").

- Props: `stats: readonly Stat[]` donde `Stat = { value: string; label: string; iconKey: string }`.
- Empty-safe: si `stats.length === 0`, no renderiza.
- Tenant-aware vía `--primary`.
- ~70 líneas.
- Story: `StatsBar.stories.ts` con Default + Empty.

### 2.2 `frontend/src/components/storefront/b2b/ProcessSteps.astro`

Replica el explainer de 4 pasos "Cotiza → Aprobamos → Producimos → Entregamos" (equivalente al stepper 1-2-3 de Bonfire).

- Props: `heading`, `steps: readonly ProcessStep[]` donde `ProcessStep = { iconKey: string; number: number; title: string; description: string; href?: string }`.
- Layout: heading + 4 cards horizontales con número circular + icono + título + descripción + CTA opcional.
- Empty-safe si steps vacío.
- ~110 líneas.
- Story: `ProcessSteps.stories.ts` con Default (4 steps) + Empty.

### 2.3 `frontend/src/components/storefront/b2b/ClientLogos.astro`

Replica el grid "trusted by" de Bonfire con logos de clientes. En B2B son logos de empresas anonimizados (Mock icons SVG del tenant color, NO logos reales, evitar implicaciones legales de marcas).

- Props: `heading`, `subheading`, `logos: readonly Logo[]` donde `Logo = { iconKey: string; label: string; href?: string }`.
- Layout: H2 + sub + grilla 4-col con "logos" (rectángulos con label tipo cliente del rubro — Restaurante, Escuela, Evento Corporativo, ONG, Equipo Deportivo, Retail).
- Empty-safe.
- ~80 líneas.
- Story: `ClientLogos.stories.ts` con Default (6 logos) + Empty.

### 2.4 `frontend/src/components/storefront/b2b/CustomerStories.astro`

Replica case studies de Bonfire con stats (ej: "$24,873 raised" + quote + customer name). En B2B: stats B2B (# órdenes procesadas, # unidades estampadas, tiempo de respuesta).

- Props: `heading`, `subheading`, `stories: readonly Story[]` donde `Story = { quote: string; customerName: string; customerRole: string; metricValue: string; metricLabel: string; avatarIconKey: string }`.
- Layout: H2 + carousel/slider de 1 story prominente con quote grande + avatar + nombre/rol + métrica destacada. Versión inicial: grid de 2-3 cards prominentes.
- Empty-safe.
- ~120 líneas.
- Story: `CustomerStories.stories.ts` con Default (3 stories) + Empty.

### 2.5 `frontend/src/components/storefront/b2b/FaqAccordion.astro`

Replica el accordion FAQ de Bonfire. **Crítico para Panda**: aclarar MOQ, NET 30, diseños, tiempos, envío, devoluciones.

- Props: `heading`, `items: readonly FaqItem[]` donde `FaqItem = { question: string; answer: string }`.
- Layout: H2 + accordion accesible (button + region, ARIA-expanded/collapsed, keyboard nav, ESC para cerrar).
- Client-side state mínimo: vanilla JS o detalles `<details>` HTML nativos (preferir `<details>` para zero-JS).
- Empty-safe.
- ~100 líneas.
- Story: `FaqAccordion.stories.ts` con Default (5 items) + Empty.

### 2.6 `frontend/src/components/storefront/b2b/MaterialsCard.astro`

Replica la card "Our sustainability mission" — para Panda: card sobre **materiales y tintas** disponibles (algodón orgánico, tintas eco, procesos).

- Props: `heading`, `eyebrow`, `body: string`, `features: readonly Feature[]` donde `Feature = { iconKey: string; label: string; description: string }`.
- Layout: card horizontal con eyebrow + H2 + body + lista de 3-4 features con icono + label + descripción.
- Empty-safe.
- ~90 líneas.
- Story: `MaterialsCard.stories.ts` con Default + Empty.

## 3. Integración (commit final)

Modificar `frontend/src/pages/[locale]/index.astro` rama B2B (`isPandaB2B === true`) para componer los 6 componentes en orden Bonfire-like, DESPUÉS de TenantHero y FeaturedCategories existentes:

```
<TenantHero />
<StatsBar />
<FeaturedCategories />
<ProcessSteps />
<ClientLogos />
<MaterialsCard />
<CustomerStories />
<FaqAccordion />
```

NO eliminar componentes existentes. NO mover nada. Solo INSERTAR los nuevos en este orden.

## 4. i18n sync (en cada commit de componente)

Cada componente agrega al `TranslationKey` union + 8-12 keys en es.ts + en.ts (heading, descripción larga, 4 process step labels, 4 descriptions, etc.). Formato `storefront.home.b2b.{section}.*`.

Para datos tipo stories/clientes/FAQ, los datos concretos viven en el archivo `.astro` como mock; solo los LABELS van en i18n.

## 5. Convenciones del frontend (de AGENTS.md)

- Componentes ≤ 150-250 líneas (target 70-120 según componente).
- PascalCase filenames. camelCase vars. UPPER_SNAKE_CASE constants.
- Props con `interface Props`. No `any`. TypeScript strict.
- Tailwind class order: layout → position → size → spacing → borders → bg → text → effects → responsive.
- HTML semántico: `<section>`, `<header>`, `<h2>`, `<details>/<summary>` para FAQ.
- Empty-safe renders solo cuando aplique.
- Storybook stories OBLIGATORIO. Variants por tenant NO aplican (estos son B2B Panda-only).
- Comentar el POR QUÉ, no el qué.
- NO emojis en código.

## 6. Locked-in decisions (no contradecir)

D1 Iconos válidos solo en `Icon.astro`. Verificar nombres antes de cada componente.
D2 Tenant-aware: `--primary` y `--primary-hover` (Panda es verde teal #2D9D8E).
D3 Stats/Susefulness concretos son mockeados pero realistas (NO "Lorem ipsum" — usar números plausibles para estampados B2B).
D4 Clientes NO son marcas reales (riesgo legal). Usar labels tipo "Restaurante", "Escuela pública", "Evento deportivo" — no nombres de empresas reales.
D5 FAQ tono B2B directo (MOQ, NET 30, sample, lead time) NO lenguaje B2C genérico.

## 7. Plan de commits (7 commits work-unit)

| # | Commit | Incluye | Estimación |
|---|---|---|---|
| 1 | feat(frontend): agregar StatsBar B2B Panda | componente + story + 4 keys i18n | 1h |
| 2 | feat(frontend): agregar ProcessSteps B2B Panda | componente + story + 10 keys i18n | 1.5h |
| 3 | feat(frontend): agregar ClientLogos B2B Panda | componente + story + 4 keys i18n | 1h |
| 4 | feat(frontend): agregar CustomerStories B2B Panda | componente + story + 6 keys i18n | 1.5h |
| 5 | feat(frontend): agregar FaqAccordion B2B Panda | componente + story + 8 keys i18n | 1h |
| 6 | feat(frontend): agregar MaterialsCard B2B Panda | componente + story + 6 keys i18n | 1h |
| 7 | feat(frontend): componer secciones nuevas en home B2B Panda | modifica index.astro rama B2B | 0.5h |
| **Total** | | **7 commits** | **~7.5h** |

## 8. Verificación final

- `cd frontend && pnpm exec astro build --outDir /tmp/astro-build-test` — verde.
- `cd frontend && pnpm storybook:build` — verde.
- `cd frontend && pnpm test --run` — verde.
- `cd frontend && pnpm exec astro check` — 0 errors, 0 warnings.
- Inspección visual: el home de Panda debe tener visible: TenantHero (existente) + StatsBar (cifras) + Categorías (existente) + ProcessSteps (4 pasos) + ClientLogos (grid) + MaterialsCard (card) + CustomerStories (cards con stats) + FaqAccordion (Q&A). Total ~9 secciones visibles en scroll.

## 9. Riesgos y honestidad

- **No logos reales de clientes**: por riesgo legal, los "logos" son iconos SVG genéricos con label de rubro (Restaurante, Escuela, etc). Si en el futuro hay clientes reales con permiso, swap por SVG real.
- **Stats son mock**: 500+ empresas atendidas, 24h respuesta, etc. Números plausibles pero inventados. Reemplazar cuando haya métricas reales del backend.
- **CustomerStories sin caras reales**: avatars son iconos SVG (no fotos para evitar issues legales de privacidad).
- **FAQ sin respuesta de soporte real**: las respuestas son las que aplican a la lógica de Panda (MOQ ≥ 10 unidades, NET 30 requiere aprobación, etc.) — validar con el usuario si la lógica es correcta antes de mergear.
