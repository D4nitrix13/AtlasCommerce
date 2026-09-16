export const API_ENDPOINTS = {
  auth: {
    login: '/auth/login',
    register: '/auth/register',
    logout: '/auth/logout',
    me: '/auth/me',

    twoFactor: {
      status: '/auth/2fa',

      email: {
        send: '/auth/2fa/email/send',
        verify: '/auth/2fa/email/verify',
      },

      totp: {
        setup: '/auth/2fa/totp/setup',
        verify: '/auth/2fa/totp/verify',
      },

      passkeys: {
        registerOptions: '/auth/2fa/passkeys/register/options',
        register: '/auth/2fa/passkeys/register',
      },

      recoveryCodes: {
        index: '/auth/recovery-codes',
        regenerate: '/auth/recovery-codes/regenerate',
      },

      disable: '/auth/2fa/disable',
    },
  },

  access: {
    users: '/users',
    roles: '/roles',
    permissions: '/permissions',
    permissionRoles: '/permission-roles',
  },

  admin: {
    stores: '/admin/stores',
    storeMaintenance: (id: number | string) => `/admin/stores/${id}/maintenance`,
  },

  customers: {
    customers: '/customers',
    addresses: '/customer-addresses',
  },

  storefront: {
    productSearch: '/storefront/products/search',
    products: '/storefront/products',
    theme: '/storefront/theme',
  },

  catalog: {
    products: '/products',
    categories: '/categories',
    brands: '/brands',
    images: '/product-images',
    variants: '/product-variants',
    attributes: '/product-attributes',
    attributeValues: '/product-attribute-values',
    variantAttributeValues: '/variant-attribute-values',
  },

  inventory: {
    inventories: '/inventories',
    movements: '/inventory-movements',
    warehouses: '/warehouses',
  },

  purchases: {
    suppliers: '/suppliers',
    purchases: '/purchases',
    items: '/purchase-items',
  },

  carts: {
    carts: '/carts',
    items: '/cart-items',
    wishlists: '/wishlists',
    wishlistItems: '/wishlist-items',
  },

  sales: {
    orders: '/orders',
    orderItems: '/order-items',
    orderStatusHistories: '/order-status-histories',

    invoices: '/invoices',
    invoiceItems: '/invoice-items',
    invoiceStatusHistories: '/invoice-status-histories',
  },

  payments: {
    methods: '/payment-methods',
    payments: '/payments',
    invoicePayments: '/invoice-payments',
    installments: '/payment-installments',
    transactions: '/payment-transactions',
  },

  shipping: {
    companies: '/shipping-companies',
    zones: '/shipping-zones',
    shipments: '/shipments',
    trackings: '/shipment-trackings',
  },

  marketing: {
    coupons: '/coupons',
    couponsValidate: '/coupons/validate',
    couponUsages: '/coupon-usages',
    promotions: '/promotions',
    productPromotions: '/product-promotions',
  },

  taxes: {
    taxes: '/taxes',
    productTaxes: '/product-taxes',
  },

  returns: {
    requests: '/return-requests',
    items: '/return-request-items',
    refunds: '/refunds',
  },

  support: {
    tickets: '/support-tickets',
    messages: '/support-ticket-messages',
    reviews: '/product-reviews',
    questions: '/product-questions',
  },

  system: {
    audits: '/audits',
    notifications: '/notifications',
  },

  settings: {
    store: '/store-settings',
    currencies: '/currencies',
    exchangeRates: '/exchange-rates',
  },
} as const;
