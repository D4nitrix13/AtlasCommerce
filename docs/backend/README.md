# Backend — Laravel API

## Visión general

El backend es una API REST construida con `Laravel 13`,
`PHP 8.3+` y `PostgreSQL`. Autentica peticiones con
Bearer tokens de `Laravel Sanctum`. Está organizado en
módulos por dominio de negocio.

## Stack

| Componente   | Versión       | Propósito                         |
| ------------ | ------------- | --------------------------------- |
| `Laravel`    | 13.x          | Framework `PHP`                   |
| `PHP`        | 8.3+          | Runtime                           |
| `Sanctum`    | 4.x           | Autenticación API (Bearer tokens) |
| `PostgreSQL` | 18+ (PostGIS) | Base de datos                     |
| `PHPUnit`    | 12.x          | Testing                           |
| `Pint`       | 1.x           | Formateo de código                |
| `Larastan`   | 3.x           | Análisis estático                 |
| IDE Helper   | 3.x           | Autocompletado en IDE             |

## Estructura del backend

```txt
backend/api/
├── app/
│   ├── Console/Commands/        # Comandos Artisan
│   ├── Http/
│   │   ├── Controllers/Api/     # Controladores API (18 módulos)
│   │   │   ├── Auth/            # Usuarios, roles, permisos, 2FA
│   │   │   ├── Carts/           # Carritos, wishlists
│   │   │   ├── Catalog/         # Productos, categorías, marcas
│   │   │   ├── Customers/       # Clientes, direcciones
│   │   │   ├── Inventory/       # Stock, movimientos, almacenes
│   │   │   ├── Marketing/       # Cupones, promociones
│   │   │   ├── Payments/        # Métodos de pago, transacciones
│   │   │   ├── Purchases/       # Proveedores, compras
│   │   │   ├── Returns/         # Devoluciones, reembolsos
│   │   │   ├── Sales/           # Órdenes, facturas
│   │   │   ├── Settings/        # Configuración, monedas
│   │   │   ├── Shipping/        # Envíos, zonas, empresas
│   │   │   ├── Storefront/      # Endpoints públicos (preparado)
│   │   │   ├── Stores/          # Configuración multi-tienda
│   │   │   ├── Support/         # Tickets, reseñas, preguntas
│   │   │   ├── System/          # Auditorías, notificaciones
│   │   │   └── Taxes/           # Impuestos
│   │   ├── Middleware/           # Middlewares personalizados
│   │   └── Resources/           # API Resources (transformación JSON)
│   ├── Models/                  # Modelos Eloquent (17 dominios)
│   ├── Notifications/           # Notificaciones (emails, etc.)
│   ├── Services/                # Servicios de negocio
│   │   └── Auth/                # EmailOtpService, RecoveryCodeService
│   └── Policies/                # Políticas de autorización (pendiente)
├── config/                      # Archivos de configuración
├── database/
│   ├── migrations/              # Migraciones organizadas por dominio
│   │   ├── auth/                # 2FA, passkeys, tokens
│   │   ├── carts/               # Carritos
│   │   ├── catalog/             # Productos, categorías
│   │   ├── customers/           # Clientes
│   │   ├── inventory/           # Stock
│   │   ├── marketing/           # Cupones, promociones
│   │   ├── payments/            # Pagos
│   │   ├── purchases/           # Compras
│   │   ├── returns/             # Devoluciones
│   │   ├── sales/               # Órdenes, facturas
│   │   ├── settings/            # Configuración
│   │   ├── shipping/            # Envíos
│   │   ├── stores/              # Multi-tienda
│   │   ├── support/             # Soporte
│   │   ├── system/              # Auditorías
│   │   └── taxes/               # Impuestos
│   ├── factories/               # Factories para testing
│   └── seeders/                 # Seeders
├── routes/
│   ├── api.php                  # Rutas API (protegidas por Sanctum)
│   └── console.php              # Rutas de consola
├── tests/                       # PHPUnit tests
└── composer.json                # Dependencias PHP
```

## Módulos del backend

- **Auth**
  - Controladores: `UserController`, `RoleController`,
    `PermissionController`, `TwoFactorAuthController`,
    `RecoveryCodeController`
  - Modelos: `User`, `Role`, `Permission`,
    `TwoFactorSetting`, `TwoFactorMethod`, `OtpChallenge`,
    `RecoveryCode`, `PasskeyCredential`
  - Descripción: Autenticación, autorización, 2FA
- **Catalog**
  - Controladores: `ProductController`, `CategoryController`,
    `BrandController`, `ProductVariantController`,
    `ProductImageController`
  - Modelos: `Product`, `Category`, `Brand`,
    `ProductVariant`, `ProductImage`, `ProductAttribute`
  - Descripción: Catálogo de productos
