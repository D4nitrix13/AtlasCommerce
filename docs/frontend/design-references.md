# AtlasCommerce — Guía de referencia de diseño UI

> **Propósito**: Este documento es la "fuente de verdad" para que cualquier
> IA que trabaje en el frontend de AtlasCommerce pueda inspirarse en patrones
> de diseño validados (research-based) sin perderse en búsquedas aleatorias.
>
> **Fuente principal recomendada**: **Baymard Institute** — ver §1.
>
> **TL;DR**: Para diseñar cualquier vista del lado cliente, abrí el patrón
> correspondiente en Baymard (§2), analizá los 5 sitios top del benchmark,
> copiá el layout estructural y aplicá los ajustes del nicho (§3) y la
> paleta de AtlasCommerce (§4).

---

## Tabla de contenidos

1. [Contexto del proyecto](#1-contexto-del-proyecto)
2. [Fuente autoritativa: Baymard Institute](#2-fuente-autoritativa-baymard-institute)
3. [Nicho y adaptaciones culturales](#3-nicho-y-adaptaciones-culturales)
4. [Paleta y branding](#4-paleta-y-branding)
5. [Mapa de vistas → URL Baymard](#5-mapa-de-vistas--url-baymard)
6. [Patrones específicos del nicho kitsune/anime](#6-patrones-específicos-del-nicho-kitsuneanime)
7. [Patrones específicos B2B (Panda Estampados)](#7-patrones-específicos-b2b-panda-estampados)
8. [Anti-patrones a evitar](#8-anti-patrones-a-evitar)
9. [Sitios nicho-kit (referencia adicional)](#9-sitios-nicho-kit-referencia-adicional)
10. [Workflow paso a paso para IA](#10-workflow-paso-a-paso-para-ia)
11. [Citas académicas para la tesis](#11-citas-académicas-para-la-tesis)

---

## 1. Contexto del proyecto

**AtlasCommerce** es una plataforma e-commerce **multi-tienda** sobre
Laravel 13 + Astro 7 + React 19 + Tailwind CSS 4 + TypeScript.

### 1.1 Las 4 instancias

| Instancia | Dominio | Tipo | Nicho | Estado para tesis |
|---|---|---|---|---|
| **Panda Estampados** | `panda-estampados.com` | **B2B** | Estampados personalizados, regalos corporativos | ✅ Aplica |
| **Kitsune Store** | `kitsune.com` | **B2C** | Cultura japonesa, anime, manga, figuras, merchandising | ✅ Aplica |
| MM Boutique | `mm-boutique.com` | Single-tenant | — | ❌ No aplica |
| Morales Store | `morales-store.com` | Single-tenant | — | ❌ No aplica |

**Esta guía aplica a Panda Estampados + Kitsune Store.**

### 1.2 Stack frontend

```txt
Astro 7 + React 19 + Tailwind CSS 4 + TypeScript 6 + Zod 4 + react-hook-form + Chart.js 4
```

**Convenciones clave** (ver `frontend/AGENTS.md`):

- Componentes React solo cuando hay interactividad (`.astro` por defecto)
- Una sola responsabilidad por archivo
- Tamaño máx: 150 líneas (simple), 250 (mediano), 400 (página)
- Estados obligatorios: loading · empty · error · success · disabled
- Tailwind ordenado por intención (layout → position → size → spacing → borders → bg → text → effects → responsive)
- i18n obligatorio: `es` y `en`

### 1.3 Estructura del frontend

```txt
frontend/src/
├── pages/
│   ├── [locale]/                 # Rutas con locale
│   │   ├── account/              # ⭐ SECCIÓN CLIENTES
│   │   ├── cart.astro
│   │   ├── checkout
│   │   ├── search/
│   │   └── admin/                # Panel admin
│   ├── account/                  # Legacy
│   ├── cart.astro                # Legacy
│   ├── checkout.astro            # Legacy
│   ├── products/
│   ├── categories/
│   └── orders/
├── components/
│   ├── account/                  # Componentes sección clientes
│   ├── storefront/               # Header, footer, productos
│   ├── admin/                    # Componentes admin
│   ├── auth/                     # Login/signup compartidos
│   └── ui/                       # Genéricos (Button, Modal, Card, etc.)
├── layouts/
├── stores/                       # Zustand stores
├── i18n/
├── lib/
├── hooks/
├── types/
└── styles/
```

---

## 2. Fuente autoritativa: Baymard Institute

### 2.1 Por qué Baymard es LA referencia

- **200,000+ horas** de investigación UX
- **344 sitios top grossing** de ecommerce global benchmarkeados
- **275,000+ UX performance scores** generados
- **71% del Fortune 500 ecommerce** usa sus datos (clientes confirmados:
  Apple, Nike, Etsy, Office Depot, Sephora, Walmart, Macy's, Crate & Barrel,
  B&H Photo, Home Depot, Levi's, Ulta Beauty, Wayfair, IKEA, Google
  Shopping, T-Mobile, Slack, John Lewis, Ōura Ring, TaylorMade,
  Harley-Davidson, Columbia Sportswear, Lenovo, AB InBev)
- Steve Krug (autor de "Don't Make Me Think") dijo: *"Damn. The reports
  that the @Baymard folks do cost money, but they're worth it."*

**NO es opinión, es research-based con 4,400+ sesiones de usability testing.**

### 2.2 URL base

| Categoría | URL |
|---|---|
| **Accounts & Self-Service** (lo que nos interesa) | `https://baymard.com/research/self-service` |
| **Cart & Checkout** | `https://baymard.com/research/checkout-usability` |
| **Product Page** | `https://baymard.com/research/product-page` |
| **Product List** | `https://baymard.com/research/ecommerce-product-lists` |
| **On-Site Search** | `https://baymard.com/research/ecommerce-search` |
| **Mobile Web** | `https://baymard.com/research/mcommerce-usability` |

### 2.3 Galería de design examples (PÚBLICA, sin login)

Las **8 page types de Accounts & Self-Service** que necesitás para sección
clientes, todas en `baymard.com/ecommerce-design-examples/{id}-{slug}`:

| ID | Page Type | Ejemplos | URL |
|---|---|---|---|
| 57 | 'My Account' Drop-Down | 257 | `https://baymard.com/ecommerce-design-examples/57-my-account-drop-down` |
| 58 | **Account Dashboard** | **444** | `https://baymard.com/ecommerce-design-examples/58-account-dashboard` |
| 59 | Address Book | 377 | `https://baymard.com/ecommerce-design-examples/59-address-book` |
| 60 | Stored Credit Cards | 263 | `https://baymard.com/ecommerce-design-examples/60-stored-credit-cards` |
| 62 | Orders Overview | 131 | `https://baymard.com/ecommerce-design-examples/62-orders-overview` |
| 63 | Order Tracking Page | 151 | `https://baymard.com/ecommerce-design-examples/63-order-tracking-page` |
| 64 | Order Returns | 130 | `https://baymard.com/ecommerce-design-examples/64-order-returns` |
| — | Newsletter Management | 291 | `https://baymard.com/ecommerce-design-examples/newsletter-management` |

**Cada ejemplo individual** tiene URL así:
`https://baymard.com/ecommerce-design-examples/58-account-dashboard/{id}-{site}`

Por ejemplo: `https://baymard.com/ecommerce-design-examples/58-account-dashboard/16998-apple`

### 2.4 Top 5 sitios del benchmark (referencias state-of-the-art)

Ordenados por cantidad de anotaciones positivas en el benchmark:

| Rank | Sitio | Patrón fuerte | Mejor para copiar |
|---|---|---|---|
| 🥇 | **Apple** | Sidebar navigation minimalista, jerarquía | Layout estructural |
| 🥇 | **Macy's** | Cards layout, balance info/densidad | Dashboard general |
| 🥇 | **Crate & Barrel** | Cards bien espaciadas, tipografía editorial | Visual craft |
| 🥈 | **Walmart** | Máxima jerarquía de info, shoppers frecuentes | Densidad info |
| 🥈 | **B&H Photo** | Densidad alta bien organizada | Catálogo técnico |
| 🥉 | **Amazon** | Escala + velocidad de carga | Performance |
| 🥉 | **Ulta Beauty** | Loyalty prominent | Beauty tier (no aplica) |
| 🥉 | **Home Depot** | Loyalty programs + recent orders | Recent activity |

### 2.5 Artículos gratuitos de Baymard (citable en tesis)

| Artículo | URL | Insight clave |
|---|---|---|
| 5 Common Pitfalls & Best Practices (2025) | `https://baymard.com/research-articles/current-state-accounts-selfservice` | Estado del arte 2025 |
| Cards Dashboard Layout | `https://baymard.com/blog/cards-dashboard-layout` | Patrón cards |
| Icon-Based Dashboard | `https://baymard.com/research-articles/use-icons-in-the-account-dashboard` | **81% no lo usan — oportunidad** |
| Newsletter Frequency | `https://baymard.com/research-articles/newsletter-frequency` | **80% no permite elegir frecuencia** |
| Account Drop-Down | `https://baymard.com/ecommerce-design-examples/57-my-account-drop-down` | Cómo estructurar el menú "Mi cuenta" |

### 2.6 Insights de Baymard que aplican a AtlasCommerce

```txt
1. "Users are better off with a few somewhat long account pages rather than
    a deep multilayer navigation hierarchy"
   → NO hacer 30 páginas chiquitas. 3-4 páginas largas y bien jerarquizadas.

2. "81% of sites don't use icon-based dashboard"
   → Oportunidad de diferenciación. Usar cards con íconos grandes.

3. "7% of users would never purchase from a particular e-commerce site again,
    solely due to a negative return experience"
   → El self-service de returns/tracking es CRÍTICO para retención.

4. "Returning users are task-oriented, not explorers"
   → El dashboard NO debe tentar a explorar. Sin recomendaciones, sin hero.
     Solo atajos a 6-8 tareas más comunes.

5. "Even on e-commerce sites with predominantly returning users, most users
    should be considered novice first-time users"
   → Asumir que el cliente olvidó la navegación entre visitas.
     Hacer la nav obvia cada vez.
```

---

## 3. Nicho y adaptaciones culturales

### 3.1 Kitsune Store (B2C cultura japonesa)

**Nicho**: anime, manga, figuras coleccionables, merchandising otaku,
cerámica, vidrio, madera, aluminio, regalos temáticos.

**Diferenciadores vs ecommerce genérico**:

- **Edición limitada y drops** (figuras numeradas, pre-order)
- **Fandom culture** — clientes son "fans" de una serie/character, no shoppers genéricos
- **Coleccionismo** — el cliente quiere ver su "wishlist" como colección curada
- **Comunidades** — Discord, Reddit, foros — el sitio puede integrarse
- **Idioma** — muchos términos japoneses (senpai, kawaii, oshi, doujinshi, cosplay)

### 3.2 Adaptaciones culturales obligatorias

| Elemento | Patrón genérico (Baymard) | Adaptación kitsune |
|---|---|---|
| Wishlist | "Saved items" | **"My Collection"** o **"Mochila del Viajero"** (kitsune = zorro viajero) |
| Categorías | Grid horizontal | Tags de género (Shonen, Seinen, Slice of Life, Mecha, Kawaii) |
| Status de orden | Pill gris | Color semántico + icono temático (sakura entrega, etc.) |
| Loyalty | "Reward points" | **"Kitsune Points"** o **"Spirit Fox Coins"** |
| Order tracking | "In transit" | **"En camino al dojo"** (tono cultural, NO abusar) |
| Hero del dashboard | "Welcome, John" | **"おかえりなさい, {name}"** (okaeri = bienvenido de vuelta) |

### 3.3 Cultura otaku: términos a conocer

| Término | Significado | Aplicación UI |
|---|---|---|
| **Oshi (推し)** | El character/artista que apoyás | "Tu oshi" = el favorito del cliente. Mostrar contenido de su oshi en home |
| **Oshi-katsu (推し活)** | Actividad de apoyar al oshi | Categoría de merchandise por personaje |
| **Doujinshi** | Fan-made comics (zona gris legal) | Etiquetar claramente si es oficial vs fan-made |
| **Nendoroid / Figma** | Tipos de figuras (líneas de producto) | Filtrar por línea en el catálogo |
| **Waifu / Husbando** | Character favorito (femenino/masculino) | Easter egg, no usar en copy principal |
| **Senpai / Kohai** | Mayor/menor en jerarquía social | Tono del copy (no abusar) |

### 3.4 Panda Estampados (B2B estampados personalizados)

**Nicho**: estampados personalizados para empresas, regalos corporativos,
merchandising con logo, textiles personalizados.

**Diferenciadores vs B2C**:

- **Cliente recurrente** — una empresa vuelve cada mes con pedidos nuevos
- **RFQ (Request for Quote)** — algunos pedidos requieren cotización previa
- **Aprobación de muestra** — flujo de aprobación de diseño antes de producción
- **Bulk pricing** — precios por volumen, descuentos por escala
- **Account admin** — la empresa tiene varios usuarios (admin, compradores)
- **Factura y NET 30** — pago a 30 días, no Stripe inmediato

**Adaptaciones obligatorias**:

| Elemento | Patrón B2C | Adaptación B2B |
|---|---|---|
| Dashboard | Orders + wishlist | **Pedidos recurrentes + cotizaciones + facturas pendientes** |
| Quick actions | Track, return, reorder | **Reorder bulk + nueva cotización + aprobar diseño** |
| Order list | Recent orders | **Pedidos activos + próximos a vencer pago** |
| Address book | 1-3 addresses | **Multi-sucursal** (sede, almacén, eventos) |
| Pricing | Visible | **Pricing tiers por volumen** (RF-CAT-029 wholesale) |
| Payment | Credit card | **NET 30, transferencia, crédito aprobado** |

---

## 4. Paleta y branding

### 4.1 Variables CSS por instancia

Definidas en `frontend/src/config/stores.ts`:

```ts
// Panda Estampados — Esmeralda (seriedad B2B)
panda-estampados.com: {
  primaryColor: "#059669",
  secondaryColor: "#10b981",
  whatsapp: "+505 8888 1234",
}

// Kitsune Store — Violeta (cultura otaku, misterio)
kitsune.com: {
  primaryColor: "#7c3aed",
  secondaryColor: "#8b5cf6",
  whatsapp: "+505 8888 5678",
}
```

### 4.2 Tokens semánticos (Tailwind)

```css
:root {
  /* Panda */
  --primary: #059669;
  --secondary: #10b981;
  --accent: #f59e0b;     /* dorado para destacar */

  /* Kitsune */
  --primary: #7c3aed;
  --secondary: #8b5cf6;
  --accent: #f472b6;     /* rosa sakura */
}

/* Estado de orden — ejemplo semántico */
:root {
  --status-pending: #fbbf24;    /* amarillo */
  --status-processing: #7c3aed; /* violeta */
  --status-shipped: #06b6d4;    /* cyan (en camino) */
  --status-delivered: #059669;  /* verde (entregado) */
  --status-cancelled: #ef4444;  /* rojo */
}
```

### 4.3 Tipografía

- **Sans-serif body**: Inter (default Tailwind)
- **Display / headings**: Plus Jakarta Sans o Manrope
- **Mono / código**: JetBrains Mono
- **Acento japonés**: Noto Sans JP (para términos japoneses en Kitsune)

---

## 5. Mapa de vistas → URL Baymard

### 5.1 Sección clientes (Account)

Para CADA vista de la sección account, esta es la URL Baymard que abrir
**primero** antes de diseñar:

| Vista AtlasCommerce | URL Baymard | Sitios top a copiar |
|---|---|---|
| `/[locale]/account/login` | Login Baymard: 444 ejemplos account dashboard, mirar `16998-apple` | Apple, Aesop, Glossier |
| `/[locale]/account/signup` | Baymard account config + onboarding | Apple, Mejuri, Bonobos |
| `/[locale]/account/forgot-password` | Baymard account sign in + 2FA | Apple, Glossier |
| `/[locale]/account/2fa` | Baymard account sign in (OTP inputs) | Apple, Google |
| `/[locale]/account/profile` | `baymard.com/ecommerce-design-examples/58-account-dashboard/16998-apple` | Apple, Github |
| `/[locale]/account/security` | Baymard account sign in + 2FA + recovery | Apple, Google, 1Password |
| `/[locale]/account/addresses` | `baymard.com/ecommerce-design-examples/59-address-book` | Mr Porter, Sephora, B&H Photo |
| `/[locale]/account/orders` | `baymard.com/ecommerce-design-examples/62-orders-overview` | Crate & Barrel, Sephora |
| `/[locale]/account/orders/[id]` | `baymard.com/ecommerce-design-examples/63-order-tracking-page` | Apple Shop, Warby Parker |
| `/[locale]/account/wishlist` | Baymard wishlist (no en su core, buscar equivalente) | Mejuri, Mr Porter |
| `/[locale]/account/tickets` | Baymard account sign in + dashboard | Zendesk patterns, Rothy's |
| `/[locale]/account/notifications` | `baymard.com/ecommerce-design-examples/newsletter-management` | Best Made Co. |
| `/[locale]/account/gdpr` | Baymard account dashboard + sign in | Apple, Google |

### 5.2 Storefront público

| Vista AtlasCommerce | URL Baymard | Sitios top |
|---|---|---|
| `/[locale]/` (home) | Baymard homepage category | Apple, Aesop, Mejuri |
| `/[locale]/products` | `baymard.com/ecommerce-design-examples/37-product-list-category` | Apple, Mr Porter |
| `/[locale]/products/[slug]` | `baymard.com/research/product-page` | Apple, Aesop, Mejuri |
| `/[locale]/cart` | `baymard.com/ecommerce-design-examples/added-to-cart-confirmation` + cart | Allbirds, Apple |
| `/[locale]/checkout` | `https://baymard.com/research/checkout-usability` (gold standard) | Allbirds, Apple |
| `/[locale]/search` | `https://baymard.com/research/ecommerce-search` | Apple, Asos |
| `/[locale]/track` (RF-SHIP-083) | `baymard.com/ecommerce-design-examples/63-order-tracking-page` | UPS, Shop App |

### 5.3 Admin panel

| Vista AtlasCommerce | URL Baymard | Sitios top |
|---|---|---|
| `/[locale]/admin/dashboard` | (admin, no en Baymard — usar Linear, Vercel, Stripe) | Stripe Dashboard, Linear |
| `/[locale]/admin/customers` | Baymard address book + orders overview (enfoque admin) | Shopify Admin, Stripe |
| `/[locale]/admin/orders/[id]` | `baymard.com/ecommerce-design-examples/62-orders-overview` | Shopify Admin |

**Nota**: Baymard es research de B2C cliente, no admin. Para admin UI
inspirate en: Stripe Dashboard, Linear, Vercel, Notion, Shopify Admin.

---

## 6. Patrones específicos del nicho kitsune/anime

### 6.1 Cards con íconos grandes para Dashboard (icon-based)

**Insight Baymard**: solo el 19% de los sitios usa icon-based dashboard.
**Oportunidad**: diferenciación fuerte.

**Estructura recomendada**:

```tsx
// frontend/src/components/account/dashboard/QuickActionCard.tsx
import type { ReactNode } from 'react';

interface QuickActionCardProps {
  icon: ReactNode;
  label: string;
  description: string;
  href: string;
  badge?: number; // ej: 3 orders pendientes
  theme?: 'kitsune' | 'panda';
}

export function QuickActionCard({
  icon,
  label,
  description,
  href,
  badge,
  theme = 'kitsune',
}: QuickActionCardProps) {
  return (
    <a
      href={href}
      className="
        group relative flex flex-col items-start gap-3
        rounded-2xl border border-neutral-200 bg-white
        p-6 transition-all
        hover:border-violet-300 hover:shadow-md
      "
    >
      {badge && (
        <span className="
          absolute -right-2 -top-2
          flex h-6 min-w-[24px] items-center justify-center
          rounded-full bg-violet-600 px-2
          text-xs font-semibold text-white
        ">
          {badge}
        </span>
      )}
      <div className="
        flex h-12 w-12 items-center justify-center
        rounded-xl bg-violet-50 text-violet-600
        group-hover:bg-violet-100
      ">
        {icon}
      </div>
      <div className="flex flex-col gap-1">
        <span className="text-base font-semibold text-neutral-900">
          {label}
        </span>
        <span className="text-sm text-neutral-500">
          {description}
        </span>
      </div>
    </a>
  );
}
```

### 6.2 Wishlist como "Colección curada" (no lista plana)

En lugar del patrón genérico "lista de productos guardados", usar el patrón
de Mejuri/Mr Porter: cada item de la wishlist tiene contexto personal.

```txt
Patrón Mejuri aplicado a Kitsune:
┌─────────────────────────────────────────────┐
│  [Imagen figura]                            │
│                                              │
│  Saber Alter — Fate/Stay Night              │
│  $145.00                                    │
│                                              │
│  📅 Agregada hace 2 semanas                 │
│  💬 "Para mi cumpleaños"                    │
│                                              │
│  [ Move to bag ] [ Remove ] [ Notify back ]│
└─────────────────────────────────────────────┘
```

### 6.3 Order tracking con timeline temático

Patrón Apple/Warby Parker pero con tono kitsune sutil:

```txt
Tracking estándar:
  Recibido → Procesando → Enviado → En entrega → Entregado

Tracking Kitsune (opcional, NO abusar):
  📦 Recibido en el dojo
  🎨 Preparando con cuidado
  🚚 En camino al viajero
  ⛩️ Casi en tu santuario
  ✨ Entregado
```

### 6.4 Status pills con color semántico + icono

```tsx
const ORDER_STATUS_STYLES = {
  pending:    { color: 'amber',   icon: '⏳', label: 'Pendiente' },
  processing: { color: 'violet',  icon: '🎨', label: 'Preparando' },
  shipped:    { color: 'cyan',    icon: '🚚', label: 'En camino' },
  delivered:  { color: 'emerald', icon: '✨', label: 'Entregado' },
  cancelled:  { color: 'red',     icon: '✕', label: 'Cancelado' },
} as const;
```

---

## 7. Patrones específicos B2B (Panda Estampados)

### 7.1 Dashboard B2B = jerarquía operativa

Diferencias clave vs dashboard B2C:

```txt
B2C (Kitsune) — "Quick actions + recent activity"
┌──────────────────────────────────────┐
│ [icon] Orders   [icon] Wishlist     │
│ [icon] Profile  [icon] Help         │
│                                      │
│ Recent orders                        │
│ ┌────┐ ┌────┐ ┌────┐               │
│ │ #1 │ │ #2 │ │ #3 │               │
│ └────┘ └────┘ └────┘               │
└──────────────────────────────────────┘

B2B (Panda) — "Action-required + account status"
┌──────────────────────────────────────────────┐
│ ⚠ Pending approval: 3 diseños              │
│ ⚠ Overdue invoice: $2,450 (NET 30 expired) │
│                                              │
│ Active orders: 7  |  Quoted: 2  |  Done: 45│
│                                              │
│ [ New bulk order ] [ Request quote ]        │
│                                              │
│ Recent activity                              │
│ Order #1234 shipped yesterday                │
│ Design approved by client                    │
└──────────────────────────────────────────────┘
```

### 7.2 Tabla B2B con acciones inline

Patrón Shopify Admin / Stripe — tabla densa con filtros y bulk actions:

```tsx
// Características clave:
// - Selección múltiple con checkboxes
// - Bulk actions en top bar
// - Filtros laterales
// - Status pill clickeable
// - Quick view (drawer) sin salir de la lista
// - Export a CSV
```

### 7.3 Cotización vs Orden directa

Panda Estampados tiene flujo de cotización (RFQ) para pedidos custom:

```txt
Flujo RFQ (estándar B2B):
1. Cliente completa formulario RFQ (cantidad, diseño, deadline)
2. Admin genera cotización con precio + plazo
3. Cliente aprueba → se convierte en orden
4. Admin aprueba mockup del diseño
5. Producción
6. Envío + factura NET 30
```

**Patrón UI**: Stepper horizontal persistente en top de la página de la
cotización, con tabs por sección (Diseño / Precios / Plazos / Términos).

---

## 8. Anti-patrones a evitar

### 8.1 Anti-patrones de ecommerce genérico

```txt
❌ NO HACER                              ✅ HACER EN SU LUGAR
─────────────────────────────────────────────────────────────
Hero image gigante en dashboard          Quick actions grid compacto
Carrusel de "productos recomendados"     Recent orders minimalista
Sidebar con 20+ items                    Sidebar con 6-8 items
Welcome popup modal al entrar             Banner sutil no modal
Login con social primero                 Email + password primero, social después
20 campos en signup                      5-7 campos en signup
"Verify your email" bloqueante           Banner no bloqueante
Tracking con 15 milestones               Tracking con 4-5 milestones claros
Newsletter modal cada visita             Footer subscribe, sin modal
Empty state con "0 items"                Empty state ilustrado + CTA
```

### 8.2 Anti-patrones del nicho otaku

```txt
❌ NO HACER                              ✅ HACER EN SU LUGAR
─────────────────────────────────────────────────────────────
Saturar la UI con emoji Japanese         Usar 1-2 acentos sutiles
Tipografía japonesa para TODO el copy    Sans-serif + términos JP en cursiva
Waifu/husbando en copy oficial           "Tu personaje favorito" + nombre
Romaji forzado en URLs (/kawaii-shop)    URLs en inglés/español (/collections/figures)
Lluvia de stickers GIF                   Ilustraciones estáticas sutiles
Estética maximalista tipo neón           Minimalismo con acento violeta
```

### 8.3 Anti-patrones B2B

```txt
❌ NO HACER                              ✅ HACER EN SU LUGAR
─────────────────────────────────────────────────────────────
Pricing oculto ("contact us")            Pricing tiers visibles (RF-CAT-029)
Checkout igual que B2C                   RFQ + checkout custom
Mismo dashboard que cliente B2C          Dashboard operativo con action items
Soporte solo por email                   Phone + WhatsApp + email + ticket
Factura por separado manual              Factura auto-generada post-pago
Sin NET 30                               NET 30 para clientes aprobados
Aprobación de diseño por email           Aprobación in-app con preview
```

---

## 9. Sitios nicho-kit (referencia adicional)

### 9.1 Sitios otaku/anime/manga

| Sitio | URL | Por qué sirve |
|---|---|---|
| **HobbyLink Japan** | `hlj.com` | "Private Warehouse" = almacenar compras hasta consolidación. Patrón único para pre-order y drops |
| **AmiAmi** | `amiami.com` | Gold standard de figuras japonesas. Pre-order calendar, restock alerts |
| **Tokyo Otaku Mode** | `otakumode.com` | UI minimalista, comunidad de fans, drops temáticos |
| **CDJapan** | `cdjapan.co.jp` | Combina anime merch + cultura pop. Wishlist con "Notify when in stock" |
| **Crunchyroll Store** | `store.crunchyroll.com` | Cross-sell con streaming subscription |
| **RightStuf (ahora Crunchyroll)** | (deprecated) | Categorización avanzada por franquicia |
| **Etsy (anime merch)** | `etsy.com/c/anime-merchandise` | Hand-made + vintage + custom. Comunidad activa |

### 9.2 Sitios estampados personalizados B2B

| Sitio | URL | Por qué sirve |
|---|---|---|
| **Custom Ink** | `customink.com` | Gold standard B2B de estampados. Design lab + group ordering |
| **Vistaprint** | `vistaprint.com` | Plantillas + custom. Business cards + merch |
| **Printful** | `printful.com` | Mockup generator + integración con tiendas |
| **Printify** | `printify.com` | Catálogo de productos + red de printers |
| **Spreadshirt** | `spreadshirt.com` | Marketplace de diseños de usuarios |
| **Teespring (Spring)** | `spring.com` | Creator-led merch + drops |

### 9.3 Sitios para crear cuenta rápido y ver dashboards

Si necesitás ver dashboards en vivo (Baymard son screenshots, no live):

| Sitio | Tiempo | Cómo llegar al dashboard |
|---|---|---|
| **Etsy** | 30 seg | Google sign-in → ves wishlist, orders, messages |
| **Sephora Beauty Insider** | 1 min | Login social → loyalty tier + orders + quiz |
| **IKEA Family** | 1 min | Email signup → ves pedidos + lista |
| **AliExpress** | 30 seg | Login social → orders + wishlist + coins |
| **Notion / Linear** | 1 min | Google sign-in → settings UI (no ecommerce pero el mejor settings UI del mundo) |
| **GitHub** | 30 seg | GitHub sign-in → settings tab → billing tab |

---

## 10. Workflow paso a paso para IA

### 10.1 Cuando te pidan implementar una vista de la sección clientes

```txt
1. IDENTIFICAR LA VISTA
   → Buscá en §5.1 la URL Baymard correspondiente a la vista

2. ABRIR BAYMARD
   → Navegá a la URL y mirá los 5 sitios top del benchmark
   → Capturá 2-3 screenshots de referencia

3. ANALIZAR EL NICHO
   → ¿Es Kitsune (B2C otaku) o Panda (B2B estampados)?
   → Aplicá adaptaciones de §3 o §7

4. LEER INSIGHTS DE BAYMARD
   → ¿Qué patrones "violation" evitar? (sección de cada ejemplo)
   → ¿Qué patrones "adherence" copiar? (sección de cada ejemplo)

5. DIBUJAR WIREFRAME
   → Layout: sidebar (si es dashboard) o single-col (si es auth/address)
   → Cards con íconos para quick actions (§6.1)
   → Tabla densa con filtros (si es lista de órdenes)

6. APLICAR BRANDING
   → Panda: primary #059669 (emerald)
   → Kitsune: primary #7c3aed (violet)
   → Tipografía: Inter + Noto Sans JP (Kitsune solo)

7. IMPLEMENTAR SIGUIENDO AGENTS.MD
   → Astro page + React island para interactivos
   → Estados: loading, empty, error, success, disabled
   → i18n: español + inglés
   → Tamaño máx 400 líneas (página) / 250 (componente mediano)

8. VALIDAR CONTRA §8
   → ¿Caíste en algún anti-pattern?
   → ¿El dashboard es task-oriented o intenta vender?
```

### 10.2 Cuando te pidan diseñar algo desde cero

```txt
1. PREGUNTAR: ¿qué vista es? ¿Qué instancia (Kitsune/Panda)?
2. BUSCAR en §5 la URL Baymard correspondiente
3. Si no hay match exacto en Baymard, buscar en §9 sitios nicho-kit
4. Si tampoco hay match, documentar el gap y crear patrón propio
5. Aplicar el workflow de §10.1 desde paso 5
```

### 10.3 Cuando te pidan comparar contra un competidor

```txt
1. Pedir el nombre del competidor
2. Verificar si está en el benchmark de Baymard:
   → Apple, Macy's, Walmart, Crate & Barrel, B&H Photo, Home Depot,
     Lowe's, Amazon, Ulta Beauty, IKEA, Wayfair, Etsy, Best Buy,
     Sephora, Target, Costco, REI, Chewy, Patagonia, North Face
3. Si está, abrir su ejemplo en Baymard:
   `baymard.com/ecommerce-design-examples/58-account-dashboard/{id}-{site}`
4. Si no está, buscar en §9 por nicho similar
```

---

## 11. Citas académicas para la tesis

### 11.1 Cita Baymard (formato APA 7)

> Baymard Institute. (2025). *Accounts & Self-Service UX: 5 Common
> Pitfalls & Best Practices*. Retrieved from
> https://baymard.com/research-articles/current-state-accounts-selfservice

### 11.2 Cita sobre el 81% icon-based

> Baymard Institute. (2022). *Accounts & Self-Service UX: Consider
> Having an "Icon-Based" Dashboard (81% Don't)*. Retrieved from
> https://baymard.com/research-articles/use-icons-in-the-account-dashboard

### 11.3 Cita sobre long pages vs short pages

> Baymard Institute. (2025). *Account Dashboard Design Guidelines*.
> Baymard Premium Research. Retrieved from
> https://baymard.com/ecommerce-design-examples/58-account-dashboard

### 11.4 Cita sobre returns impact

> Baymard Institute. (2024). *Order Tracking & Returns UX Benchmark*.
> Retrieved from
> https://baymard.com/research-articles/2024-benchmark-order-tracking-and-returns

### 11.5 Frase para defender decisiones de diseño en el tribunal

> "El diseño de la sección de clientes de AtlasCommerce se basa en la
> guía de 'Accounts & Self-Service UX' de Baymard Institute, utilizada
> por el 71% del Fortune 500 ecommerce y validada con 4,400+ sesiones
> de usability testing. Particularmente, aplicamos el principio de
> 'few long pages rather than deep multilayer navigation' y el patrón
> 'icon-based dashboard' (diferenciador: solo el 19% de los sitios
> top lo implementan)."

---

## 12. Apéndice — Recursos extra

### 12.1 Sitios para ver screenshots sin login

| Recurso | URL | Uso |
|---|---|---|
| **Baymard Institute** | `baymard.com/ecommerce-design-examples/` | Source of truth, 18,000+ ejemplos |
| **ecomm.design** | `ecomm.design` | 4,061 sitios ecommerce catalogados |
| **Godly** | `godly.website` | Sitios web bien diseñados (no ecommerce) |
| **Really Good UX** | `reallygoodux.io` | Onboarding flows + screenshots |
| **Mobbin** | `mobbin.com` | Patrones UI mobile/web (signup free) |

### 12.2 Newsletters para seguir

- Baymard Weekly (UX research) — `baymard.com`
- UX Collective — `uxdesign.cc`
- Smashing Magazine — `smashingmagazine.com`
- Econsultancy — `econsultancy.com`
- Charlotte Lytton / Baymard Premium (pago)

### 12.3 Libros de referencia en la colección

- Steve Krug — *Don't Make Me Think* (mencionado por Baymard)
- Don Norman — *The Design of Everyday Things*
- Luke Wroblewski — *Mobile First* y *Web Form Design*
- Jared Spool — *Web Usability* (research pioneer)
- Erika Hall — *Conversational Design*

---

**Última actualización**: 2026-09-21
**Mantenedor**: Equipo frontend AtlasCommerce
**Próxima revisión**: cuando se implemente una vista nueva de sección clientes
