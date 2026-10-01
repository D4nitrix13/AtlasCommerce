# Estrategia de Rutas API

## Organización

Todas las rutas API se definen en
[`backend/routes/api.php`](../../backend/routes/api.php
"backend/routes/api.php") (979 líneas, 164 rutas
registradas a 2026-09-30). `Laravel` aplica automáticamente
el prefijo `/api` a las rutas de este archivo.

```txt
Ruta en api.php:      Route::get('products', ...)
URL completa:         GET /api/products
```

> El prefijo `/api/v1/` documentado en otros lugares se
> aplica en `bootstrap/app.php` (RNF-MAIN-054, ver
> [`docs/api/versioning.md`](../api/versioning.md "api/versioning.md")).
> Las rutas del archivo se llaman sin el segmento `v1` y
> Laravel lo agrega según la configuración de instancia.

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
categorías, theme, assets) están registradas **sin**
`auth:sanctum`. Resuelven la tienda por dominio vía el
middleware `ResolveStoreFromDomain`:

```php
Route::prefix('storefront')->group(...);
Route::get('/assets/{path}', [StorefrontAssetController::class, 'show']);
Route::get('/tracking/{tracking_code}', [TrackingController::class, 'show']);
Route::get('/products/{product}/reviews', [ProductReviewController::class, 'indexForProduct']);
Route::get('/products/{product}/questions', [ProductQuestionController::class, 'indexForProduct']);
```

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

## Módulos y rutas

A continuación, el mapa de rutas registradas en
`backend/routes/api.php` agrupado por dominio. Los nombres
siguen la convención Laravel (`singular.plural.action`).

### Auth — login, logout, password

```txt
POST   /api/auth/login                  → AuthController@login
POST   /api/auth/forgot-password        → PasswordResetTokenController@forgot
POST   /api/auth/reset-password         → PasswordResetTokenController@reset
POST   /api/auth/recover-via-recovery-code
                                      → PasswordResetTokenController@recoverWithRecoveryCode
POST   /api/auth/recover-via-totp       → PasswordResetTokenController@recoverWithTotp
GET    /api/auth/social/{provider}/redirect
                                      → SocialAuthController@redirect
GET    /api/auth/social/{provider}/callback
                                      → SocialAuthController@callback
POST   /api/auth/2fa/verify-totp-login  → AuthController@verifyTotpLogin
POST   /api/auth/login/email-otp/request → EmailOtpController@request
POST   /api/auth/login/email-otp/verify  → EmailOtpController@verify
POST   /api/auth/logout                 → AuthController@logout
GET    /api/auth/me                     → AuthController@me
```

### 2FA — TOTP, recovery codes

```txt
GET    /api/auth/2fa/totp               → TotpController@status
POST   /api/auth/2fa/totp/setup         → TotpController@setup
POST   /api/auth/2fa/totp/verify        → TotpController@verify
DELETE /api/auth/2fa/totp               → TotpController@destroy
GET    /api/auth/recovery-codes         → RecoveryCodeController@index
POST   /api/auth/recovery-codes/regenerate
                                      → RecoveryCodeController@regenerate
```

### Account — datos del cliente autenticado

```txt
GET    /api/account/data-export         → AccountDataController@export
POST   /api/account/deletion-request    → AccountDataController@requestDeletion
DELETE /api/account                     → AccountDataController@destroy
POST   /api/account/deletion-cancel     → AccountDataController@cancelDeletion
GET    /api/account/addresses           → CustomerAddressController@index
POST   /api/account/addresses           → CustomerAddressController@store
GET    /api/account/addresses/{address} → CustomerAddressController@show
PUT    /api/account/addresses/{address} → CustomerAddressController@update
DELETE /api/account/addresses/{address} → CustomerAddressController@destroy
POST   /api/account/addresses/{address}/primary
                                      → CustomerAddressController@setPrimary
GET    /api/account/wishlists           → WishlistController@index
POST   /api/account/wishlists           → WishlistController@store
GET    /api/account/wishlists/{wishlist} → WishlistController@show
DELETE /api/account/wishlists/{wishlist} → WishlistController@destroy
POST   /api/account/wishlists/{wishlist}/items
                                      → WishlistController@addItem
DELETE /api/account/wishlists/{wishlist}/items/{item}
                                      → WishlistController@removeItem
GET    /api/account/reviews             → ProductReviewController@indexForCustomer
GET    /api/account/questions           → ProductQuestionController@indexForCustomer
```

### Catalog — productos, categorías, marcas, variantes

