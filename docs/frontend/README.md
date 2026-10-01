# Frontend — Astro 7 + React 19

## Visión general

El frontend es una aplicación construida con **Astro 7** que
maneja tanto la tienda pública (storefront) como el panel
administrativo. Usa **React 19** para componentes interactivos,
**Tailwind CSS 4** para estilos y **TypeScript 6** para tipado
estático.

Un mismo build sirve bajo múltiples dominios. La tienda actual
se resuelve a partir del dominio de origen (ver
[`architecture/flow.md`](../architecture/flow.md
"architecture/flow.md") § 2).

## Stack

| Componente | Versión | Propósito |
| --- | --- | --- |
| `Astro` | 7.x | Framework principal (SSG + Islands) |
| `React` | 19.x | Componentes interactivos |
| `Tailwind CSS` | 4.x | Estilos utility-first |
| `TypeScript` | 6.x | Tipado estático |
| `react-hook-form` | 7.x | Manejo de formularios |
| `Zod` | 4.x | Validación de schemas |
| `Chart.js` | 4.x | Gráficas |
| `Storybook` | 10.x | Catálogo de componentes (UI primitives) |
| `pnpm` | — | Gestor de paquetes |

## Arquitectura de rendering

```txt
Modelo: Híbrido (SSG + Islands)

Página HTML estática (Astro)
  └── Isla React interactiva (client:load / client:visible)
        └── State management (stores/)
              └── API calls (lib/api.ts → Laravel backend)
```

- **Componentes `.astro`** — Renderizados estáticos en build
  time. Sin estado ni interactividad.
- **Componentes `React`** — Para interactividad (formularios,
  carrito, modales). Se importan con `client:*`.
- **Client directives** — `client:visible` > `client:load`
  cuando sea posible (performance).

## Estructura del proyecto

```txt
frontend/
├── src/
│   ├── assets/                     # Imágenes y iconos
│   ├── components/
│   │   ├── ui/                     # 13 UI primitives + ext. React
│   │   ├── storefront/             # Tienda pública (header, footer,
│   │   │                           # products, cart, checkout, home,
│   │   │                           # b2b, search, branding)
│   │   ├── account/                # Sección clientes (dashboard,
│   │   │                           # orders, wishlist, addresses)
│   │   ├── admin/                  # Panel admin (sidebar, header,
│   │   │                           # dashboard, catalog, sales,
│   │   │                           # inventory, marketing, support,
│   │   │                           # settings, customers, ...)
│   │   └── auth/                   # Formularios auth compartidos
│   ├── config/
│   │   ├── menu.ts                 # Menú admin
│   │   └── site.ts                 # Configuración global
│   ├── data/                       # Datos mock (solo dev)
│   ├── i18n/                       # es.json / en.json
│   ├── layouts/
│   │   ├── Layout.astro            # Layout base
│   │   ├── StorefrontLayout.astro  # Tienda pública
│   │   ├── AdminLayout.astro       # Panel admin
│   │   └── AuthLayout.astro        # Login, registro, recovery
│   ├── lib/
│   │   ├── api.ts                  # Cliente HTTP genérico
│   │   ├── endpoints.ts            # Mapa de endpoints API
│   │   ├── auth.ts                 # Lógica auth
│   │   ├── formatters.ts           # Formateo currency/date
│   │   └── i18n.ts                 # Helper de traducciones
│   ├── pages/                      # File-based routing
│   │   ├── index.astro             # redirect a [locale]
│   │   └── [locale]/               # rutas con locale es/en
│   ├── stores/                     # Estado React (cart, ui)
│   ├── styles/
│   │   └── global.css              # Import de Tailwind
│   └── types/                      # 15+ archivos TS por dominio
├── .storybook/                     # Storybook 10 (UI primitives)
├── public/                         # Assets estáticos
├── astro.config.mjs
├── tsconfig.json
├── package.json
└── pnpm-lock.yaml
```

## Rutas principales

Todas las rutas viven bajo `[locale]/` (es/en). A continuación
se listan sin prefijo de locale.

### Tienda pública

| Ruta | Página | Descripción |
| --- | --- | --- |
| `/` | `[locale]/index.astro` | Homepage por tenant |
| `/products` | `[locale]/products/index.astro` | Catálogo |
| `/products/:slug` | `[locale]/products/[slug].astro` | Detalle producto |
| `/cart` | `[locale]/cart.astro` | Carrito |
| `/checkout` | `[locale]/checkout.astro` | Checkout multi-step |
| `/search` | `[locale]/search/index.astro` | Búsqueda |
| `/rfq` | `[locale]/rfq/index.astro` | Panda B2B — Request For Quote |
| `/rfq/confirmation` | `[locale]/rfq/confirmation.astro` | Confirmación RFQ |
| `/bulk-order` | `[locale]/bulk-order/index.astro` | Panda B2B — bulk pricing |

### Autenticación

| Ruta | Página |
| --- | --- |
| `/auth/login` | `[locale]/auth/login.astro` |
| `/auth/signup` | `[locale]/auth/signup.astro` |
| `/auth/forgot-password` | `[locale]/auth/forgot-password.astro` |
| `/auth/reset-password` | `[locale]/auth/reset-password.astro` |
| `/auth/reset-success` | `[locale]/auth/reset-success.astro` |
| `/auth/2fa` | `[locale]/auth/2fa.astro` |