- **Customers**
  - Controladores: `CustomerController`,
    `CustomerAddressController`
  - Modelos: `Customer`, `CustomerAddress`
  - Descripción: Perfiles de clientes
- **Inventory**
  - Controladores: `InventoryController`,
    `InventoryMovementController`, `WarehouseController`
  - Modelos: `Inventory`, `InventoryMovement`, `Warehouse`
  - Descripción: Gestión de stock
- **Purchases**
  - Controladores: `PurchaseController`,
    `SupplierController`
  - Modelos: `Purchase`, `Supplier`, `PurchaseItem`
  - Descripción: Compras a proveedores
- **Carts**
  - Controladores: `CartController`, `CartItemController`,
    `WishlistController`, `WishlistItemController`
  - Modelos: `Cart`, `CartItem`, `Wishlist`, `WishlistItem`
  - Descripción: Carritos y wishlists
- **Sales**
  - Controladores: `OrderController`, `InvoiceController`,
    `OrderItemController`, `InvoiceItemController`
  - Modelos: `Order`, `Invoice`, `OrderItem`,
    `InvoiceItem`, `OrderStatusHistory`,
    `InvoiceStatusHistory`
  - Descripción: Ventas y facturación
- **Payments**
  - Controladores: `PaymentController`,
    `PaymentMethodController`,
    `PaymentTransactionController`
  - Modelos: `Payment`, `PaymentMethod`,
    `PaymentTransaction`, `PaymentInstallment`,
    `InvoicePayment`
  - Descripción: Pagos
- **Shipping**
  - Controladores: `ShippingCompanyController`,
    `ShippingZoneController`, `ShipmentController`,
    `ShipmentTrackingController`
  - Modelos: `ShippingCompany`, `ShippingZone`,
    `Shipment`, `ShipmentTracking`
  - Descripción: Envíos
- **Marketing**
  - Controladores: `CouponController`,
    `PromotionController`
  - Modelos: `Coupon`, `Promotion`, `CouponUsage`,
    `ProductPromotion`
  - Descripción: Cupones y promociones
- **Taxes**
  - Controladores: `TaxController`,
    `ProductTaxController`
  - Modelos: `Tax`, `ProductTax`
  - Descripción: Impuestos
- **Returns**
  - Controladores: `ReturnRequestController`,
    `RefundController`
  - Modelos: `ReturnRequest`, `ReturnRequestItem`,
    `Refund`
  - Descripción: Devoluciones
- **Support**
  - Controladores: `SupportTicketController`,
    `ProductReviewController`,
    `ProductQuestionController`
  - Modelos: `SupportTicket`, `SupportTicketMessage`,
    `ProductReview`, `ProductQuestion`
  - Descripción: Soporte al cliente
- **System**
  - Controladores: `AuditController`,
    `NotificationController`
  - Modelos: `Audit`, `Notification`
  - Descripción: Auditoría y notificaciones
- **Settings**
  - Controladores: `CurrencyController`,
    `ExchangeRateController`,
    `StoreSettingController`
  - Modelos: `Currency`, `ExchangeRate`,
    `StoreSetting`
  - Descripción: Configuración
- **Stores**
  - Controladores: `StoreSettingController`
  - Modelos: `Store`, `StoreDomain`,
    `StoreSocialLink`, `ProductStore`, `UserStore`,
    `CustomerStore`, `SalesChannel`, `StoreSetting`
  - Descripción: Multi-tienda. Base estructural
    contemplada en el modelo ER. Lógica de negocio
    completa: debe verificarse en el código.
- **Storefront**
  - Controladores: `ProductController` como base
    inicial; debe verificarse en código
  - Modelos: —
  - Descripción: Endpoints públicos del storefront

## Autenticación

Las rutas privadas y administrativas en `routes/api.php`
están protegidas por el middleware `auth:sanctum`. Las
rutas públicas del storefront no requieren autenticación.
El frontend envía el token en el header:

```http
Authorization: Bearer {token}
```

Ver [backend/api/authentication.md](api/authentication.md "Autenticación API")
para detalles completos.

## Testing

```bash
# Ejecutar todos los tests
php artisan test --compact

# Ejecutar un archivo específico
php artisan test --compact tests/Feature/Auth/EmailOtpTwoFactorTest.php

# Filtrar por nombre de test
php artisan test --compact --filter=testName
```

## Convenciones

- Los controladores viven en
  `app/Http/Controllers/Api/{Modulo}/`
- Los modelos viven en `app/Models/{Modulo}/`
- Los servicios viven en `app/Services/{Modulo}/`
- Las migraciones se organizan por dominio en
  `database/migrations/{modulo}/`
- Se usa `Pint` para formateo, `Larastan` para análisis
  estático
- Los controladores deben ser delgados; la lógica de
  negocio va en Services
