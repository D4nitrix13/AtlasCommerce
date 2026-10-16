# Plan: Adapt home page de Bonfire al design system multi-tenant de AtlasCommerce

> **Status**: plan actualizado 2026-10-12. Drift corregido. 4 preguntas
> abiertas resueltas con recomendación. 2 preguntas aún pendientes de
> decisión del usuario. Listo para implementar al confirmar.
>
> **Source**: investigación de `bonfire.com` (HTML 642 KB, Framer React
> site). El CSS/JS ofuscado no es replicable, pero el patrón visual
> (jerarquía de secciones, copy, paleta) sí lo es.
>
> **Goal**: replicar la home de Bonfire adaptada a las 4 tiendas
> (Panda Estampados → Kitsune → MM-Boutique → Morales Store) con el
> mismo esqueleto, distintos colores + copy + imágenes por tenant.

## 1. Patrón visual de Bonfire (sin cambios desde el plan original)

Color palette apagada (verde `#2B8265` como primary, no neón),
secciones con padding generoso, H1 grande, jerarquía clara, CTAs
filled/outline. Estructura de la home reducida para AtlasCommerce a:

```
1. Hero            (eyebrow + H1 + subtitle + 2 CTAs + trust badges)
2. Categorías      (grid 2x2 visual con imágenes, ya existe)
3. Productos       (destacados con badges, ya existe)
4. Use cases       (4 cards por vertical — "para quién es esto")
5. CTA banner      (full-width, fondo accent, 1 CTA)
```

Se descartan para AtlasCommerce: Trustpilot rating (no tenemos
sistema de reviews), logos de clientes (no tenemos clientes
verificables aún), case studies (idem), FAQs (no hay backend que
las soporte todavía).

## 2. Adaptación al codebase actual

### 2.1 Estado verificado (drift corregido 2026-10-12)

| Archivo o assumption del plan original | Realidad actual |
|---|---|
| `home/TenantHero.astro` | **No existe**. `TenantHero.astro` está en `components/storefront/b2b/` (importado en `index.astro:28`). |
| `FeaturedCategories` usa iconos | **Ya usa imágenes** (gradient overlay, white text, grid 2/3/4 cols). El "Commit 2 → cambiar a imágenes" ya está hecho. |
| `HeroBanner.astro` no existe | **Existe** (84 líneas, tenant-aware via `--primary`, define `HeroCta` interface reutilizable). |
| `HeroBanner.stories.ts` no existe | **Existe**, patrón a seguir para los nuevos componentes. |
| Hay que agregar `imageAlt` a `Category` type | **No hace falta**. El type `Category` en `types/catalog.ts` ya tiene `slug / name / imageUrl / href`. |
| i18n keys nuevas para hero/categories | **Ya existen**: `storefront.home.hero.{eyebrow,title,subtitle,ctaPrimary,ctaSecondary}` y `featuredCategories.*`, `featuredProducts.*`. **Faltan**: `trustBadge.*`, `useCases.*`, `ctaBanner.*`, per-tenant case labels. |
| `data/products.ts` solo categorías | Tiene `CATEGORIES` + `PRODUCTS` + helpers. Para useCases por tenant, mejor archivo separado `data/tenants.ts`. |

### 2.2 Componentes a crear (3 nuevos + 3 stories)

1. **`HomeHero.astro`** — reemplazo de `HeroBanner.astro` para la rama B2C.
   - Mismo patrón + `trustBadges[]?: TrustBadge[]` prop.
   - Reutiliza `HeroCta` interface (export desde archivo nuevo o refactor al de `HeroBanner`).
   - Slots/empty: si no hay trustBadges, no renderiza la fila.
   - ~120 líneas.

2. **`UseCases.astro`** — los "personas" de Bonfire adaptados.
   - Props: `heading: string`, `subheading?: string`, `cases: readonly UseCase[]`.
   - Layout: H2 + grid de cards circulares (icon + label) debajo.
   - Solo renderiza si `cases.length > 0` (empty-safe).
   - ~90 líneas.

