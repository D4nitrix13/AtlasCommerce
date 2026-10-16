# Prompt — Sistema de diseño AtlasCommerce (v1)

> **Propósito**: prompt autocontenido que se le pasa a una IA generativa de imágenes/diseños para producir los visuales de las vistas del storefront. La salida se replica después en código Astro + Tailwind.
>
> **Audiencia del prompt**: una IA generativa (GPT, Claude con image, Midjourney, etc.) que NO tiene contexto previo del proyecto. Cada vista que se le pida debe poder diseñarse solo con este documento.

---

## 1. Contexto del proyecto

**AtlasCommerce** es una plataforma multi-tenant de comercio electrónico multi-instancia. Atiende a cuatro tiendas que comparten la misma base de código pero tienen branding, paletas y experiencias diferenciadas:

| Tenant | Dominio | Modelo | Vertical |
|---|---|---|---|
| **Panda Estampados** | panda-estampados.com | B2B | Estampado textil personalizado para empresas, escuelas, ONGs |
| **Kitsune Store** | kitsune.com | B2C | Merch otaku (figuras, manga, anime, cosplay) |
| **MM Boutique** | mm-boutique.com | B2C | Moda, zapatos, bolsos, mochilas, vestidos, gorras |
| **Morales Store** | morales-store.com | B2C | Retail general, abarrotes, hogar |

Cada tenant tiene su propio `accentColor` y `accentHoverColor` configurados en `frontend/packages/themes/<tenant>/src/index.ts`. Panda usa teal `#2D9D8E`, Kitsune violeta `#7C3AED`, MM Boutique pink `#DB2777`, Morales Store azul `#2563EB`.

## 2. Stack técnico

- **Framework**: Astro 7 con SSR (output: server, adapter: node standalone)
- **Estilos**: Tailwind CSS 4 + CSS custom por componente
- **Interactividad**: React 19 selectivo (para componentes interactivos; resto es Astro puro)
- **TypeScript**: 6 con strict
- **i18n**: soporte para `es` y `en` via `getServerDictionary`
- **Tenant detection**: middleware en `src/middleware.ts` lee el host y setea `Astro.locals.tenant`
- **Auth**: Laravel backend expone endpoints `/api/v1/auth/*`; frontend usa Sanctum tokens
- **Mock data**: `frontend/src/data/products.ts` (B2C), `frontend/src/data/b2b-mock.ts` (B2B), `frontend/src/data/tenants.ts` (per-tenant use cases)
- **Sin librerías de animación** (framer-motion, GSAP, etc.). Animaciones son CSS-only via `animation-timeline` + IntersectionObserver fallback (`src/scripts/scroll-fx.ts`)
- **Sin Tailwind config externo** para animaciones; tokens viven en CSS files individuales

## 3. Sistema de diseño

El proyecto está en proceso de adopción del lenguaje visual de **bonfire.com** (sitio de referencia). La versión completa del design system está preservada en `docs/frontend/design-references-bonfire.md` con tokens, tipografía, sombras, decorativos, prompt para IA de imagen, y mapeo a Panda/Kitsune.

**Tokens clave**:

- **Radius**: 16px (cards/botones), 8px (inner), 999px (pills)
- **Sombras azul-frías** (nunca negras): 3 capas con `rgba(204,219,240,.2)` + `inset 0 0 0 1px rgb(189,202,216)`
- **Tipografía**: Inter para body, hierarchy via Tailwind text-4xl a text-7xl
- **Decorativos Bonfire**: Squiggle (squiggle ámbar), Blob (formas orgánicas), StarBurst (girasoles rotados), ProductMockup (silueta de playera/hoodie/mug/hat), AvatarCircle (iniciales con gradiente), FaceIllustration (4 variantes: artist/creator/school/organizer)
- **Lenguaje editorial**: cream background `#F9F6F1` (mapeado a `bg-slate-50`), whitespace generoso (padding 16-32), sections con `max-w-[1280px]`, headings con tracking apretado
- **Hover states**: `-translate-y-0.5` en cards, `scale-105` en imágenes, sombra elevada

**Colores branded** (NO copiar — son específicos de Bonfire):
- Rust `#951318` (badge "EXPLORE")
- Verde Bonfire `#2B8265` (CTA primary)

En Panda se reemplaza con el accent del tenant: `var(--primary)` que resuelve al teal/violeta/pink/azul según el tenant.

## 4. Convenciones de implementación

- **Componentes ≤ 250 líneas** (frontend/AGENTS.md §1)
- **PascalCase** filenames, **camelCase** vars, **UPPER_SNAKE_CASE** constants
- **Props tipadas** con `interface Props`, TypeScript strict, no `any`
- **HTML semántico**: `<section>`, `<header>`, `<h2>`, `<details>/<summary>` para accordions
- **Decorativos con `aria-hidden="true"` y `pointer-events-none`**
- **Storybook stories** obligatorias para cada componente visual
- **i18n**: cualquier string visible va en `frontend/src/i18n/locales/{es,en}.ts` con key en `TranslationKey` union
- **Tailwind class order**: layout → position → size → spacing → borders → bg → text → effects → responsive
- **No emojis en código**

## 5. Vistas esenciales a diseñar (por orden de prioridad)

Para cada vista, la IA generativa debe producir un screenshot/mockup 1440×900px desktop-first (mobile aparte si aplica) con la siguiente estructura por defecto:

1. **Header sticky blanco** (h-20): logo del tenant a la izquierda, 3-4 items de nav con caret dropdown, search pill centrado, CTA primaria "Cotizar" / "Get started" en `var(--primary)`, "Mi cuenta" + "Carrito" + locale switcher a la derecha
2. **Contenido principal** según la vista
3. **Footer cream** (bg-slate-50): grid 3 columnas (brand | nav links | contact/social), divider con `border-t border-slate-200`, línea legal abajo con copyright + email

