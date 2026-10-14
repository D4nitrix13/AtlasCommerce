# AtlasCommerce — Prompt v3: Home con mega menu Nike-style

> **Propósito**: prompt enfocado solo en la página principal (/) con mega menu estilo Nike.com. Las otras 11 vistas (catálogo, detalle, cart, checkout, mis órdenes, login, signup, RFQ, bulk, admin dashboard, admin orders) tienen su propio prompt en `odd/tasks/atlascommerce-prompts-plan.md`.
> **Audiencia**: una IA generativa de imágenes/diseños (GPT, Claude, Midjourney) que NO tiene contexto previo.

---

## 1. Contexto del proyecto

**AtlasCommerce** es una plataforma multi-tenant de comercio electrónico multi-instancia. Atiende a cuatro tiendas con branding diferenciado:

| Tenant | Modelo | Estilo para home | Categorías mega menu |
|---|---|---|---|
| **Panda Estampados** | B2B | Nike B2B minimal (white + teal) | Servicios, Catálogo, Casos, Cotizar, FAQ |
| **Kitsune Store** | B2C | Nike sport (white + violet) | Productos, Anime, Manga, Figuras, Ropa, Ofertas |
| **MM Boutique** | B2C | Nike boutique (white + pink) | Productos, Categorías, Marcas, Nuevo, Ofertas |
| **Morales Store** | B2C | Nike retail (white + blue) | Productos, Categorías, Marcas, Liquidación |

**Stack técnico** (sin cambios):
- **Framework**: Astro 7 SSR (output: server, adapter: node standalone)
- **Estilos**: Tailwind CSS 4 + CSS custom
- **Interactividad**: React 19 selectivo (mega menu es vanilla JS o CSS hover)
- **Tipografía**: Inter o system-ui (jerarquía bold sans-serif)
- **Tokens**: ver `docs/frontend/design-references-nike.md`

---

## 2. Estructura exacta de la home que debe generar la IA

### A. Top utility bar (38px alto, fondo `#111111`, texto blanco)

Logo Jordan a la izquierda (rebrandable a "Kitsune B2B" o similar), seguido de "Find a Store", "Help", "Join Us", "Sign In". Separador vertical entre cada item.

### B. Brand row (72px alto, fondo `#FFFFFF`, sticky al top del viewport)

- **Izquierda**: Logo del tenant (swoosh-style para Kitsune, monograma PM para Panda, MM para MM Boutique, MS para Morales) + nombre
- **Centro**: Mega menu con 5-6 items principales, cada uno con dropdown de 4-5 columnas al hacer hover
- **Derecha**: 3 iconos — search (lupa), favorites (corazón), cart (bolsa)
- **Sticky** al hacer scroll

### C. Mega menu dropdown (al hacer hover en item principal)

Estructura del dropdown (variant por tenant, ejemplo Kitsune):

```
┌────────────────────────────────────────────────────────────────┐
│ [Kitsune ▾]  [Anime ▾]  [Manga ▾]  [Figuras ▾]  [Ropa ▾]  [Ofertas ▾]   🔍 ❤ 🛒 │
├────────────────────────────────────────────────────────────────┤
│ (cuando hover en "Anime")                                              │
│ ┌──────────────┬──────────────┬──────────────┬──────────────┐         │
│ │ Popular      │ Tops          │ Por género   │ Por estudio │         │
│ │ - Nuevos     │ - Remeras     │ - Shonen     │ - Ghibli     │         │
│ │ - Destacados │ - Hoodies     │ - Seinen     │ - MAPPA      │         │
│ │ - Ofertas    │ - Manga corta │ - Isekai     │ - Bones      │         │
│ │              │ - Crop tops   │ - Romance    │              │         │
│ │              │               │ - Mecha      │              │         │
│ ├──────────────┼──────────────┼──────────────┼──────────────┤         │
│ │ 1.jpg        │ 2.jpg        │ 3.jpg        │ 4.jpg        │         │
│ │ Featured     │ Lo más nuevo  │ Top 10        │ En tendencia │         │
│ └──────────────┴──────────────┴──────────────┴──────────────┘         │
└────────────────────────────────────────────────────────────────┘
```

**Reglas del mega menu**:
- Activación por hover (CSS `:hover` puro, sin JS necesario)
- Ancho completo del viewport o fixed al item hovered
- Fondo blanco `bg-white`, con shadow sutil
- Cada columna con header de categoría y 4-6 items listados
- Imágenes de "Featured" o "Trending" en la parte inferior
- Cierre por mouseleave o click fuera
- Mobile (<lg): hamburger menu con accordion vertical

### D. Hero section (full-bleed, 80vh)

- Imagen full-bleed con un modelo/persona usando productos del tenant
- Para Kitsune: un fan en cosplay o con figuras
- Para Panda: un cliente corporativo con una playera estampada con logo
- Para MM: una modelo con outfit boutique
- Para Morales: una familia comprando retail
- **Headline bold** (text-5xl a text-7xl) sobre el lado derecho con eyebrow arriba
- **Subtítulo** y **CTA negro rectangular** "Shop now" / "Cotizar volumen" sin border radius

