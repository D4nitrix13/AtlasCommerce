<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 -->

---
name: astro-frontend-structure
description: Where to put each file in the frontend. UI components vs domain, pages by module, layouts, lib/, stores/, types/. Triggers: creating page, component, layout, store, lib, type, route, deciding location of X.
---

# Astro Frontend Structure

Source: `docs/frontend/estructura.md` (1711 lines).

## Philosophy

The frontend should **NOT** copy exactly the backend table structure.
The frontend represents **screens, views, components, user flows**.
The backend represents **entities, controllers, models, endpoints**.

## Stack

- **Astro** - pages and layouts.
- **React** - interactive components.
- **Tailwind** - styles.
- **TypeScript** - data contracts.
- **Zod** - validation.
- **React Hook Form** - forms.
- **Chart.js** - charts.
- **Laravel API** as backend.

## General structure

```txt
frontend/
├── public/ # files served directly
├── src/
│ ├── assets/ # imported from components
│ ├── components/ # UI + domain
│ ├── config/ # global config
│ ├── data/ # temporary mocks
│ ├── layouts/ # base layouts
│ ├── lib/ # API client, helpers, single source of truth
│ ├── pages/ # Astro real routes
│ ├── stores/ # global state
│ ├── styles/ # global CSS
│ └── types/ # TS types by module
├── astro.config.mjs
├── package.json
├── pnpm-lock.yaml
├── tsconfig.json
└── README.md
```

## `src/components/` - Golden rule

```txt
ui/ ← generic, no business rules
admin/ ← only for /admin
atlas-commerce/ ← purchase flow (cart, checkout)
storefront/ ← public store (header, footer, products)
charts/ ← visualizations
forms/ ← reusable forms
tables/ ← reusable tables
```

### `src/components/ui/` - reusable base

- No business rules.
- Used by admin and storefront.

**Current components:**

```txt
Alert, Badge, Breadcrumb, Button, Card, Dropdown, Icon,
Input, Modal, Pagination, Select, Table, Textarea
```

**Rule:**

```txt
✅ Button.astro, Input.astro, Modal.astro
❌ ProductPaymentStatusCard.astro → its module
❌ InvoiceTotalSummary.astro → its module
❌ CustomerDebtBadge.astro → its module
```

### `src/components/admin/` - only for `/admin`

```txt
admin/
├── cards/ # MetricCard, TopProductsCard
├── dashboard/ # RevenueChart, RecentOrdersTable
├── header/ # AdminHeader
├── modals/
├── reports/
└── sidebar/ # AdminSidebar, AdminSidebarItem
```

> If a component is **only** used inside `/admin`, it lives here.

### `src/components/atlas-commerce/` - purchase flow

```txt
atlas-commerce/cart/ # CartItem, CartSummary
atlas-commerce/checkout/ # CheckoutForm
```

### `src/components/storefront/` - public store

```txt
storefront/header/ # PublicHeader
storefront/footer/ # PublicFooter
storefront/home/ # hero, banners
storefront/products/ # ProductCard, ProductGrid
```

> Do NOT use `components/public/` - confuses with root `public/`.

## `src/config/` - global visual config

```txt
config/menu.ts # admin sidebar
config/site.ts # name, description, metadata
```

> If it's global visual **config**, it goes here. If it's an API endpoint, it goes in `lib/endpoints.ts`.

## `src/data/` - temporary mocks

`data/admin-dashboard.ts` with mock data.

> ⚠️ Don't leave mocks as a final production source - connect to the API.

## `src/layouts/` - page bases

```txt
AdminLayout.astro ← /admin/*
PublicLayout.astro ← public store
AuthLayout.astro ← login, register, recovery
Layout.astro ← general base
```

```astro
---
import AdminLayout from "../../layouts/AdminLayout.astro";
---
<AdminLayout title="Dashboard">
 <h1>Dashboard</h1>
</AdminLayout>
```

## `src/lib/` - key to connect Astro ↔ Laravel

```txt
api.ts # base HTTP client
endpoints.ts # centralized routes
auth.ts # login, logout, register
formatters.ts # formatCurrency, formatDate, formatPercent
storage.ts # localStorage / sessionStorage helpers
```

### `api.ts` - HTTP client

- Define base function to call the API.
- Add common headers.
- Attach Bearer token.
- Handle HTTP errors.
- Process JSON responses.

> `PUBLIC_API_URL` must include the `/api` prefix.
>
> ```env
> PUBLIC_API_URL=http://localhost:9000/api
> ```

### `endpoints.ts` - centralize routes

```ts
// ✅ GOOD - without prefix
export const API_ENDPOINTS = {
 catalog: { products: "/products" },
};

// ❌ BAD - with /api
export const API_ENDPOINTS = {
 catalog: { products: "/api/products" },
};
```

The `/api` prefix comes from `PUBLIC_API_URL`.

### `formatters.ts` - Intl wrappers