3. **`CtaBanner.astro`** — banner full-width de cierre.
   - Props: `heading: string`, `ctaLabel: string`, `ctaHref: string`.
   - Tenant-aware vía `--primary`, sin prop de accent.
   - ~70 líneas.

### 2.3 Stories (3 archivos)

Patrón obligatorio según `frontend/AGENTS.md` §22. Cada story
incluye `Default` + variants por tenant cuando aplique.

- `HomeHero.stories.ts` → Default + Panda + Kitsune + MM-Boutique + Morales Store variants
- `UseCases.stories.ts` → Default (4 cases) + Empty (cases: [])
- `CtaBanner.stories.ts` → Default + per-tenant variants

### 2.4 Archivos a modificar (3)

1. **`frontend/src/pages/[locale]/index.astro`**
   - Branch B2C (`else`, no-Panda): reemplazar `HeroBanner` por
     `HomeHero`. Insertar `UseCases` después de `FeaturedProducts`.
     Insertar `CtaBanner` antes de cerrar `</StorefrontLayout>`.
   - Branch B2B (Panda): sin cambios.
   - La lógica tenant-aware con `tenantSlug === 'panda-estampados'`
     ya está. No hace falta refactor mayor.

2. **`frontend/src/i18n/locales/{es,en}.ts`** + **`types.ts`**
   - 6 keys shared nuevas (en es + en + types):
     - `storefront.home.trustBadge.riskFree`
     - `storefront.home.trustBadge.noInventory`
     - `storefront.home.trustBadge.easyUse`
     - `storefront.home.useCases.heading`
     - `storefront.home.useCases.subheading`
     - `storefront.home.ctaBanner.label`
   - Per-tenant use case labels: `storefront.home.{tenant}.useCase.{key}`
     - 4 tenants × 4 cases × 2 idiomas = **32 keys totales** (16 en `es.ts`, 16 en `en.ts`).
     - Pendiente del usuario definir los nombres exactos (ver §3 Q7).

3. **`frontend/src/data/tenants.ts`** (NUEVO archivo)
   - Tipo: `Record<TenantSlug, { useCases: readonly UseCase[] }>`.
   - Datos mock por tenant hasta que exista el endpoint backend.
   - Separar de `data/products.ts` para mantener SoC: useCases son
     data de marketing, no del catálogo.

### 2.5 Lo que queda intocado

- `HeroBanner.astro` y su story → no se borran (Kitsune podría
  necesitarlos en una versión simplificada de la home). Solo se
  deja de importar desde `index.astro`.
- `TenantHero.astro` en `b2b/` → B2B no cambia.
- `FeaturedCategories.astro` → ya está visualmente bien.
- `FeaturedProducts.astro` → ya está bien.

## 3. Decisiones sobre las 4 preguntas abiertas (2026-10-12)

### Q1. Imágenes — resuelta

**Decisión**: SVG placeholders con gradientes del accent color del tenant.

**Por qué**: Ya existe el patrón `PLACEHOLDER_IMAGE` en `data/products.ts:24-39`.
Reutilizar el helper. Costo: 0 assets externos. Cuando lleguen los
mockups reales, swap por `<img src="/products/{slug}.webp">` en
`UseCases`.

### Q2. Testimonios / Reviews — resuelta

**Decisión**: NO incluir sección SocialProof en esta iteración.

**Por qué**: Testimonios ficticios sin review system real son
deshonestos (engañan al usuario sobre lo que ve). Mejor: cuando
exista el sistema de reviews, agregar la sección de verdad con
datos reales.

**Elimina del scope**: `SocialProof.astro` y sus dependencias i18n
(5 keys que estaban en el plan original).

### Q3. Logos de clientes ("trusted by") — resuelta

**Decisión**: Eliminar la sección y reemplazarla por `UseCases`.

**Por qué**: `UseCases` cumple función similar (construir confianza)
sin requerir clientes reales. Lleva al usuario a destinos útiles
(landing de cada vertical). Más honesto que logos inventados.