### Cuenta del cliente

| Ruta | Página |
| --- | --- |
| `/account` | `[locale]/account/index.astro` (dashboard icon-based) |
| `/account/orders` | `[locale]/account/orders/index.astro` |
| `/account/orders/[id]` | `[locale]/account/orders/[id].astro` (tracking) |
| `/account/wishlist` | `[locale]/account/wishlist/index.astro` |
| `/account/addresses` | `[locale]/account/addresses/index.astro` (multi-sucursal) |
| `/account/profile` | `[locale]/account/profile/index.astro` |
| `/account/security` | `[locale]/account/security/index.astro` |

### Panel administrativo

| Ruta | Descripción |
| --- | --- |
| `/admin` | Dashboard principal |
| `/admin/dashboard` | Métricas + reportes i18n |
| `/admin/catalog/products` | Listado de productos |
| `/admin/catalog/categories` | Categorías |
| `/admin/catalog/brands` | Marcas |
| `/admin/catalog/variants` | Variantes de producto |
| `/admin/customers` | Clientes |
| `/admin/inventory/stock` | Stock por almacén |
| `/admin/inventory/movements` | Movimientos |
| `/admin/inventory/warehouses` | Almacenes |
| `/admin/sales/orders` | Pedidos |
| `/admin/sales/invoices` | Facturas |
| `/admin/sales/hub` | Centro de ventas |
| `/admin/marketing/coupons` | Cupones |
| `/admin/marketing/promotions` | Promociones |
| `/admin/support/tickets` | Tickets |
| `/admin/support/reviews` | Reseñas |
| `/admin/support/questions` | Preguntas |
| `/admin/settings/store` | Config de tienda |
| `/admin/settings/theme` | Theme editor (color pickers) |
| `/admin/settings/currencies` | Monedas |
| `/admin/settings/taxes` | Impuestos |
| `/admin/users` | Usuarios |
| `/admin/roles` | Roles |
| `/admin/reports` | Reportes |

## Layouts

| Layout | Uso |
| --- | --- |
| `Layout.astro` | Layout base general |
| `StorefrontLayout.astro` | Tienda pública (header + footer) |
| `AdminLayout.astro` | Panel admin (sidebar + header) |
| `AuthLayout.astro` | Login, registro, recovery |

## Componentes UI (`src/components/ui/`)

13 UI primitives sin lógica de negocio. Cada uno trae su
`*.stories.ts` en Storybook 10:

```txt
Alert.astro       Badge.astro       Breadcrumb.astro
Button.astro      Card.astro        Dropdown.astro
Icon.astro        Input.astro       Modal.astro
Pagination.astro  Select.astro      Table.astro
Textarea.astro
```

Extensiones interactivas en `src/components/ui/` (no son `.astro`):

```txt
LanguageSwitcher.tsx       # selector de locale es/en
PasswordInput.tsx          # input password con toggle visibility
PasswordStrengthMeter.tsx  # medidor de fortaleza
SecurityChecklist.tsx      # checklist 2FA / recovery codes
SocialIcons.tsx            # iconos de redes sociales
```

**Regla:** los componentes UI no deben conocer reglas
específicas del negocio. Si necesitan lógica de dominio,
viven en `src/components/{domain}/`.

## Storybook 10

El proyecto incluye **Storybook 10** configurado para Astro +
React. Sirve como catálogo visual de UI primitives y showcase
para componentes de storefront y account.

```bash
cd frontend
pnpm storybook              # dev en http://localhost:6006
pnpm storybook:build        # build estático (storybook-static/)
pnpm storybook:test         # smoke test del build
```

Cobertura actual (23 stories):

- **13 UI primitives** — cada `.astro` con default + edge cases.
- **4 storefront** — `HeroBanner`, `CartItem`, `CartSummary`,
  `ProductGrid`, `ProductCard`.
- **5 account** — `QuickActionCard`, `QuickActionsGrid`,
  `DashboardWelcomeBanner`, `RecentOrdersList`, `WishlistItem`.

Ver [frontend/storybook.md](storybook.md "frontend/storybook.md")
para el flujo completo de trabajo.

## Relación con la API

El frontend consume la API Laravel mediante `src/lib/api.ts`
y `src/lib/endpoints.ts`.

```txt
Frontend → apiFetch(endpoint, options) → API Laravel → PostgreSQL
```

Ver [frontend/api-client.md](api-client.md "Cliente API")
para detalles del cliente API.

## Convenciones

- Componentes: PascalCase (`ProductCard.astro`).
- Variables/funciones: camelCase
  (`totalSales`, `formatCurrency`).
- Constantes: UPPER_SNAKE_CASE (`API_BASE_URL`).
- Booleanos como preguntas (`isOpen`, `hasProducts`).
- Funciones de eventos: `handle` + verbo (`handleSubmit`).
- Orden de clases `Tailwind`: layout → posición → tamaño →
  espaciado → bordes → fondo → texto → efectos → responsive.
- Todo componente UI nuevo trae su `*.stories.ts`.
