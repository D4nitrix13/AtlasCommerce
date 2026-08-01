# Frontend — Astro 7 + React 19

## Visión general

El frontend es una aplicación construida con **Astro 7** que
maneja tanto la tienda pública (storefront) como el panel
administrativo. Usa **React 19** para componentes interactivos,
**Tailwind CSS 4** para estilos y **TypeScript 6** para tipado
estático.

Un mismo build sirve bajo múltiples dominios. La tienda actual
se resuelve a partir del dominio de origen.

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
| `pnpm` | — | Gestor de paquetes |

## Arquitectura de rendering

```txt
Modelo: Hybrido (SSG + Islands)

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
frontend/kitsune-store/
├── src/
│   ├── assets/                     # Imágenes y iconos
│   ├── components/
│   │   ├── ui/                     # 13 componentes UI
│   │   ├── storefront/             # Tienda pública
│   │   ├── admin/                  # Panel administrativo
│   │   ├── ecommerce/              # Carrito, checkout
│   │   └── charts/                 # (pendiente)
│   ├── config/
│   │   ├── menu.ts                 # Menú admin (8 secciones)
│   │   └── site.ts                 # Configuración global
│   ├── data/
│   │   └── admin-dashboard.ts      # Datos mock del dashboard
│   ├── layouts/
│   │   ├── Layout.astro            # Layout base
│   │   ├── PublicLayout.astro      # Tienda pública
│   │   ├── AdminLayout.astro       # Panel admin
│   │   └── AuthLayout.astro        # Autenticación
│   ├── lib/
│   │   ├── api.ts                  # Cliente HTTP genérico
│   │   ├── endpoints.ts            # Mapa de endpoints API
│   │   ├── auth.ts                 # (pendiente) Lógica auth
│   │   ├── storage.ts              # (pendiente) Helpers
│   │   └── formatters.ts           # (pendiente) Formateo
│   ├── pages/                      # 56 páginas
│   ├── stores/                     # Estado React global
│   ├── styles/
│   │   └── global.css              # Import de Tailwind
│   └── types/                      # 15 archivos TS
├── public/                         # Assets estáticos
├── astro.config.mjs
├── tsconfig.json
├── package.json
└── pnpm-lock.yaml
```

## Rutas principales

### Tienda pública

| Ruta | Página | Descripción |
| --- | --- | --- |
| `/` | `index.astro` | Homepage |
| `/products` | `products/index.astro` | Catálogo |
| `/products/:slug` | `products/[slug].astro` | Detalle producto |
| `/categories` | `categories/index.astro` | Categorías |
| `/cart` | `cart.astro` | Carrito |
| `/checkout` | `checkout.astro` | Checkout |
| `/orders` | `orders/index.astro` | Pedidos del cliente |

### Autenticación

| Ruta | Página |
| --- | --- |
| `/auth/login` | `auth/login.astro` |
| `/auth/register` | `auth/register.astro` |

### Cuenta

| Ruta | Página |
| --- | --- |
| `/account` | `account/index.astro` |

### Panel administrativo

| Ruta | Descripción |
| --- | --- |
| `/admin` | Dashboard principal |
| `/admin/dashboard` | Dashboard alternativo |
| `/admin/catalog/*` | Productos, categorías, marcas, variantes |
| `/admin/customers` | Clientes |
| `/admin/inventory/*` | Stock, movimientos, almacenes |
| `/admin/purchases/*` | Proveedores, compras |
| `/admin/sales/*` | Órdenes, facturas |
| `/admin/payments/*` | Métodos de pago, cuotas |
| `/admin/shipping/*` | Empresas, zonas de envío |
| `/admin/marketing/*` | Cupones, promociones |
| `/admin/support/*` | Tickets, reseñas, preguntas |
| `/admin/settings/*` | Configuración, monedas, impuestos |
| `/admin/users` | Usuarios |
| `/admin/roles` | Roles |
| `/admin/reports` | Reportes |

## Layouts

| Layout | Uso |
| --- | --- |
| `Layout.astro` | Layout base general |
| `PublicLayout.astro` | Tienda pública (header + footer) |
| `AdminLayout.astro` | Panel admin (sidebar + header) |
| `AuthLayout.astro` | Login, registro |

## Componentes UI (`src/components/ui/`)

13 componentes genéricos reutilizables:

```txt
Alert.astro      Badge.astro      Breadcrumb.astro
Button.astro     Card.astro       Dropdown.astro
Icon.astro       Input.astro      Modal.astro
Pagination.astro Select.astro     Table.astro
Textarea.astro
```

**Regla:** Los componentes UI no deben conocer reglas
específicas del negocio.

## Relación con la API

El frontend consume la API Laravel mediante `src/lib/api.ts`
y `src/lib/endpoints.ts`.

```txt
Frontend → apiFetch(endpoint, options) → API Laravel → PostgreSQL
```

Ver [frontend/api-client.md](api-client.md "Cliente API")
para detalles del cliente API.

## Convenciones

- Componentes: PascalCase (`ProductCard.astro`)
- Variables/funciones: camelCase
  (`totalSales`, `formatCurrency`)
- Constantes: UPPER_SNAKE_CASE (`API_BASE_URL`)
- Booleanos como preguntas (`isOpen`, `hasProducts`)
- Funciones de eventos: `handle` + verbo (`handleSubmit`)
- Orden de clases `Tailwind`: layout → posición → tamaño →
  espaciado → bordes → fondo → texto → efectos → responsive