### E. Carrusel de productos destacados (después del hero)

3-4 cards de producto en grid horizontal scrollable con snap.

### F. Secciones adicionales (opcional según espacio)

- Categorías destacadas (grid 4-col con imagen + nombre)
- Best sellers (carrusel 2x4)
- Trending / New arrivals (carrusel)
- Banner promocional full-bleed con texto bold
- Footer multi-columna con sitemap + suscripción

### G. Footer

Multi-columna con sitemap de categorías + cuenta + recursos + legal. Background negro `bg-nike-black`, texto blanco.

---

## 3. Tokens exactos a usar

- Backgrounds: `bg-white` o `bg-slate-50` (nunca cream)
- Cards: `bg-white rounded-none` o `rounded-sm border border-nike-gray-200`
- Botones primarios: `bg-nike-black text-white px-6 py-3 rounded-none font-semibold uppercase tracking-wide`
- Botones secundarios: `bg-white text-nike-black border border-nike-black`
- Tipografía: Inter (open source fallback) o system-ui, jerarquía bold sans-serif
- Whitespace: generoso entre secciones (`py-20` a `py-32`)
- Sin decorativos orgánicos (cero squiggles, blobs, sunbursts)
- Sin rounded-2xl (Nike es más rectangular)

---

## 4. Convenciones de implementación

- **Componentes ≤ 250 líneas** (frontend/AGENTS.md §1)
- **PascalCase** filenames, **camelCase** vars, **UPPER_SNAKE_CASE** constants
- **Props** con `interface Props`, TypeScript strict
- **Tailwind** class order: layout → position → size → spacing → borders → bg → text → effects → responsive
- **HTML semántico**: `<header>`, `<nav>`, `<main>`, `<section>`, `<button>`, `<form>`, `<details>` para mobile menu
- **Mega menu**: `position: absolute` sobre item hovered, `display: group-hover:block` con Tailwind
- **Sin librerías de animación** (no Framer Motion, no GSAP)
- **Sin emojis en código**
- **No usar `ring` de Tailwind** para focus (usar `outline` con `--nike-black`)
- **Conventional commits en español** si la IA genera código

---

## 5. Variaciones por tenant

La IA debe producir 4 versiones de la home (una por tenant) en el mismo ZIP:

| Tenant | Categorías mega menu | Hero imagery | CTA principal | Accent |
|---|---|---|---|---|
| Panda | Servicios, Catálogo, Casos, Cotizar, FAQ | Cliente corporativo con playera estampada | "Cotizar volumen" | Teal `#2D9D8E` |
| Kitsune | Productos, Anime, Manga, Figuras, Ropa, Ofertas | Fan en cosplay con figuras | "Comprar ahora" | Violeta `#7C3AED` |
| MM | Productos, Categorías, Marcas, Nuevo, Ofertas | Modelo con outfit boutique | "Ver colección" | Pink `#DB2777` |
| Morales | Productos, Categorías, Marcas, Liquidación | Familia comprando | "Explorar" | Azul `#2563EB` |

---

## 6. Lo que NO debe hacer

- NO debe usar el logo o marca registrada de Nike
- NO debe usar la tipografía Nike Futura ND (usar Inter o system-ui)
- NO debe usar el slogan "Just Do It" o copy trademarked
- NO debe usar fotos de atletas profesionales de Nike
- NO debe usar cream backgrounds estilo Bonfire
- NO debe usar decorativos orgánicos (squiggles, blobs, sunbursts)
- NO debe usar rounded-2xl (Nike es más rectangular)
- NO debe usar shadow azul-fría 3-capas de Bonfire

---

## 7. Formato de salida esperado

Para CADA uno de los 4 tenants, la IA generativa debe producir:

1. **Hero image desktop** (1440×900px mínimo, PNG o SVG inline)
2. **Hero image mobile** (375×812px)
3. **Mega menu abierto** (mostrando el dropdown del item principal seleccionado)
4. **Vista mobile** del mega menu (hamburger con accordion vertical)
5. **Carrusel de productos** del tenant (4-5 productos visibles)
6. **Footer** multi-columna con el sitemap del tenant
7. **Tokens custom** que se necesitan
8. **Lista de componentes Astro/React** sugeridos para implementar
9. **i18n keys** con texto en es + en

Si la IA propone algo que rompa las convenciones (cream backgrounds, rounded-2xl, decorativos), debe justificarlo y pedir aprobación.

---

## 8. Referencias cruzadas

- `docs/frontend/design-references-nike.md` — design system Nike v1 con tokens
- `odd/tasks/atlascommerce-design-prompt-v2.md` — prompt v2 multi-vista (este es v3 enfocado en home)
- `odd/tasks/atlascommerce-prompts-plan.md` — plan de prompts categorizados
- `frontend/AGENTS.md` — convenciones del proyecto

---

**Versión**: v3 (2026-10-14) — Nike home focus
**Reemplaza**: el bloque home de v2
**Próxima revisión**: cuando cambien los tokens Nike o se defina el flujo mobile del mega menu con más detalle
