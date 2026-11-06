/**
 * Endpoints registry — single source of truth para TODAS las URLs del backend.
 *
 * Cubre 4 grupos de rutas:
 *   - /v1/storefront/* — paginas publicas del cliente (home, product, cart, auth)
 *   - /v1/auth/* — login, logout, me, register, password reset (customer-scoped)
 *   - /v1/account/* — customer logueado (orders, addresses, returns, questions)
 *   - /v1/admin/* — panel administrativo (orders, products, customers, returns, themes, metrics)
 *
 * **Por que es central**: si una URL cambia en backend (version bump, refactor),
 * solo hay que tocar este archivo. Cada componente importa el path desde aca,
 * nunca usa strings hardcoded en sus fetch calls.
 *
 * Ejemplo de uso:
 * ```ts
 * import { API_ENDPOINTS } from '../lib/endpoints';
 * const response = await fetch(API_ENDPOINTS.admin.orders.recent);
 * ```
 */
export const API_ENDPOINTS = {
  auth: {
    login: '/v1/auth/login',
    // CA.2 (customer-auth sprint) — endpoint público del storefront
    // para registrar Customers. Ver backend/tests/Feature/Auth/StorefrontRegisterTest.php.
    register: '/v1/storefront/auth/register',
    logout: '/v1/auth/logout',
    me: '/v1/auth/me',

    forgotPassword: '/v1/auth/forgot-password',
    resetPassword: '/v1/auth/reset-password',
    // 2-step recovery: el usuario verifica un segundo factor (recovery
    // code o TOTP) y obtiene un `challenge_token` de un solo uso para
    // poder llamar a resetPassword.
    recoverViaRecoveryCode: '/v1/auth/recover-via-recovery-code',
    recoverViaTotp: '/v1/auth/recover-via-totp',

    twoFactor: {
      status: '/v1/auth/2fa',

      email: {
        send: '/v1/auth/2fa/email/send',
        verify: '/v1/auth/2fa/email/verify',
      },

      totp: {
        setup: '/v1/auth/2fa/totp/setup',
        verify: '/v1/auth/2fa/totp/verify',
        // Verificación del challenge emitido por /auth/login cuando
        // responde 2fa_required. Distinto del verify autenticado de
        // arriba (gestión del setup): este es público y devuelve el
        // token de sesión.
        verifyLogin: '/v1/auth/2fa/verify-totp-login',
      },

      passkeys: {
        registerOptions: '/v1/auth/2fa/passkeys/register/options',
        register: '/v1/auth/2fa/passkeys/register',
      },

      recoveryCodes: {
        index: '/v1/auth/recovery-codes',
        regenerate: '/v1/auth/recovery-codes/regenerate',
      },

      disable: '/v1/auth/2fa/disable',
    },

    social: {
      redirect: (provider: string): string => `/v1/auth/social/${provider}/redirect`,
      callback: (provider: string): string => `/v1/auth/social/${provider}/callback`,
    },
  },

  access: {
    users: '/v1/users',
    roles: '/v1/roles',
    permissions: '/v1/permissions',
    permissionRoles: '/v1/permission-roles',
  },

  admin: {
    stores: '/v1/admin/stores',
    storeMaintenance: (id: number | string) => `/admin/stores/${id}/maintenance`,
    customers: '/v1/admin/customers',
    customer: (id: number | string) => `/admin/customers/${id}`,
    customerSuspend: (id: number | string) => `/admin/customers/${id}/suspend`,
    customerReactivate: (id: number | string) => `/admin/customers/${id}/reactivate`,
    orders: {
      recent: '/v1/admin/orders/recent',
    },
    catalog: {
      productSizes: '/v1/admin/catalog/product-sizes',
      productSize: (id: number | string) => `/v1/admin/catalog/product-sizes/${id}`,
      productAttributeValues: '/v1/admin/catalog/product-attribute-values',
      productAttributeValue: (id: number | string) =>
        `/v1/admin/catalog/product-attribute-values/${id}`,
      attributes: '/v1/admin/attributes',
      // Imágenes de producto (RF-CAT-022), anidadas bajo el producto.
      productImages: (productId: number | string) =>
        `/v1/admin/catalog/products/${productId}/images`,
      productImage: (productId: number | string, imageId: number | string) =>
        `/v1/admin/catalog/products/${productId}/images/${imageId}`,
      productImagesOrder: (productId: number | string) =>
        `/v1/admin/catalog/products/${productId}/images/order`,
      productImageUpload: (productId: number | string) =>
        `/v1/admin/catalog/products/${productId}/images/upload`,
    },
  },

  customers: {
    customers: '/v1/customers',
    addresses: '/v1/customer-addresses',
    accountAddresses: '/v1/account/addresses',
    accountAddress: (id: number | string) => `/account/addresses/${id}`,
    accountAddressPrimary: (id: number | string) => `/account/addresses/${id}/primary`,
  },

  storefront: {
    productSearch: '/v1/storefront/products/search',
    products: '/v1/storefront/products',
    theme: '/v1/storefront/theme',
    socialLinks: '/v1/storefront/social-links',
  },

  catalog: {
    products: '/v1/products',
    categories: '/v1/categories',
    brands: '/v1/brands',
    images: '/v1/product-images',
    variants: '/v1/product-variants',
    attributes: '/v1/product-attributes',
    attributeValues: '/v1/product-attribute-values',
    variantAttributeValues: '/v1/variant-attribute-values',
  },

  inventory: {
    inventories: '/v1/inventories',
    movements: '/v1/inventory-movements',
    warehouses: '/v1/warehouses',
  },

  purchases: {
    suppliers: '/v1/suppliers',
    purchases: '/v1/purchases',
    items: '/v1/purchase-items',
  },

  carts: {
    carts: '/v1/carts',
    items: '/v1/cart-items',
    wishlists: '/v1/wishlists',
    wishlistItems: '/v1/wishlist-items',
  },

  sales: {
    orders: '/v1/orders',
    orderItems: '/v1/order-items',
    orderStatusHistories: '/v1/order-status-histories',

    invoices: '/v1/invoices',
    invoiceItems: '/v1/invoice-items',
    invoiceStatusHistories: '/v1/invoice-status-histories',
  },

  payments: {
    methods: '/v1/payment-methods',
    payments: '/v1/payments',
    invoicePayments: '/v1/invoice-payments',
    installments: '/v1/payment-installments',
    transactions: '/v1/payment-transactions',
  },

  shipping: {
    companies: '/v1/shipping-companies',
    zones: '/v1/shipping-zones',
    shipments: '/v1/shipments',
    trackings: '/v1/shipment-trackings',
  },

  marketing: {
    coupons: '/v1/coupons',
    couponsValidate: '/v1/coupons/validate',
    couponUsages: '/v1/coupon-usages',
    promotions: '/v1/promotions',
    productPromotions: '/v1/product-promotions',
  },

  taxes: {
    taxes: '/v1/taxes',
    productTaxes: '/v1/product-taxes',
  },

  returns: {
    requests: '/v1/return-requests',
    items: '/v1/return-request-items',
    refunds: '/v1/refunds',
  },

  support: {
    tickets: '/v1/support-tickets',
    messages: '/v1/support-ticket-messages',
    reviews: '/v1/product-reviews',
    questions: '/v1/product-questions',
  },

  system: {
    audits: '/v1/audits',
    notifications: '/v1/notifications',
  },

  settings: {
    store: '/v1/store-settings',
    currencies: '/v1/currencies',
    exchangeRates: '/v1/exchange-rates',
  },
} as const;