```ts
formatCurrency(value) // Intl.NumberFormat("es-NI", { style: "currency" })
formatDate(date)
formatPercent(value)
formatNumber(value)
```

### `storage.ts` - browser storage

- Read/write token.
- Simple preferences.

> Don't save sensitive data unnecessarily.

## `src/pages/` - file-based routing

```txt
src/pages/index.astro → /
src/pages/products/index.astro → /products
src/pages/products/[slug].astro → /products/:slug
src/pages/admin/dashboard/index.astro → /admin/dashboard
```

### `src/pages/auth/` - login, register

Routes: `/auth/login`, `/auth/register`, `/auth/forgot-password`.

> Frontend visuals, **not** equal to API endpoints.

### `src/pages/account/` - authenticated user

```txt
account/index.astro
account/security/index.astro
account/security/two-factor.astro
account/security/recovery-codes.astro
```

2FA belongs to the authenticated user → `account/security/`.

### `src/pages/admin/` - admin panel

```txt
admin/
├── catalog/ # products, categories, brands, variants
├── customers/
├── inventory/ # stock, movements, warehouses
├── purchases/ # purchase-orders, suppliers
├── sales/ # orders, invoices
├── payments/ # methods, installments, transactions
├── shipping/ # companies, zones, shipments
├── marketing/ # coupons, promotions
├── returns/
├── support/ # tickets, reviews, questions
├── settings/ # currencies, store, taxes
└── system/
```

> `/admin/purchases/orders` vs `/admin/sales/orders` are confusing.
> Rename to `/admin/purchases/purchase-orders`.

## `src/stores/` - global state

```txt
auth.ts # user, token, session
cart.ts # cart
ui.ts # sidebar, theme, modals
```

> Don't save in stores data only used by one page.

## `src/styles/` - global CSS

`styles/global.css` - CSS variables, Tailwind base config.

> ⚠️ Avoid page-specific styles if they can be resolved with Tailwind.

## `src/types/` - TS by module

```txt
api.ts, auth.ts, carts.ts, catalog.ts, customers.ts,
inventory.ts, marketing.ts, payments.ts, purchases.ts,
returns.ts, sales.ts, settings.ts, shipping.ts, support.ts,
system.ts
```

> Each module → its own file. Same order as Laravel modules.

## Pages ↔ endpoints relation

```txt
Astro page: Laravel endpoint:
/admin/inventory/stock → /api/inventories
/admin/sales/orders → /api/orders
/admin/settings/taxes → /api/taxes
```

> Names **don't** have to match. What matters is that
> `src/lib/endpoints.ts` centralizes the mapping.

## Recommended number of READMEs

**5-7 documentation files**. Neither more, nor less.

```txt
✅ README.md (root)
✅ DOCUMENTACION.md (root)
✅ src/components/README.md
✅ src/pages/README.md
✅ src/lib/README.md
✅ src/types/README.md
✅ src/stores/README.md

❌ README per each small folder (sidebar/, header/, products/, etc.)
```

Those sections are documented from a higher-up README.

## Path aliases

```ts
// tsconfig.json
{
 "compilerOptions": {
 "paths": {
 "@/*": ["src/*"]
 }
 }
}
```

```ts
// Use
import { apiFetch } from "@/lib/api";
import { useAuth } from "@/stores/auth";
import { LoginForm } from "@/components/admin/auth/LoginForm";
```

## Mandatory UI states

Every component with data must handle:

```astro
{isLoading && <p>Cargando productos...</p>}

{hasError && (
 <p class="text-red-500">No se pudieron cargar los productos.</p>
)}

{!isLoading && products.length === 0 && (
 <p>No hay productos registrados.</p>
)}

{products.length > 0 && (
 <ProductTable products={products} />
)}
```

Minimum states: **loading / empty / error / success / disabled**.

## i18n

```bash
# Add a key
# 1. Edit src/i18n/locales/es.ts
# 2. Edit src/i18n/locales/en.ts
# 3. Verify types with pnpm run typecheck
```

## Trigger patterns

Activate when:

- Creating a new page, layout, component.
- Deciding where a new file lives.
- Creating a new store, lib, type.
- Deciding if a component is UI or domain.
- Renaming / reorganizing files.
- Creating an endpoint on the frontend.

## Anti-patterns

- ❌ `src/components/public/` - confuses with root `public/`.
- ❌ `src/pages/admin/purchases/orders` - confuses with sales/orders.
- ❌ Business component in `src/components/ui/`.
- ❌ Endpoint with `/api` prefix in `endpoints.ts`.
- ❌ Shared/Generic type in `types/` without clear module.
- ❌ Business logic in an `.astro` page.
- ❌ Sensitive data in `storage.ts`.
- ❌ Mock data in `data/` that ends up in production.

## Related skills

- `astro-themes-and-palette` - branding, theme packages, auth palette.
- `conventional-commits-and-prs` - frontend commit format.
- `quality-gate-pre-commit` - checks before commit.
