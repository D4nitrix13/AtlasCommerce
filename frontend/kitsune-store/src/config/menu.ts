export const adminMenu = [
  {
    label: "Overview",
    icon: "lucide:layout-dashboard",
    children: [
      { label: "Dashboard", href: "/admin" },
      { label: "Analytics", href: "/admin/reports" },
    ],
  },
  {
    label: "Commerce",
    icon: "lucide:shopping-bag",
    children: [
      { label: "Orders", href: "/admin/sales/orders" },
      { label: "Products", href: "/admin/catalog/products" },
      { label: "Customers", href: "/admin/customers" },
      { label: "Invoices", href: "/admin/sales/invoices" },
    ],
  },
  {
    label: "Catalog",
    icon: "lucide:boxes",
    children: [
      { label: "Categories", href: "/admin/catalog/categories" },
      { label: "Brands", href: "/admin/catalog/brands" },
      { label: "Variants", href: "/admin/catalog/variants" },
    ],
  },
  {
    label: "Inventory",
    icon: "lucide:warehouse",
    children: [
      { label: "Stock", href: "/admin/inventory/stock" },
      { label: "Movements", href: "/admin/inventory/movements" },
      { label: "Warehouses", href: "/admin/inventory/warehouses" },
    ],
  },
  {
    label: "Purchases",
    icon: "lucide:truck",
    children: [
      { label: "Suppliers", href: "/admin/purchases/suppliers" },
      { label: "Purchases", href: "/admin/purchases/orders" },
    ],
  },
  {
    label: "Marketing",
    icon: "lucide:badge-percent",
    children: [
      { label: "Coupons", href: "/admin/marketing/coupons" },
      { label: "Promotions", href: "/admin/marketing/promotions" },
    ],
  },
  {
    label: "Support",
    icon: "lucide:headphones",
    children: [
      { label: "Tickets", href: "/admin/support/tickets" },
      { label: "Reviews", href: "/admin/support/reviews" },
      { label: "Questions", href: "/admin/support/questions" },
    ],
  },
  {
    label: "System",
    icon: "lucide:settings",
    children: [
      { label: "Users", href: "/admin/users" },
      { label: "Roles", href: "/admin/roles" },
      { label: "Audits", href: "/admin/system/audits" },
      { label: "Notifications", href: "/admin/system/notifications" },
      { label: "Settings", href: "/admin/settings" },
    ],
  },
];
