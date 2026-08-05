# Kitsune Store — Documentación del Frontend

## Resumen del Proyecto

Frontend para ecommerce **Kitsune Store** construido con **Astro 6** + **React 19**.
Estilo visual: **Tailwind CSS 4** con tema verde esmeralda.
Gráficas: **Chart.js 4**. Formularios: **react-hook-form** + **Zod 4**.

---

## Stack Tecnológico

| Componente           | Versión  | Propósito                          |
| -------------------- | -------- | ---------------------------------- |
| Astro                | ^6.4.7   | Framework principal (SSG/SSR)      |
| React                | ^19.2.7  | Componentes interactivos (islas)   |
| React DOM            | ^19.2.7  | Renderizado React                  |
| Tailwind CSS         | ^4.3.1   | Framework de estilos utility-first |
| Chart.js             | ^4.5.1   | Gráficas (line, doughnut, bar)     |
| react-hook-form      | ^7.80.0  | Manejo de formularios              |
| Zod                  | ^4.4.3   | Validación de schemas              |
| @iconify-json/lucide | ^1.2.114 | Iconos Lucide via Iconify          |
| TypeScript           | ^6.0.3   | Type checking (devDependency)      |

---

## Estructura del Proyecto

```txt
frontend/
├── src/
│   ├── components/
│   │   ├── ui/                         # Componentes UI genéricos
│   │   │   ├── Alert.astro
│   │   │   ├── Badge.astro
│   │   │   ├── Breadcrumb.astro
│   │   │   ├── Button.astro
│   │   │   ├── Card.astro
│   │   │   ├── Dropdown.astro
│   │   │   ├── Input.astro
│   │   │   ├── Modal.astro
│   │   │   ├── Pagination.astro
│   │   │   ├── Select.astro
│   │   │   ├── Table.astro
│   │   │   └── Textarea.astro
│   │   │
│   │   ├── public/                     # Componentes de la tienda pública
│   │   │   ├── header/
│   │   │   │   └── PublicHeader.astro
│   │   │   ├── footer/
│   │   │   │   └── PublicFooter.astro
│   │   │   └── products/
│   │   │       ├── ProductCard.astro
│   │   │       └── ProductGrid.astro
│   │   │
│   │   ├── ecommerce/                  # Componentes de compra
│   │   │   ├── cart/
│   │   │   │   ├── CartItem.astro
│   │   │   │   └── CartSummary.astro
│   │   │   └── checkout/
│   │   │       └── CheckoutForm.astro
│   │   │
│   │   └── admin/                      # Componentes del panel admin
│   │       ├── header/
│   │       │   └── AdminHeader.astro
│   │       ├── sidebar/
│   │       │   ├── AdminSidebar.astro
│   │       │   └── AdminSidebarItem.astro
│   │       └── dashboard/
│   │           ├── MetricCard.astro
│   │           ├── RevenueChart.astro
│   │           ├── TrafficSourcesCard.astro
│   │           ├── MonthlyGoalsCard.astro
│   │           ├── RecentOrdersTable.astro
│   │           ├── RecentActivityCard.astro
│   │           └── LowStockTable.astro
│   │
│   ├── layouts/
│   │   ├── Layout.astro                # Layout base (tienda pública)
│   │   └── AdminLayout.astro           # Layout del panel admin
│   │
│   ├── pages/                          # Rutas (file-based routing)
│   │   ├── index.astro                 # Homepage
│   │   ├── cart.astro                  # Carrito de compras
│   │   ├── checkout.astro             # Checkout
│   │   ├── auth/
│   │   │   ├── login.astro
│   │   │   └── register.astro
│   │   ├── account/
│   │   │   └── index.astro
│   │   ├── products/
│   │   │   ├── index.astro             # Listado de productos
│   │   │   └── [slug].astro            # Detalle de producto (dinámica)
│   │   ├── categories/
│   │   │   └── index.astro
│   │   ├── orders/
│   │   │   └── index.astro
│   │   └── admin/                      # Panel de administración
│   │       ├── index.astro             # Dashboard principal
│   │       ├── auth/login.astro
│   │       ├── catalog/
│   │       │   ├── products/ (index, create, [id])
│   │       │   ├── categories/
│   │       │   ├── brands/
│   │       │   └── variants/
│   │       ├── customers/
│   │       ├── inventory/ (stock, movements, warehouses)
│   │       ├── marketing/ (coupons, promotions)
│   │       ├── payments/ (methods, installments)
│   │       ├── purchases/ (suppliers, orders)
│   │       ├── sales/ (orders, invoices — con [id] dinámicas)
│   │       ├── shipping/ (companies, zones)
│   │       ├── support/ (tickets, reviews, questions)
│   │       ├── returns/
│   │       ├── roles/
│   │       ├── users/
│   │       ├── reports/
│   │       ├── settings/ (store, currencies, taxes)
│   │       └── system/ (audits, notifications)
│   │
│   ├── lib/                            # Utilidades y helpers
│   │   ├── api.ts                      # Wrapper genérico para fetch API
│   │   ├── endpoints.ts                # Mapa de endpoints de la API
│   │   ├── auth.ts                     # (vacío) Lógica de autenticación
│   │   ├── storage.ts                  # (vacío) Helpers de localStorage
│   │   └── formatters.ts               # (vacío) Formateo de datos
│   │
│   ├── config/
│   │   └── menu.ts                     # Configuración del menú admin
│   │
│   ├── data/
│   │   └── admin-dashboard.ts          # Datos mock del dashboard
│   │
│   ├── stores/                         # Estado global (React)
│   │   ├── auth.ts                     # (vacío) Estado de autenticación
│   │   ├── cart.ts                     # (vacío) Estado del carrito
│   │   └── ui.ts                       # (vacío) Estado de UI
│   │
│   ├── styles/
│   │   └── global.css                  # Import de Tailwind CSS
│   │
│   └── types/                          # Definiciones TypeScript
│       ├── api.ts                      # (vacío)
│       ├── auth.ts                     # (vacío)
│       ├── catalog.ts                  # (vacío)
│       ├── customers.ts                # (vacío)
│       ├── inventory.ts                # (vacío)
│       ├── sales.ts                    # (vacío)
│       ├── payments.ts                 # (vacío)
│       ├── purchases.ts                # (vacío)
│       ├── carts.ts                    # (vacío)
│       ├── shipping.ts                 # (vacío)
│       ├── marketing.ts                # (vacío)
│       ├── returns.ts                  # (vacío)
│       ├── support.ts                  # (vacío)
│       ├── system.ts                   # (vacío)
│       └── settings.ts                 # (vacío)
│
├── public/                             # Assets estáticos
│   ├── favicon.ico
│   └── favicon.svg
│
├── astro.config.mjs                    # Configuración de Astro
├── tsconfig.json                       # Configuración de TypeScript
├── package.json                        # Dependencias
└── pnpm-lock.yaml                      # Lock file (pnpm)
```