### Lista priorizada

| # | Vista | Ruta | Propósito | Tipo de layout |
|---|---|---|---|---|
| 1 | **Login** | `/es/account/login` | Email + password + "¿olvidaste tu contraseña?" + "¿no tenés cuenta?" | Single column centrado, max-w-md |
| 2 | **Signup / Register** | `/es/account/signup` (NO EXISTE — crear) | Form de registro con email + password + nombre + verificar email después | Single column centrado, formulario multi-step opcional |
| 3 | **Account dashboard cliente** | `/es/account` | Saludo, próximas órdenes (3 cards), acciones rápidas (pedir, addresses, ayuda, profile), últimas 5 órdenes (lista resumida) | Multi-section grid, 1-2 columnas desktop |
| 4 | **Lista de productos / catálogo** | `/es/products` | Filtros laterales (categoría, precio, color, talla), grid de 4 cols desktop con paginación, breadcrumbs, sort dropdown | Sidebar izquierda + grid derecha, paginación al fondo |
| 5 | **Detalle de producto** | `/es/products/[slug]` | Galería (carousel thumbnails), título + precio, variant selector (talla/color), descripción, CTA add-to-cart, info de envío, productos relacionados | 2 columnas desktop: gallery izq, info der; tabs para detalles; sticky add-to-cart mobile |
| 6 | **RFQ (Request for Quote) — solo B2B Panda** | `/es/rfq` | Formulario de cotización: tipo de estampado, cantidad, descripción, fecha, contacto; submit genera RFQ con número | Multi-step stepper (1: detalles, 2: contacto, 3: confirmación); 1 columna centrada |
| 7 | **Bulk order — solo B2B Panda** | `/es/bulk-order` | Tiered pricing table (10/25/50/100/500+), selector de cantidad, calculadora de descuento, CTA a "solicitar cotización" | Tabla comparativa con sticky header, columna resaltada para tier popular |
| 8 | **Cart** | `/es/cart` | Lista de items con imagen + título + variant + qty controls, subtotal, impuestos, total, CTA checkout, "continuar comprando" | 2 columnas: items izq, summary der sticky |
| 9 | **Checkout multi-step** | `/es/checkout` | 1: dirección, 2: envío, 3: pago, 4: revisión; progress stepper arriba; resumen sticky a la derecha | Stepper horizontal arriba, 2 cols (form izq, summary sticky der) |
| 10 | **Mis órdenes** | `/es/account/orders` | Lista de órdenes con thumbnail + número + fecha + status pill + total; click expande detalles | Lista vertical con cards colapsables |
| 11 | **Admin dashboard** | `/es/admin` | Stats cards (revenue, orders, customers, conversion), revenue chart, recent orders table, top products, alerts | Multi-grid: KPI cards arriba, charts medio, tablas abajo |
| 12 | **Admin orders list** | `/es/admin/sales/orders` | Filtros (status, fecha, customer), tabla con acciones bulk, exportar CSV | Tabla full-width con filters arriba y paginación al fondo |

## 6. Formato de salida esperado

Para cada vista, la IA generativa debe producir:

1. **Hero image desktop** (1440×900px mínimo, PNG o SVG inline)
2. **Versión mobile** (375×812px, opcional para vistas críticas)
3. **Lista de componentes Astro/React** sugeridos para implementar la vista (basados en la lista de `components/storefront/`, `components/account/`, `components/admin/`)
4. **i18n keys nuevas** que la vista necesita (con texto en es + en)
5. **Tokens CSS custom** que no existen en el design system y habría que agregar (raro, pero posible)
6. **Variaciones por tenant** si la vista se ve diferente (ej. RFQ es B2B solo, login puede ser B2B o B2C)

Si la IA propone algo que rompa las convenciones (radio 16px, cream background, tenant accent en roles CTA), debe justificarlo y pedir aprobación.

## 7. Lo que NO debe hacer

- NO debe usar rust `#951318` (color del logo Bonfire, branded)
- NO debe usar verde Bonfire `#2B8265` en CTAs (mapeado al accent del tenant)
- NO debe usar fotos de personas reales (sustituir con SVG mockups o `AvatarCircle` con iniciales)
- NO debe usar testimonios de clientes con nombres reales (sustituir con sintéticos o `CustomerStories` con mock data)
- NO debe usar logos de marcas reales en el grid de clientes (sustituir con `ClientLogos` con iconos genéricos)
- NO debe asumir que la vista es solo desktop; mobile-first es una convención

## 8. Referencia de implementación

- Documento de design system completo: `docs/frontend/design-references-bonfire.md`
- Plan de sprints previos: `odd/tasks/home-redesign-bonfire-adaptation.md`, `panda-bonfire-complete.md`, `bonfire-visual-fidelity.md`
- Frontend rules: `frontend/AGENTS.md`
- Conventional commits en español: `.claude/skills/conventional-commits-and-prs/SKILL.md`
- Componentes existentes que se reutilizan (NO reimplementar): `frontend/src/components/storefront/{layout,home,b2b,checkout,cart}/`, `frontend/src/components/account/{auth,dashboard,addresses,orders,gdpr,layout}/`, `frontend/src/components/admin/{auth,header,sidebar,catalog,customers,inventory,marketing,reports,sales,settings,shipping,stores,support}/`
- Primitives visuales disponibles: `frontend/src/components/ui/{Squiggle,Blob,ProductMockup,StarBurst,FaceIllustration,AvatarCircle,Icon}.astro`

---

**Versión**: v1 (2026-10-13)
**Próxima revisión**: cuando se cierren las 12 vistas listadas o cambien los tokens del design system.
