# AtlasCommerce — Design system reference Nike (v1)

> **Propósito**: prompt v2 (Nike inspiration) que reemplaza v1 (Bonfire) para la próxima tanda de prompts. La home redo y las nuevas admin views usarán este design system.
> **Audiencia del prompt**: una IA generativa de imágenes/diseños (GPT, Claude, Midjourney) que NO tiene contexto previo.

---

## 1. Contexto del proyecto

**AtlasCommerce** es una plataforma multi-tenant de comercio electrónico multi-instancia. Atiende a cuatro tiendas con branding y experiencias diferenciadas:

| Tenant | Dominio | Modelo | Vertical | Estilo |
|---|---|---|---|---|
| **Panda Estampados** | panda-estampados.com | B2B | Estampado textil | Bonfire editorial (denso, confianza) |
| **Kitsune Store** | kitsune.com | B2C | Otaku/figuras | **Nike sport** (minimalista, producto) |
| **MM Boutique** | mm-boutique.com | B2C | Moda/zapatos | **Nike minimalista** (boutique) |
| **Morales Store** | morales-store.com | B2C | Retail general | **Nike retail** (grid limpio) |

### Decisión clave
- **B2C (Kitsune/MM/Morales) → Nike** aesthetic
- **B2B (Panda) → Bonfire** editorial (referencia historica en `design-references-bonfire.md`)
- **Vistas mixtas B2B admin → Bonfire base** con acentos Nike en CTAs primarios

---

## 2. Stack técnico (sin cambios)

- **Framework**: Astro 7 SSR (output: server, adapter: node standalone)
- **Estilos**: Tailwind CSS 4 + CSS custom
- **Interactividad**: React 19 selectivo
- **TypeScript**: 6 strict
- **i18n**: `es` / `en` via `getServerDictionary`
- **Animations**: CSS-only (`animation-timeline` + IntersectionObserver fallback)
- **Sin Tailwind config externo** para animaciones

---

## 3. Design system Nike (referencia principal)

Tokens, lenguaje visual, componentes y mapeo a AtlasCommerce están documentados en `docs/frontend/design-references-nike.md`. Resumen:

- **Backgrounds**: blanco puro (`bg-white`) o gris claro (`bg-nike-gray-100`). **No cream**.
- **Cards**: `bg-white border border-nike-gray-200 rounded-none o rounded-sm`. Sin sombras pesadas.
- **Botones primarios**: `bg-nike-black text-white px-6 py-3 rounded-none font-semibold uppercase tracking-wide`
- **Heroes**: full-bleed con imagen de producto grande, tipografía bold dominante
- **Tipografía**: Inter (open source fallback) o system-ui, jerarquía bold sans-serif
- **Whitespace**: generoso, py-20 a py-32
- **NO decorativos**: cero squiggles, blobs, sunbursts, ilustraciones naïf

### Colores
- `--nike-white: #FFFFFF`
- `--nike-black: #111111`
- `--nike-gray-100/200/400/600: #F5F5F5 / #E5E5E5 / #757575 / #333333`
- `--nike-volt: #CEFF00` (highlight ocasional)
- `--nike-orange: #FA5400` (badges, alerts)

---

## 4. Convenciones de implementación

- **Componentes ≤ 250 líneas** (frontend/AGENTS.md §1)
- **PascalCase** filenames, **camelCase** vars, **UPPER_SNAKE_CASE** constants
- **Props** con `interface Props`, TypeScript strict
- **Tailwind** class order: layout → position → size → spacing → borders → bg → text → effects → responsive
- **HTML semántico**: `<section>`, `<header>`, `<main>`, `<nav>`, `<button>`, `<form>`
- **Sin emojis en código**
- **No usar la utilidad `ring` de Tailwind** para focus (azul permanente feo); usar `outline` con `--nike-black`

---

## 5. Vistas esenciales a diseñar (orden de prioridad)

### Sprint 1 — Home redo + Auth Nike-style (4 vistas)