---

## Arquitectura

### Rendering: Hybrido (SSG + Islands)

Astro renderiza las páginas como **estáticas por defecto** (SSG). Los componentes React se hidratan como **islas** bajo demanda usando el atributo `client:*`.

```txt
Página HTML estática (Astro)
  └── Isla React interactiva (client:load / client:visible)
        └── State management (Zustand o React context)
              └── API calls (apiFetch)
```

### Routing: File-based

Las rutas se generan automáticamente desde `src/pages/`:

- `src/pages/index.astro` → `/`
- `src/pages/admin/index.astro` → `/admin`
- `src/pages/products/[slug].astro` → `/products/:slug` (dinámica)
- `src/pages/admin/catalog/products/[id].astro` → `/admin/catalog/products/:id`

### Layouts

| Layout              | Uso            | Componentes                       |
| ------------------- | -------------- | --------------------------------- |
| `Layout.astro`      | Tienda pública | Sin sidebar ni header admin       |
| `AdminLayout.astro` | Panel admin    | AdminSidebar + AdminHeader + slot |

### Componentes: Astro vs React

- **Componentes `.astro`**: Renderizado estático en build time. No tienen estado ni interactividad.
- **Componentes React**: Para interactividad (formularios, carrito, modales). Se importan con `client:*`.

---

## Cliente API (`src/lib/api.ts`)

### Uso

```typescript
import { apiFetch } from "../lib/api";
import { API_ENDPOINTS } from "../lib/endpoints";

// GET
const products = await apiFetch<Product[]>(API_ENDPOINTS.catalog.products);

// POST
const order = await apiFetch<Order>(API_ENDPOINTS.sales.orders, {
  method: "POST",
  body: JSON.stringify({ customer_id: 1, items: [...] }),
  token: "abc123",
});

// Con autenticación
const profile = await apiFetch<User>(API_ENDPOINTS.auth.me, {
  token: localStorage.getItem("token"),
});
```

### Comportamiento

- Base URL desde variable de entorno `PUBLIC_API_URL`
- Headers: `Accept: application/json`, `Content-Type: application/json`
- Autenticación: Bearer token opcional
- Error handling: lanza `{ status, message, errors }` en requests fallidos

---

## Endpoints API (`src/lib/endpoints.ts`)

Mapa centralizado de todas las rutas de la API backend:

