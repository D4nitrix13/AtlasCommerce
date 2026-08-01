export const adminMenu = [
  {
    label: "Resumen",
    children: [
      { label: "Dashboard", href: "/admin" },
      { label: "Analíticas", href: "/admin/reports" },
    ],
  },
  {
    label: "Comercio",
    children: [
      { label: "Órdenes", href: "/admin/sales/orders" },
      { label: "Productos", href: "/admin/catalog/products" },
      { label: "Clientes", href: "/admin/customers" },
      { label: "Facturas", href: "/admin/sales/invoices" },
    ],
  },
  {
    label: "Catálogo",
    children: [
      { label: "Categorías", href: "/admin/catalog/categories" },
      { label: "Marcas", href: "/admin/catalog/brands" },
      { label: "Variantes", href: "/admin/catalog/variants" },
    ],
  },
  {
    label: "Inventario",
    children: [
      { label: "Stock", href: "/admin/inventory/stock" },
      { label: "Movimientos", href: "/admin/inventory/movements" },
      { label: "Depósitos", href: "/admin/inventory/warehouses" },
    ],
  },
  {
    label: "Compras",
    children: [
      { label: "Proveedores", href: "/admin/purchases/suppliers" },
      { label: "Compras", href: "/admin/purchases/orders" },
    ],
  },
  {
    label: "Marketing",
    children: [
      { label: "Cupones", href: "/admin/marketing/coupons" },
      { label: "Promociones", href: "/admin/marketing/promotions" },
    ],
  },
  {
    label: "Soporte",
    children: [
      { label: "Tickets", href: "/admin/support/tickets" },
      { label: "Reseñas", href: "/admin/support/reviews" },
      { label: "Preguntas", href: "/admin/support/questions" },
    ],
  },
  {
    label: "Sistema",
    children: [
      { label: "Usuarios", href: "/admin/users" },
      { label: "Roles", href: "/admin/roles" },
      { label: "Auditoría", href: "/admin/system/audits" },
      { label: "Configuración", href: "/admin/settings" },
    ],
  },
];
