# Cliente API — Frontend ↔ Backend

## Visión general

El frontend consume la API Laravel mediante dos archivos
principales:

- `src/lib/api.ts` — Cliente HTTP genérico
- `src/lib/endpoints.ts` — Mapa centralizado de endpoints

## src/lib/api.ts

Función principal: `apiFetch<T>(endpoint, options)`

### Características

- Base URL desde `PUBLIC_API_URL`
- Headers: `Accept: application/json`,
  `Content-Type: application/json`
- Autenticación: Bearer token opcional via `options.token`
- Soporte para `FormData` (detecta automáticamente)
- Error handling: lanza `{ status, message, errors }` en
  requests fallidos
- Respuesta 204: retorna `null`
- Content-Type no JSON: retorna `null`

### Uso

```ts
import { apiFetch } from "../lib/api";
import { API_ENDPOINTS } from "../lib/endpoints";

// GET
const products = await apiFetch<Product[]>(
    API_ENDPOINTS.catalog.products
);

// POST
const order = await apiFetch<Order>(
    API_ENDPOINTS.sales.orders,
    {
        method: "POST",
        body: JSON.stringify({
            customer_id: 1,
            items: [...],
        }),
        token: "abc123",
    }
);

// Con store_id
const orders = await apiFetch<Order[]>(
    `${API_ENDPOINTS.sales.orders}?store_id=1`,
    { token: "abc123" }
);
```

### Firma

```ts
async function apiFetch<T>(
    endpoint: string,
    options?: {
        method?: string;
        body?: string | FormData;
        token?: string;
        headers?: Record<string, string>;
    }
): Promise<T | null>
```

## src/lib/endpoints.ts

Mapa centralizado de todos los endpoints de la API backend.

### Autenticación

```txt
API_ENDPOINTS.auth.login    → POST /auth/login
API_ENDPOINTS.auth.register → POST /auth/register
API_ENDPOINTS.auth.logout   → POST /auth/logout
API_ENDPOINTS.auth.me       → GET /auth/me
```

### 2FA

```txt
API_ENDPOINTS.auth.twoFactor.status
    → GET /auth/2fa
API_ENDPOINTS.auth.twoFactor.email.send
    → POST /auth/2fa/email/send
API_ENDPOINTS.auth.twoFactor.email.verify
    → POST /auth/2fa/email/verify
API_ENDPOINTS.auth.twoFactor.recoveryCodes.index
    → GET /auth/2fa/recovery-codes
API_ENDPOINTS.auth.twoFactor.recoveryCodes.regenerate
    → POST /auth/2fa/recovery-codes/regenerate
API_ENDPOINTS.auth.twoFactor.disable
    → POST /auth/2fa/disable
```

### Acceso

```txt
API_ENDPOINTS.access.users       → /users
API_ENDPOINTS.access.roles       → /roles
API_ENDPOINTS.access.permissions → /permissions
```

### Catálogo

```txt
API_ENDPOINTS.catalog.products   → /products
API_ENDPOINTS.catalog.categories → /categories
API_ENDPOINTS.catalog.brands     → /brands
API_ENDPOINTS.catalog.images     → /product-images
API_ENDPOINTS.catalog.variants   → /product-variants
```

### Clientes

```txt
API_ENDPOINTS.customers.customers → /customers
API_ENDPOINTS.customers.addresses → /customer-addresses
```

### Inventario

```txt
API_ENDPOINTS.inventory.inventories → /inventories
API_ENDPOINTS.inventory.movements   → /inventory-movements
API_ENDPOINTS.inventory.warehouses  → /warehouses
```

### Ventas

```txt
API_ENDPOINTS.sales.orders   → /orders
API_ENDPOINTS.sales.invoices → /invoices
```

### Pagos

```txt
API_ENDPOINTS.payments.payments → /payments
API_ENDPOINTS.payments.methods  → /payment-methods
```

### Envíos

```txt
API_ENDPOINTS.shipping.companies → /shipping-companies
API_ENDPOINTS.shipping.zones     → /shipping-zones
API_ENDPOINTS.shipping.shipments → /shipments
```

### Marketing

```txt
API_ENDPOINTS.marketing.coupons    → /coupons
API_ENDPOINTS.marketing.promotions → /promotions
```

### Otros

```txt
API_ENDPOINTS.taxes.taxes          → /taxes
API_ENDPOINTS.returns.requests     → /return-requests
API_ENDPOINTS.support.tickets      → /support-tickets
API_ENDPOINTS.system.audits        → /audits
API_ENDPOINTS.system.notifications → /notifications
API_ENDPOINTS.settings.store       → /store-settings
API_ENDPOINTS.settings.currencies  → /currencies
```

## Variables de entorno

```env
PUBLIC_API_URL=http://localhost:9000/api
```

- `PUBLIC_API_URL` debe incluir el prefijo `/api`
- Los endpoints en `endpoints.ts` NO incluyen `/api`
- El prefijo `/api` viene de la variable de entorno

## Autenticación (endpoints)

El token se almacena en `localStorage` y se adjunta
automáticamente:

```ts
const token = localStorage.getItem("auth_token");

const response = await apiFetch(url, {
    token: token ?? undefined,
});
```

## Manejo de errores

```ts
try {
    const products = await apiFetch<Product[]>(
        API_ENDPOINTS.catalog.products
    );
} catch (error) {
    if (
        error &&
        typeof error === "object" &&
        "status" in error
    ) {
        // Error HTTP
        console.error(error.status, error.message);
    }
}
```

## Convenciones para nuevos endpoints

1. Agregar el endpoint en `src/lib/endpoints.ts` agrupado
   por dominio.
2. Crear el tipo TypeScript correspondiente en
   `src/types/{domain}.ts`.
3. Usar `apiFetch<T>()` con el tipo correcto.
4. No duplicar strings de endpoints — siempre usar
   `API_ENDPOINTS`.
5. Los endpoints públicos del storefront usarán el mismo
   patrón pero sin token.

## Endpoints públicos vs privados

| Tipo | Autenticación | Ejemplo |
| --- | --- | --- |
| Público (storefront) | No requiere | `/products`, `/categories` |
| Privado (admin) | Requiere Bearer token | `/orders`, `/customers` |
| Auth | Login: no; Logout: sí | `/auth/login`, `/auth/logout` |
| 2FA | Requiere Bearer token | `/auth/2fa/email/send` |