```typescript
API_ENDPOINTS.auth.login        // POST /auth/login
API_ENDPOINTS.auth.register     // POST /auth/register
API_ENDPOINTS.auth.me           // GET /auth/me
API_ENDPOINTS.catalog.products  // GET/POST /products
API_ENDPOINTS.catalog.categories // GET/POST /categories
API_ENDPOINTS.sales.orders      // GET/POST /orders
API_ENDPOINTS.sales.invoices    // GET/POST /invoices
API_ENDPOINTS.payments.payments // GET/POST /payments
API_ENDPOINTS.settings.taxes    // GET/POST /taxes
// ... etc
```

---

## Dashboard del Admin (`/admin`)

### Layout

```txt
┌─────────────┬──────────────────────────────────────────┐
│             │  AdminHeader (search, actions, avatar)    │
│  Sidebar    ├──────────────────────────────────────────┤
│  (260px)    │  MetricCard × 4                          │
│  fixed      ├────────────────────────┬─────────────────┤
│             │  RevenueChart (2/3)    │ TrafficSources  │
│             │                        │ MonthlyGoals    │
│             ├────────────────────────┼─────────────────┤
│             │  RecentOrdersTable     │ RecentActivity  │
│             │  (2/3)                 │                 │
└─────────────┴────────────────────────┴─────────────────┘
```

### Componentes del Dashboard

| Componente           | Tipo             | Datos     | Descripción                                                      |
| -------------------- | ---------------- | --------- | ---------------------------------------------------------------- |
| `MetricCard`         | Astro (static)   | Props     | 4 cards: Ingresos, Usuarios, Órdenes, Visitas con sparkline SVG  |
| `RevenueChart`       | Astro + Chart.js | Hardcoded | Gráfica de línea mensual (Ingresos/Órdenes/Ganancia)             |
| `TrafficSourcesCard` | Astro + Chart.js | Hardcoded | Donut chart: Directo 35%, Orgánico 28%, Referido 22%, Social 15% |
| `MonthlyGoalsCard`   | Astro (static)   | Hardcoded | 3 progress bars: Ingresos 88%, Clientes 85%, Conversión 76%      |
| `RecentOrdersTable`  | Astro (static)   | Hardcoded | Tabla con 5 órdenes mock                                         |
| `RecentActivityCard` | Astro (static)   | Hardcoded | Feed de 5 actividad reciente                                     |

### Sidebar

8 secciones alineadas al modelo de datos:

| Sección   | Items                                 |
| --------- | ------------------------------------- |
| Overview  | Dashboard, Analytics                  |
| Commerce  | Orders, Products, Customers, Invoices |
| Catalog   | Categories, Brands, Variants          |
| Inventory | Stock, Movements, Warehouses          |
| Purchases | Suppliers, Purchases                  |
| Marketing | Coupons, Promotions                   |
| Support   | Tickets, Reviews, Questions           |
| System    | Users, Roles, Audits, Settings        |

---

## Páginas del Admin (55 páginas)

### Tienda Pública

| Ruta              | Página               |
| ----------------- | -------------------- |
| `/`               | Homepage             |
| `/cart`           | Carrito de compras   |
| `/checkout`       | Checkout             |
| `/auth/login`     | Login                |
| `/auth/register`  | Registro             |
| `/account`        | Mi cuenta            |
| `/products`       | Listado de productos |
| `/products/:slug` | Detalle de producto  |
| `/categories`     | Categorías           |
| `/orders`         | Mis órdenes          |

### Panel Admin

| Ruta                             | Página               |
| -------------------------------- | -------------------- |
| `/admin`                         | Dashboard principal  |
| `/admin/auth/login`              | Login admin          |
| `/admin/catalog/products`        | Listado de productos |
| `/admin/catalog/products/create` | Crear producto       |
| `/admin/catalog/products/:id`    | Editar producto      |
| `/admin/catalog/categories`      | Categorías           |
| `/admin/catalog/brands`          | Marcas               |
| `/admin/catalog/variants`        | Variantes            |
| `/admin/customers`               | Clientes             |
| `/admin/inventory/stock`         | Stock                |
| `/admin/inventory/movements`     | Movimientos          |
| `/admin/inventory/warehouses`    | Almacenes            |
| `/admin/marketing/coupons`       | Cupones              |
| `/admin/marketing/promotions`    | Promociones          |
| `/admin/payments`                | Pagos                |
| `/admin/payments/methods`        | Métodos de pago      |
| `/admin/payments/installments`   | Cuotas               |
| `/admin/purchases/suppliers`     | Proveedores          |
| `/admin/purchases/orders`        | Compras              |
| `/admin/sales/orders`            | Pedidos              |
| `/admin/sales/orders/:id`        | Detalle de pedido    |
| `/admin/sales/invoices`          | Facturas             |
| `/admin/sales/invoices/:id`      | Detalle de factura   |
| `/admin/shipping/companies`      | Empresas de envío    |
| `/admin/shipping/zones`          | Zonas geográficas    |
| `/admin/support/tickets`         | Tickets de soporte   |
| `/admin/support/reviews`         | Reseñas              |
| `/admin/support/questions`       | Preguntas            |
| `/admin/returns`                 | Devoluciones         |
| `/admin/roles`                   | Roles                |
| `/admin/users`                   | Usuarios             |
| `/admin/reports`                 | Reportes/Analytics   |
| `/admin/settings/store`          | Config tienda        |
| `/admin/settings/currencies`     | Monedas              |
| `/admin/settings/taxes`          | Impuestos            |
| `/admin/system/audits`           | Auditoría            |
| `/admin/system/notifications`    | Notificaciones       |

