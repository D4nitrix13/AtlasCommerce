# Panel Administrativo

## Visión general

El panel administrativo permite gestionar ambas tiendas
(Panda Estampados y Kitsune) desde una sola interfaz. Está
construido con `Astro` y `React`, usando el `AdminLayout`
como estructura base.

## Acceso

```txt
URL: admin.pandas-estampados.com
Layout: AdminLayout.astro
Autenticación: Requiere login + token Sanctum
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

El sidebar contiene 8 secciones con 22 items:

| Sección        | Items                                     |
| -------------- | ----------------------------------------- |
| **Resumen**    | Dashboard, Analíticas                     |
| **Comercio**   | Órdenes, Productos, Clientes, Facturas    |
| **Catálogo**   | Categorías, Marcas, Variantes             |
| **Inventario** | Stock, Movimientos, Depósitos             |
| **Compras**    | Proveedores, Compras                      |
| **Marketing**  | Cupones, Promociones                      |
| **Soporte**    | Tickets, Reseñas, Preguntas               |
| **Sistema**    | Usuarios, Roles, Auditoría, Configuración |

La configuración del menú está en `src/config/menu.ts`.

### Header

El header incluye:

- Búsqueda global
- Acciones rápidas
- Avatar del usuario
- Selector de tienda (futuro)

## Selector de tienda (futuro)

El panel admin debe permitir cambiar entre tiendas:

```txt
┌───────────────────────────────────────────────┐
│ [Panda Estampados]  notifications  user@email │
└───────────────────────────────────────────────┘
```

El selector envía un header `X-Store-Id` en cada petición
API para filtrar datos por tienda.

**Estado:** No implementado.
Ver [backend/stores/store-context.md](../backend/stores/store-context.md
"Store Context").

## Módulos del admin

### Dashboard

```txt
/admin/dashboard → dashboard/index.astro
```

Componentes:

- `MetricCard.astro` — 4 cards: Ingresos, Usuarios,
  Órdenes, Visitas
- `RevenueChart.astro` — Gráfica de línea mensual
- `TrafficSourcesCard.astro` — Donut chart de tráfico
- `MonthlyGoalsCard.astro` — Progress bars de metas
- `RecentOrdersTable.astro` — Tabla de órdenes recientes
- `RecentActivityCard.astro` — Feed de actividad
- `LowStockTable.astro` — Productos con stock bajo

### Catálogo

```txt
/admin/catalog/products     → CRUD de productos
/admin/catalog/categories   → CRUD de categorías
/admin/catalog/brands       → CRUD de marcas
/admin/catalog/variants     → Variantes de productos
```

### Clientes

```txt
/admin/customers → Listado de clientes
```

### Inventario

```txt
/admin/inventory/stock      → Stock por almacén
/admin/inventory/movements  → Movimientos de inventario
/admin/inventory/warehouses → Almacenes
```

### Compras

```txt
/admin/purchases/purchase-orders → Órdenes de compra
/admin/purchases/suppliers       → Proveedores
```

### Ventas

```txt
/admin/sales/orders   → Pedidos de clientes
/admin/sales/invoices → Facturas
```

### Pagos

```txt
/admin/payments/methods      → Métodos de pago
/admin/payments/installments → Cuotas
```

### Envíos

```txt
/admin/shipping/companies → Empresas de envío
/admin/shipping/zones     → Zonas de envío
```

### Marketing

```txt
/admin/marketing/coupons    → Cupones
/admin/marketing/promotions → Promociones
```

### Soporte

```txt
/admin/support/tickets   → Tickets de soporte
/admin/support/reviews   → Reseñas de productos
/admin/support/questions → Preguntas de productos
```

### Configuración

```txt
/admin/settings/store      → Configuración de tienda
/admin/settings/currencies → Monedas
/admin/settings/taxes      → Impuestos
```

### Sistema

```txt
/admin/users → Gestión de usuarios
/admin/roles → Gestión de roles
```

### Reportes

```txt
/admin/reports → Dashboard de reportes
```

Componentes:

- `RevenueOverviewCard.astro`
- `SalesPerformanceCard.astro`
- `TopProductsCard.astro`
- `CustomerAnalyticsCard.astro`
- `TrafficOverviewCard.astro`

## Filtros por tienda

Cada módulo del admin debe filtrar datos por la tienda
seleccionada. El mecanismo es:

1. Usuario selecciona tienda en el selector.
2. Frontend envía `X-Store-Id` en cada petición API.
3. Backend filtra datos por `store_id`.
4. Frontend muestra solo datos de la tienda seleccionada.

## Estados de interfaz

Todo componente con datos debe manejar los 5 estados:

```astro
{isLoading && <p>Cargando productos...</p>}
{hasError && <p class="text-red-500">Error al cargar.</p>}
{!isLoading && products.length === 0 && (
  <p>No hay productos.</p>
)}
{products.length > 0 && (
  <ProductTable products={products} />
)}
```

## Datos mock

El dashboard actualmente usa datos hardcodeados en
`src/data/admin-dashboard.ts`. Estos datos deben
reemplazarse con llamadas a la API:

```txt
GET /api/orders?store_id={id}
GET /api/customers?store_id={id}
GET /api/products?store_id={id}
GET /api/inventories
```

## Pendiente

- Selector de tienda en header — No implementado
- Conexión con API real — No implementada
- Filtros por tienda — No implementados
- CRUDs funcionales — No implementados
- Formularios con `react-hook-form` + `Zod` —
  No implementados
- Paginación y búsqueda — No implementados
- Estados loading/error/empty —
  Parcialmente implementados