```txt
GET    /api/products/{product}/reviews  → ProductReviewController@indexForProduct
GET    /api/products/{product}/questions → ProductQuestionController@indexForProduct
GET    /api/attributes                  → AttributeController@index
GET    /api/attributes/{attribute}      → AttributeController@show
DELETE /api/attributes/{attribute}      → AttributeController@destroy
GET    /api/attribute-values            → ProductAttributeValueController@index
GET    /api/attribute-values/{attributeValue}
                                      → ProductAttributeValueController@show
DELETE /api/attribute-values/{attributeValue}
                                      → ProductAttributeValueController@destroy
POST   /api/brands                      → BrandController@store
GET    /api/brands/{brand}              → BrandController@show
PUT    /api/brands/{brand}              → BrandController@update
DELETE /api/brands/{brand}              → BrandController@destroy
POST   /api/categories                  → CategoryController@store
GET    /api/categories/{category}       → CategoryController@show
PUT    /api/categories/{category}       → CategoryController@update
DELETE /api/categories/{category}       → CategoryController@destroy
DELETE /api/products/{product}/images/{image}
                                      → ProductImageController@destroy
POST   /api/products/{product}/images   → ProductImageController@store
POST   /api/products                    → ProductController@store
GET    /api/products/{product}          → ProductController@show
PUT    /api/products/{product}          → ProductController@update
DELETE /api/products/{product}          → ProductController@destroy
POST   /api/variants                    → ProductVariantController@store
GET    /api/variants/{variant}          → ProductVariantController@show
PUT    /api/variants/{variant}          → ProductVariantController@update
DELETE /api/variants/{variant}          → ProductVariantController@destroy
POST   /api/wholesale-prices            → WholesalePriceController@store
DELETE /api/wholesale-prices/{wholesalePrice}
                                      → WholesalePriceController@destroy
```

### Carts, Orders, Invoices, Returns, Shipments, Refunds

CRUDs estándar con `Route::apiResource()`:

```txt
apiResource('carts', CartController)
apiResource('carts.items', CartItemController)
apiResource('orders', OrderController)
apiResource('payments', PaymentController)
apiResource('refunds', RefundController)
apiResource('return-requests', ReturnRequestController)
apiResource('return-request-items', ReturnRequestItemController)
apiResource('shipments', ShipmentController)
```

### Coupons, Promotions, Wholesale

```txt
POST   /api/coupons                     → CouponController@store
GET    /api/coupons/{coupon}            → CouponController@show
PUT    /api/coupons/{coupon}            → CouponController@update
DELETE /api/coupons/{coupon}            → CouponController@destroy
POST   /api/promotions                  → PromotionController@store
GET    /api/promotions/{promotion}      → PromotionController@show
PUT    /api/promotions/{promotion}      → PromotionController@update
DELETE /api/promotions/{promotion}      → PromotionController@destroy
POST   /api/promotions/{promotion}/products
                                      → PromotionProductController@store
```

### Customers, Reviews, Questions, Support Tickets

```txt
POST   /api/customers                   → CustomerController@store
GET    /api/customers/{customer}        → CustomerController@show
PUT    /api/customers/{customer}        → CustomerController@update
DELETE /api/customers/{customer}        → CustomerController@destroy
POST   /api/reviews                     → AdminProductReviewController@store
DELETE /api/reviews/{review}            → AdminProductReviewController@destroy
POST   /api/support-tickets             → AdminSupportTicketController@store
GET    /api/support-tickets/{supportTicket}
                                      → AdminSupportTicketController@show
```

### Admin — stores, theme, roles, users, metrics

```txt
GET    /api/admin/theme                 → ThemeController@show
PATCH  /api/admin/theme                 → ThemeController@update
GET    /api/admin/orders/recent         → RecentOrdersController@index
GET    /api/admin/metrics/revenue/yearly        → RevenueYearlyController
GET    /api/admin/metrics/sales/performance      → SalesPerformanceController
GET    /api/admin/metrics/traffic/sources        → TrafficSourcesController
GET    /api/admin/metrics/customers/segments     → CustomerSegmentsController
GET    /api/admin/metrics/products/top           → TopProductsController
GET    /api/admin/inventory/low-stock            → LowStockController
GET    /api/admin/permissions            → RoleController@listPermissions
POST   /api/admin/roles                  → RoleController@store
GET    /api/admin/roles/{role}           → RoleController@show
PUT    /api/admin/roles/{role}           → RoleController@update
DELETE /api/admin/roles/{role}           → RoleController@destroy
DELETE /api/admin/users/{user}           → UserController@destroy
POST   /api/admin/stores/{store}/social-links/{link}
                                            → store-level/@destroy
DELETE /api/admin/stores/{store}/social-links/{link}
                                            → StoreSocialLinkController@destroy
```

### Storefront público

```txt
GET    /api/storefront/theme             → StorefrontThemeController@show
GET    /api/storefront/products          → StorefrontProductController@index
GET    /api/storefront/products/{slug}   → StorefrontProductController@show
GET    /api/storefront/products/{slug}/price
                                        → StorefrontProductController@price
GET    /api/storefront/promotions        → StorefrontPromotionController@index
GET    /api/assets/{path}                → StorefrontAssetController@show
```

## Recomendaciones

1. **Agrupar rutas por módulo** — Cada módulo tiene su
   sección comentada en `api.php`.
2. **Usar `apiResource`** — Para CRUDs estándar,
   `Route::apiResource()` genera las 7 rutas RESTful.
3. **Versionado** — Prefijo `/api/v1/` aplicado en
   `bootstrap/app.php`. Ver
   [`docs/api/versioning.md`](../api/versioning.md
   "api/versioning.md").
4. **Rate limiting** — Aplicar throttle en rutas públicas
   del storefront.
5. **Filtrado por tienda** — El middleware
   `ResolveStoreFromDomain` resuelve el `store_id` por
   host y los global scopes filtran queries.
6. **Multi-tenant** — RLS activado sobre tablas con
   `store_id` (F5.3 del roadmap).