| # | Vista | Ruta | Tenant | Notas |
|---|---|---|---|---|
| 1 | **Home (redo Nike)** | `/[locale]/` | Multi-tenant (B2C Nike, B2B Bonfire) | Cambia layout a Nike minimalista con imagen grande |
| 2 | **Login cliente** | `/[locale]/account/login` | B2C (Nike) | Auth aesthetic con action imagery |
| 3 | **Signup cliente** | `/[locale]/account/signup` | B2C (Nike) | Multi-step con progress bold |
| 4 | **Mis pedidos Nike** | `/[locale]/account/orders` | B2C (Nike) | Versión Nike de la vista que ya existe con Bonfire |

### Sprint 2 — Catálogo + Producto Nike-style (3 vistas)

| # | Vista | Ruta | Tenant | Notas |
|---|---|---|---|---|
| 5 | **Catálogo Nike** | `/[locale]/products` | B2C | Grid más amplio, sin decorativos, hover image-zoom |
| 6 | **Detalle producto Nike** | `/[locale]/products/[slug]` | B2C | Gallery dominante, sticky ATC mobile, sin blobs |
| 7 | **Carrito Nike** | `/[locale]/cart` | B2C | Lista minimalista, summary simple |

### Sprint 3 — Checkout + Cart + Mis órdenes Nike (3 vistas — reescritura)

| # | Vista | Ruta | Tenant | Notas |
|---|---|---|---|---|
| 8 | **Checkout Nike** | `/[locale]/checkout` | B2C | Stepper más limpio, sin decorativos |
| 9 | **Mis pedidos Nike** | (sprint 1 cubre) | — | — |
| 10 | **Account dashboard Nike** | `/[locale]/account` | B2C | Versión Nike de la vista Bonfire que ya existe |

### Sprint 4 — RFQ + Bulk B2B (mantener Bonfire editorial)

| # | Vista | Ruta | Tenant | Notas |
|---|---|---|---|---|
| 11 | **RFQ Bonfire** | (sprint anterior cubre) | B2B | Mantener Bonfire — denso y confiable |
| 12 | **Bulk order Bonfire** | (sprint anterior cubre) | B2B | Mantener Bonfire — tier comparison |

### Sprint 5+ — Admin views (Bonfire editorial para B2B)

Categorizar admin views en 7-8 prompts Bonfire-style (no Nike). El admin es B2B management y necesita densidad informativa, no minimalismo.

---

## 6. Formato de salida esperado (idéntico a v1)

Para cada vista, la IA generativa debe producir:

1. **Hero image desktop** (1440×900px mínimo, PNG o SVG inline)
2. **Versión mobile** (375×812px, opcional para vistas críticas)
3. **Lista de componentes Astro/React** sugeridos
4. **i18n keys nuevas** que la vista necesita (con texto en es + en)
5. **Tokens CSS custom** que no existen en el design system
6. **Variaciones por tenant** si aplica (Panda sigue Bonfire, B2C sigue Nike)

---

## 7. Lo que NO debe hacer

- NO debe usar el logo de Nike (marca registrada)
- NO debe usar la tipografía Nike Futura ND (no open source; usar Inter)
- NO debe usar el slogan "Just Do It" o copy trademarked de Nike
- NO debe usar fotos de atletas profesionales (necesitamos material propio)
- NO debe usar cream backgrounds tipo Bonfire (Nike es blanco/negro)
- NO debe usar squiggles, blobs, sunbursts, formas orgánicas
- NO debe usar rounded-2xl para cards (Nike es más rectangular)
- NO debe usar sombra azul-fría de 3 capas estilo Bonfire (Nike es plano)

---

## 8. Referencias cruzadas

- `docs/frontend/design-references-bonfire.md` — referencia histórica de sprints previos
- `docs/frontend/design-references-nike.md` — referencia Nike para sprints nuevos
- `odd/tasks/atlascommerce-views-plan.md` — plan de las 10 vistas Bonfire (ya mergeadas)
- `odd/tasks/atlascommerce-prompts-plan.md` — plan multi-prompt actualizado con Nike
- `frontend/AGENTS.md` — convenciones del proyecto

---

**Version**: v2 (2026-10-14) — Nike inspiration
**Reemplaza**: v1 (Bonfire inspiration, archivado)
**Próxima revision**: cuando cambien los tokens Nike o el prompt v3 de la IA externa
