# Estrategia de Rutas API

## Organización

Todas las rutas API se definen en
`backend/api/routes/api.php`. `Laravel` aplica
automáticamente el prefijo `/api` a las rutas de este
archivo.

```txt
Ruta en api.php:      Route::get('products', ...)
URL completa:         GET /api/products
```

## Rutas protegidas

Las rutas privadas actuales requieren autenticación
`Sanctum`. El middleware `auth:sanctum` se aplica a nivel
de grupo:

```php
Route::middleware('auth:sanctum')->group(function (): void {
    // Todas las rutas aquí requieren Bearer token
});
```

## Rutas públicas (storefront)

Las rutas públicas del storefront (catálogo, productos,
categorías) se definen bajo el prefijo `storefront/`.
Estas rutas resuelven la tienda por dominio y no
requieren autenticación.

```php
Route::prefix('storefront')->group(function (): void {
    Route::get('products', [StorefrontProductController::class, 'index']);
    Route::get('products/{slug}', [StorefrontProductController::class, 'show']);
    Route::get('categories', [StorefrontCategoryController::class, 'index']);
});
```

**Estado:** Base inicial preparada. El controlador
`Storefront/ProductController` existe como estructura
base. Las rutas públicas deben resolver tienda por
dominio. Las rutas administrativas deben usar
`auth:sanctum` y validar `X-Store-Id`.

Debe verificarse en el código la integración
completa con `ResolveStoreFromDomain`.

## Convención de endpoints

Los endpoints siguen convenciones RESTful estándar:

| Acción              | Método    | URI                          |
| ------------------- | --------- | ---------------------------- |
| Listar              | GET       | `/api/{resource}`            |
| Crear               | POST      | `/api/{resource}`            |
| Ver                 | GET       | `/api/{resource}/{id}`       |
| Actualizar          | PUT/PATCH | `/api/{resource}/{id}`       |
| Eliminar            | DELETE    | `/api/{resource}/{id}`       |
| Relaciones anidadas | GET       | `/api/{parent}/{id}/{child}` |

## Módulos y rutas actuales

### Auth

```txt
GET    /api/users                    → UserController@index
POST   /api/users                    → UserController@store
GET    /api/users/{id}               → UserController@show
PUT    /api/users/{id}               → UserController@update
DELETE /api/users/{id}               → UserController@destroy

GET    /api/roles                    → RoleController@index
POST   /api/roles                    → RoleController@store
... (CRUD completo)

GET    /api/permissions              → PermissionController@index
POST   /api/permissions              → PermissionController@store
... (CRUD completo)
```

### 2FA

```txt
GET    /api/auth/2fa                 → TwoFactorAuthController@show
POST   /api/auth/2fa/email/send      → TwoFactorAuthController@sendEmailOtp
POST   /api/auth/2fa/email/verify    → TwoFactorAuthController@verifyEmailOtp
GET    /api/auth/2fa/recovery-codes   → RecoveryCodeController@index
POST   /api/auth/2fa/recovery-codes/regenerate
       → RecoveryCodeController@regenerate
POST   /api/auth/2fa/disable         → TwoFactorAuthController@disable
```

### Catalog

```txt
GET    /api/products                 → ProductController@index
POST   /api/products                 → ProductController@store
GET    /api/products/{id}            → ProductController@show
PUT    /api/products/{id}            → ProductController@update
DELETE /api/products/{id}            → ProductController@destroy

GET    /api/products/{id}/images     → ProductImageController@index
POST   /api/products/{id}/images     → ProductImageController@store
... (CRUD completo)

GET    /api/categories               → CategoryController@index
GET    /api/brands                   → BrandController@index
GET    /api/products/{id}/variants   → ProductVariantController@index
GET    /api/products/{id}/attributes → ProductAttributeController@index
```

### Customers

```txt
GET    /api/customers                → CustomerController@index
POST   /api/customers                → CustomerController@store
GET    /api/customers/{id}/addresses → CustomerAddressController@index
```

### Inventory

```txt
GET    /api/inventories              → InventoryController@index
GET    /api/inventory-movements      → InventoryMovementController@index
GET    /api/warehouses               → WarehouseController@index
```

### Sales

```txt
GET    /api/orders                   → OrderController@index
POST   /api/orders                   → OrderController@store
GET    /api/orders/{id}/items        → OrderItemController@index
GET    /api/orders/{id}/status-histories → OrderStatusHistoryController@index

GET    /api/invoices                 → InvoiceController@index
POST   /api/invoices                 → InvoiceController@store
```

### Payments

```txt
GET    /api/payments                 → PaymentController@index
GET    /api/payment-methods          → PaymentMethodController@index
GET    /api/payment-transactions     → PaymentTransactionController@index
GET    /api/payment-installments     → PaymentInstallmentController@index
GET    /api/invoice-payments         → InvoicePaymentController@index
```

### Marketing

```txt
GET    /api/coupons                  → CouponController@index
GET    /api/promotions               → PromotionController@index
GET    /api/product-promotions       → ProductPromotionController@index
```

### Soporte, Settings, System

```txt
GET    /api/support-tickets          → SupportTicketController@index
GET    /api/product-reviews          → ProductReviewController@index
GET    /api/currencies               → CurrencyController@index
GET    /api/store-settings           → StoreSettingController@index
GET    /api/audits                   → AuditController@index (solo index, show)
GET    /api/notifications
       → NotificationController@index
       (index, show, update)
```

## Recomendaciones

1. **Agrupar rutas por módulo** — Cada módulo tiene su
   sección comentada en `api.php`.
2. **Usar `apiResource`** — Para CRUDs estándar,
   `Route::apiResource()` genera las 7 rutas RESTful.
3. **Versionado futuro** — Si se necesita versionar la
   API, usar prefijo `/api/v1/`.
4. **Rate limiting** — Agregar middleware de rate
   limiting a rutas públicas del storefront.
5. **Filtrado por tienda** — Las rutas del storefront
   deben filtrar por `store_id` automáticamente.