### Q4. Orden de commits — resuelta

**Decisión**: 5 commits consolidados (no los 6 originales).

**Razón**:
- Commit 2 original era "FeaturedCategories → imágenes" — moot (ya hecho).
- SocialProof eliminado → 1 componente menos.
- Consolidar los 4 tenants en menos commits mejora la revisión
  visual (cada PR = home completa revisable, no piezas huérfanas).

## 4. Plan de implementación — 5 commits work-unit

### Commit 1: 3 componentes B2C shared + stories + i18n shared

**Incluye**:
- `frontend/src/components/storefront/home/HomeHero.astro` (~120 LOC).
- `frontend/src/components/storefront/home/UseCases.astro` (~90 LOC).
- `frontend/src/components/storefront/home/CtaBanner.astro` (~70 LOC).
- Sus 3 `*.stories.ts` con variants por tenant.
- i18n: 6 keys shared nuevas en `es.ts`, `en.ts` y `types.ts`.
- `HomeHeroCta` interface exportada desde `HomeHero.astro` (o refactor desde `HeroBanner.astro`).

**Verificación**: `pnpm build` en `frontend/` sin errores. Storybook
build verde. Sin tests unit (pure presentation).

**Branch**: no se commitea directamente a master → feature branch `feat/home-bonfire-adaptation`.

### Commit 2: Tenant data + Panda composition

**Incluye**:
- `frontend/src/data/tenants.ts` (NUEVO) — `Record<TenantSlug, UseCase[]>`.
- i18n: 8 keys Panda use cases (4 × 2 idiomas).
  - Slugs tentativos: `individual`, `events`, `business`, `sports`. Pendiente validación.
- `frontend/src/pages/[locale]/index.astro`:
  - Branch B2C (`!isPandaB2B`): reemplazar `HeroBanner` por `HomeHero`, insertar `UseCases` después de `FeaturedProducts`, insertar `CtaBanner` antes de `</StorefrontLayout>`.
  - Branch B2B: sin cambios.
- Panda theme accent (`#2D9D8E`) sin tocar — `StorefrontLayout.astro` ya inyecta `--primary` por tenant.

**Verificación**: `pnpm build` + `pnpm storybook` visual QA en las 4 páginas tenant (Panda B2B no cambia, Kitsune/MM/Morales ya renderizan).

### Commit 3: Kitsune variant

**Incluye**:
- i18n: 8 keys Kitsune use cases.
  - Slugs tentativos: `figures`, `anime`, `manga`, `cosplay`. Pendiente validación.
- Hero copy en `es.ts`/`en.ts` ajustado al nicho otaku (revisar tono
  actual — los textos existentes pueden servir).

**Verificación**: visual QA en `/es/` (no Panda) y `/en/` (no Panda).

### Commit 4: MM-Boutique + Morales Store variants

**Incluye**:
- i18n: 16 keys (8 × 2 tiendas).
- MM-Boutique vertical (moda/ropa) — slugs pendientes del usuario.
- Morales Store vertical (abarrotes/retail) — slugs pendientes del usuario.

**Verificación**: visual QA en los 2 tenants adicionales.

### Commit 5: Visual QA + accessibility + commit hygiene

**Incluye**:
- `pnpm build` final — sin errores.
- Storybook QA — capturar screenshots de las 4 variantes por si el usuario pide review visual.
- Accessibility check: `pnpm lighthouse` o `axe DevTools` sobre las 4 home variants — sin violations AA.
- Conventional commit messages sin markdown en body (skill ya documentado).
- Push a `feat/home-bonfire-adaptation` → PR con descripción enlazando este doc.

## 5. Estimación rough

| Commit | Estimación | Deps | Notas |
|---|---|---|---|
| 1 — componentes shared + stories + i18n shared | 2.5 h | — | 3 componentes + 3 stories = 6 archivos nuevos |
| 2 — Panda data + composition | 1.5 h | 1 | Data file + i18n + modificación de `index.astro` |
| 3 — Kitsune variant | 0.5 h | 2 | Solo copy + i18n keys |
| 4 — MM + Morales variants | 1 h | 2 | 2 tenants con sus 16 keys |
| 5 — QA visual + a11y + push + PR | 0.5 h | 1-4 | Verificación global |
| **Total** | **~6 h** | — | **5 commits** |

