export const API_ENDPOINTS = {
  auth: {
    login: "/auth/login",
    register: "/auth/register",
    logout: "/auth/logout",
    me: "/auth/me",
  },

  customers: "/customers",
  users: "/users",
  roles: "/roles",
  permissions: "/permissions",

  catalog: {
    products: "/products",
    categories: "/categories",
    brands: "/brands",
    variants: "/product-variants",
    attributes: "/attributes",
  },

  inventory: {
    stock: "/inventory",
    movements: "/inventory-movements",
    warehouses: "/warehouses",
  },

  purchases: {
    suppliers: "/suppliers",
    purchases: "/purchases",
  },

  carts: {
    carts: "/carts",
    wishlists: "/wishlists",
  },

  sales: {
    orders: "/orders",
    invoices: "/invoices",
  },

  payments: {
    methods: "/payment-methods",
    payments: "/payments",
    installments: "/payment-installments",
    transactions: "/payment-transactions",
  },

  shipping: {
    companies: "/shipping-companies",
    zones: "/shipping-zones",
    shipments: "/shipments",
  },

  marketing: {
    coupons: "/coupons",
    promotions: "/promotions",
  },

  returns: "/return-requests",

  support: {
    tickets: "/support-tickets",
    reviews: "/product-reviews",
    questions: "/product-questions",
  },

  system: {
    audits: "/audits",
    notifications: "/notifications",
  },

  settings: {
    store: "/store-settings",
    currencies: "/currencies",
    exchangeRates: "/exchange-rates",
    taxes: "/taxes",
  },
} as const;