---

## Dependencias

### Producción

| Paquete                | Versión  | Uso                       |
| ---------------------- | -------- | ------------------------- |
| `astro`                | ^6.4.7   | Framework principal       |
| `@astrojs/react`       | ^5.0.7   | Integración React         |
| `react`                | ^19.2.7  | UI interactiva            |
| `react-dom`            | ^19.2.7  | Renderizado React         |
| `tailwindcss`          | ^4.3.1   | Estilos utility-first     |
| `@tailwindcss/vite`    | ^4.3.1   | Plugin Vite para Tailwind |
| `chart.js`             | ^4.5.1   | Gráficas                  |
| `react-hook-form`      | ^7.80.0  | Formularios               |
| `zod`                  | ^4.4.3   | Validación de schemas     |
| `@iconify-json/lucide` | ^1.2.114 | Iconos                    |

### Desarrollo

| Paquete      | Versión | Uso           |
| ------------ | ------- | ------------- |
| `typescript` | ^6.0.3  | Type checking |

---

## Configuración

### `astro.config.mjs`

```js
export default defineConfig({
  vite: {
    plugins: [tailwindcss()]     // Tailwind v4 via Vite plugin
  },
  integrations: [react()]        // Soporte para islas React
});
```

### `tsconfig.json`

```json
{
  "extends": "astro/tsconfigs/strict",
  "compilerOptions": {
    "jsx": "react-jsx",
    "jsxImportSource": "react"
  }
}
```

### Variable de entorno requerida

```env
PUBLIC_API_URL=http://localhost:9000/api   # URL del backend Laravel
```

---

## Conveniones de Código

### Componentes Astro

```astro
---
// Frontmatter: imports, props, lógica del servidor
interface Props {
  title: string;
  accent?: "emerald" | "sky";
}

const { title, accent = "emerald" } = Astro.props;
---

<!-- Template: HTML + Tailwind -->
<article class="rounded-2xl border bg-white p-5">
  <h2>{title}</h2>
</article>

<!-- Script: lógica del cliente (opcional) -->
<script>
  // Client-side JS
</script>
```

### Estilos

- **Tailwind CSS 4** utility-first (no CSS modules)
- Colores: esmeralda (`#059669`) como primary
- Bordes redondeados: `rounded-2xl` para cards, `rounded-xl` para botones/inputs
- Sombras: `shadow-sm` para cards
- Espaciado: `gap-4` / `gap-5` en grids

### TypeScript

- Archivos en `src/types/` organizados por dominio (actualmente vacíos)
- Props tipadas con `interface Props`
- `as const` para objetos de configuración inmutable

---

## Estado Actual del Código

### Completado ✅

- Estructura base de Astro 6 con React
- Layout admin con sidebar, header y responsive
- Dashboard con 6 componentes (MetricCard, RevenueChart, TrafficSourcesCard, MonthlyGoalsCard, RecentOrdersTable, RecentActivityCard)
- Cliente API genérico (`apiFetch`)
- Mapa de endpoints de la API
- Configuración del menú admin
- 55 páginas scaffolded (placeholder)
- 12 componentes UI genéricos (scaffolded)
- Tailwind CSS 4 configurado

### Pendiente 🔲

- Implementar componentes UI (Button, Card, Input, Table, etc.)
- Conectar dashboard con datos reales de la API
- Implementar autenticación (login, registro, token management)
- Implementar formularios con react-hook-form + Zod
- Implementar carrito de compras (estado + API)
- Implementar checkout
- Implementar CRUD de productos (admin)
- Implementar CRUD de pedidos (admin)
- Implementar páginas de detalle ([slug], [id])
- Agregar menú hamburguesa para móvil (sidebar)
- Implementar estados de carga y error
- Agregar paginación y filtros
- Implementar búsqueda
- Agregar notificaciones toast
- Testing con Playwright o Vitest

---

## Comandos

```bash
# Desarrollo
pnpm dev              # Servidor de desarrollo con hot reload

# Build
pnpm build            # Build estático para producción
pnpm preview          # Previsualizar build local

# Tipo checking
pnpm astro check      # Verificar tipos TypeScript
```