## 6. Riesgos y honestidad

**Lo que SÍ puedo hacer**:
- Componentes Astro + Tailwind siguiendo patrones de Bonfire.
- i18n keys sincronizadas en `es.ts` + `en.ts` + `types.ts`.
- 4 variantes de tenant con mismo esqueleto.
- Storybook con variants por tenant (regla del frontend).

**Lo que necesito del usuario antes de Commit 1**:
1. **Nombres exactos de las 4 use cases por tenant** (ver §7).
2. **Emoji o SVG icons** en UseCases (ver §7).

**Lo que NO puedo hacer (queda fuera del scope)**:
1. **Imágenes reales de productos Panda** estampados (mockups con
   logos) — uso SVG placeholders con gradientes hasta tener material
   real.
2. **Testimonios reales** — descartado en esta iteración (Q2).
3. **Logos de clientes** — descartado, reemplazado por `UseCases` (Q3).
4. **Hero image lifestyle** (Bonfire usa foto de modelo con merch) —
   decorado con SVG/gradiente hasta tener foto real.

**Riesgo técnico bajo**: tenant detection ya funciona, theme system
estable, i18n types estrictos. Toda la complejidad nueva es UI pura.

## 7. Decisiones locked-in 2026-10-12 (resueltas con el usuario)

Q5. **Use cases por tenant** — APROBADAS por el usuario:

| Tenant | u1 | u2 | u3 | u4 | Segmentación |
|---|---|---|---|---|---|
| Panda Estampados | Individuos | Eventos | Empresas | Equipos/Deportes | Por rol / tipo de cliente (B2B-aware) |
| Kitsune | Figuras | Anime | Manga | Cosplay | Por vertical del nicho otaku |
| MM-Boutique | Eventos | Diario | Trabajo | Accesorios | Por **ocasión** (boutique aspiracional) |
| Morales Store | Mujer | Hombre | Niños | Accesorios | Por **demografía** (tienda general, variedad) |

Total i18n keys per-tenant: 4 tenants × 4 cases × 2 idiomas = **32 keys**
(formato `storefront.home.{tenant}.useCase.{slug}`).

**Razonamiento por tenant**: la gente que entra a una boutique
busca "qué ponerme para X evento". La gente que entra a una
tienda general primero busca "sección donde está lo mío"
(mujer/hombre/niños). Patrón Zara vs Falabella.

Q6. **Iconos en `UseCases.astro`** — APROBADO:
- **`lucide-react`** — ya está en `frontend/package.json`, accesible
  (aria-label automático), `currentColor` se hereda del theme.
- Set tentativo: `User`, `PartyPopper`, `Building2`, `Trophy`
  (Panda); `Sparkles`, `Tv`, `BookOpen`, `Drama` (Kitsune);
  `GlassWater`, `Sun`, `Briefcase`, `ShoppingBag` (MM);
  `User`, `UserCheck`, `Baby`, `Package` (Morales).
- Ajustables durante implementación si el visual no cierra.

## 8. Cómo continuar en otra sesión

Estado al cerrar: **plan actualizado con 4 decisiones tomadas, 2
pendientes del usuario. No implementado.**

Próximos pasos:
1. Responder §7 (Q5 nombres use cases + Q6 tipo de iconos).
2. Confirmar branch de trabajo (`feat/home-bonfire-adaptation` o nombre preferido).
3. Arrancar Commit 1 (3 componentes + 3 stories + 6 keys shared).
4. Visual review tras cada commit + iteración.

Este doc vive en `odd/tasks/home-redesign-bonfire-adaptation.md`.
Spec-companion de tracking en memoria con topic key
`architecture/home-redesign-bonfire`.
