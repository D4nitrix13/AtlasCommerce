# Panel Administrativo

## Visión general

El panel administrativo permite gestionar todas las marcas
de la instancia (Panda Estampados + Kitsune en
`panda-estampados-and-kitsune`; single-brand en las otras)
desde una sola interfaz. Está construido con `Astro` + `React`,
usando el `AdminLayout` como estructura base.

Las rutas administrativas se montan bajo `[locale]/admin/`
para soportar i18n es/en (F5.2).

## Acceso

```txt
URL:      admin.panda-estampados.com
          admin.kitsune.com
Layout:   AdminLayout.astro
Auth:     Sanctum Bearer token + cookie de sesión
```

## Estructura del panel

```txt
┌─────────────┬─────────────────────────────────┐
│             │  AdminHeader (search, avatar)   │
│  Sidebar    ├─────────────────────────────────┤
│  (260px)    │  Contenido de la página         │
│  fixed      │                                 │
│             │                                 │
└─────────────┴─────────────────────────────────┘
```

### Sidebar

`AdminSidebar.astro` + `AdminSidebarItem.astro`. Construye
los links con `[locale]` dinámico para soportar i18n
(commit `8d95e5f`). Muestra un badge con la cantidad de
pedidos pendientes del tenant.

Configuración del menú en
[`frontend/src/config/menu.ts`](../../frontend/src/config/menu.ts
"frontend/src/config/menu.ts").

### Header

El header (`AdminHeader.astro`) incluye:

- Búsqueda global
- Acciones rápidas
- Avatar del usuario
- Locale switcher (es/en)

## Módulos del admin

Las páginas viven bajo `[locale]/admin/{sección}/`.

### Dashboard

```txt
/admin/dashboard → dashboard/index.astro
```

Componentes:

- `MetricCard.astro` — 4 cards: Ingresos, Usuarios,
  Órdenes, Visitas.
- `RevenueChart.astro` — Gráfica de línea mensual.
- `TrafficSourcesCard.astro` — Donut chart de tráfico.
- `MonthlyGoalsCard.astro` — Progress bars de metas.
- `RecentOrdersTable.astro` — Tabla de órdenes recientes.
- `RecentActivityCard.astro` — Feed de actividad.
- `LowStockTable.astro` — Productos con stock bajo.

Migrado a i18n (commit `12e9f7f`): 75 strings es/en.

### Catálogo (F5.3)

```txt
/admin/catalog/products      → ProductList.astro
/admin/catalog/categories    → CategoryList.astro
/admin/catalog/brands        → BrandList.astro
/admin/catalog/variants      → VariantList.astro
```

### Clientes

```txt
/admin/customers → CustomerList.astro
```

### Inventario (F5.5)

```txt
/admin/inventory/stock       → StockList.astro
/admin/inventory/movements   → MovementList.astro
/admin/inventory/warehouses  → WarehouseList.astro
```

### Compras

```txt
/admin/purchases/purchase-orders → PurchaseOrderList.astro
/admin/purchases/suppliers       → SupplierList.astro
```

### Ventas (F5.4)

```txt
/admin/sales/orders   → OrderList.astro
/admin/sales/invoices → InvoiceList.astro
/admin/sales/hub      → SalesHub.astro
```

### Pagos

```txt
/admin/payments/methods       → PaymentMethodList.astro
/admin/payments/transactions  → PaymentTransactionList.astro
/admin/payments/installments  → InstallmentList.astro
```

### Envíos

```txt
/admin/shipping/companies → ShippingCompanyList.astro
/admin/shipping/zones     → ShippingZoneList.astro
```

### Marketing (F5.5)

```txt
/admin/marketing/coupons    → CouponList.astro
/admin/marketing/promotions → PromotionList.astro
```

### Soporte

```txt
/admin/support/tickets   → TicketList.astro
/admin/support/reviews   → ReviewList.astro
/admin/support/questions → QuestionList.astro
```

### Configuración (F5.5)

```txt
/admin/settings/store      → StoreSettings.astro
/admin/settings/theme      → ThemeForm.tsx (color pickers + tokens)
Admin/settings/currencies  → CurrencyList.astro
/admin/settings/taxes      → TaxList.astro
```

`ThemeForm` SSR-fetch el theme del store actual vía
`GET /api/admin/theme` y persiste via
`PATCH /api/admin/theme`.

### Sistema

```txt
/admin/users → UserList.astro
/admin/roles → RoleList.astro
```

### Devoluciones

```txt
/admin/returns/requests → ReturnRequestList.astro
```

### Reportes (F5.1)

```txt
/admin/reports → dashboard/index.astro (Reportes)
```

Componentes:

- `RevenueOverviewCard.astro`
- `SalesPerformanceCard.astro`
- `TopProductsCard.astro`
- `CustomerAnalyticsCard.astro`
- `TrafficOverviewCard.astro`

Migrado a i18n (commit `12e9f7f`): 75 strings es/en.

## Filtros por tienda

El admin trabaja en el contexto de un solo `store_id`
activo por request:

1. El backend resuelve el store por dominio vía
   `ResolveStoreFromDomain` middleware.
2. El `user_store` valida que el operador tiene acceso al
   store resuelto.
3. Los queries se filtran automáticamente por `store_id`
   via el global scope `BelongsToStore`.

Cuando la instancia es multi-store (Panda + Kitsune), el
operador con acceso a ambos stores ve ambos contextos
según el subdominio del admin (`admin.panda-estampados.com`
vs `admin.kitsune.com`).

## Estados de interfaz

Todo componente con datos debe manejar los 5 estados
(ver [`frontend/AGENTS.md`](../../frontend/AGENTS.md
"frontend/AGENTS.md") § 11):

```astro
{isLoading && <p>Cargando productos...</p>}
{hasError && (
  <p class="text-red-500">Error al cargar.</p>
)}
{!isLoading && products.length === 0 && (
  <p>No hay productos.</p>
)}
{products.length > 0 && (
  <ProductTable products={products} />
)}
```

## Datos mock

`src/data/admin-dashboard.ts` se mantiene solo para
desarrollo sin API. Las páginas reales hacen SSR-fetch desde
endpoints admin:

```txt
GET    /api/admin/orders/recent
GET    /api/admin/metrics/revenue/yearly
GET    /api/admin/metrics/sales/performance
GET    /api/admin/metrics/traffic/sources
GET    /api/admin/metrics/customers/segments
GET    /api/admin/metrics/products/top
GET    /api/admin/inventory/low-stock
GET    /api/admin/theme
PATCH  /api/admin/theme
GET    /api/admin/customers
GET    /api/admin/products
```

## Pendiente

- CRUDs avanzados (crear/editar con formularios) —
  lectura y listado implementados; formularios de edición
  parcial.
- Bulk actions en tablas — Patrón definido, pendiente
  implementación.
