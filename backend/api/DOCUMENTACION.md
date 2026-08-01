# Store — Documentación del Backend

## Resumen del Proyecto

API REST para un ecommerce completo construida con **Laravel 13** y **PHP 8.3+**.
Base de datos: **PostgreSQL** (nombre: `store`).
Autenticación: **Laravel Sanctum** (tokens Bearer).

---

## Stack Tecnológico

| Componente        | Versión    | Propósito                               |
| ----------------- | ---------- | --------------------------------------- |
| PHP               | ^8.3       | Lenguaje del servidor                   |
| Laravel Framework | ^13.8      | Framework MVC                           |
| Laravel Sanctum   | (incluido) | Autenticación API por tokens            |
| Laravel Boost     | ^2.2       | Herramientas de desarrollo AI           |
| Laravel Pint      | ^1.27      | Formateo de código PHP                  |
| Laravel Pail      | ^1.2.5     | Logging en tiempo real                  |
| PHPUnit           | ^12.5.12   | Testing unitario y de funcionalidad     |
| FakerPHP          | ^1.23      | Generación de datos fake para factories |
| PostgreSQL        | 15+        | Base de datos principal                 |

---

## Estructura del Proyecto

```txt
backend/api/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/                    # 58 controllers organizados por dominio
│   │   │       ├── Auth/               # UserController, RoleController, PermissionController, PermissionRoleController
│   │   │       ├── Carts/              # CartController, CartItemController, WishlistController, WishlistItemController
│   │   │       ├── Catalog/            # BrandController, CategoryController, ProductController, ProductAttributeController,
│   │   │       │                       # ProductAttributeValueController, ProductImageController, ProductVariantController,
│   │   │       │                       # VariantAttributeValueController
│   │   │       ├── Customers/          # CustomerController, CustomerAddressController
│   │   │       ├── Inventory/          # InventoryController, InventoryMovementController, WarehouseController
│   │   │       ├── Marketing/          # CouponController, CouponUsageController, PromotionController, ProductPromotionController
│   │   │       ├── Payments/           # PaymentController, PaymentMethodController, PaymentTransactionController,
│   │   │       │                       # PaymentInstallmentController, InvoicePaymentController
│   │   │       ├── Purchases/          # PurchaseController, PurchaseItemController, SupplierController
│   │   │       ├── Returns/            # ReturnRequestController, ReturnRequestItemController, RefundController
│   │   │       ├── Sales/              # OrderController, OrderItemController, OrderStatusHistoryController,
│   │   │       │                       # InvoiceController, InvoiceItemController, InvoiceStatusHistoryController
│   │   │       ├── Settings/           # CurrencyController, ExchangeRateController, StoreSettingController
│   │   │       ├── Shipping/           # ShipmentController, ShipmentTrackingController, ShippingCompanyController,
│   │   │       │                       # ShippingZoneController
│   │   │       ├── Support/            # SupportTicketController, SupportTicketMessageController, ProductReviewController,
│   │   │       │                       # ProductQuestionController
│   │   │       ├── System/             # AuditController, NotificationController
│   │   │       └── Taxes/              # TaxController, ProductTaxController
│   │   └── Resources/                  # 58 API Resources para serialización JSON
│   │       └── (mismos subdirectorios que Controllers)
│   ├── Models/                         # 58 modelos Eloquent
│   └── Providers/
│       └── AppServiceProvider.php      # Auto-discovery de migraciones por subdirectorios
│
├── config/                             # 10 archivos de configuración de Laravel
│   ├── app.php                         # Nombre, timezone, locale, providers
│   ├── auth.php                        # Guards, providers, password reset
│   ├── cache.php                       # Driver de cache (database)
│   ├── database.php                    # Conexiones: sqlite (default), pgsql, mysql, redis
│   ├── filesystems.php                 # Discos: local, public, s3
│   ├── logging.php                     # Canales: stack, single, daily
│   ├── mail.php                        # Transporte de email
│   ├── queue.php                       # Cola de trabajos
│   ├── services.php                    # APIs externas
│   └── session.php                     # Sesiones (driver: database)
│
├── database/
│   ├── migrations/                     # 57 migraciones organizadas por dominio
│   │   ├── auth/                       # users, roles, permissions, permission_role
│   │   ├── catalog/                    # categories, brands, attributes, products, product_images,
│   │   │                               # product_variants, attribute_product, attribute_variant
│   │   ├── customers/                  # customers, customer_addresses
│   │   ├── inventory/                  # warehouses, inventories, inventory_movements
│   │   ├── purchases/                  # suppliers, purchases, purchase_items
│   │   ├── carts/                      # carts, cart_items, wishlists, wishlist_items
│   │   ├── sales/                      # orders, order_items, order_status_histories,
│   │   │                               # invoices, invoice_items, invoice_status_histories
│   │   ├── payments/                   # payment_methods, payments, invoice_payments,
│   │   │                               # payment_installments, payment_transactions
│   │   ├── shipping/                   # shipping_companies, shipping_zones, shipments, shipment_trackings
│   │   ├── marketing/                  # coupons, promotions, coupon_usages, product_promotion
│   │   ├── taxes/                      # taxes, product_tax
│   │   ├── returns/                    # return_requests, return_request_items, refunds
│   │   ├── support/                    # product_reviews, product_questions, support_tickets,
│   │   │                               # support_ticket_messages
│   │   ├── system/                     # notifications, audits
│   │   ├── settings/                   # store_settings, currencies, exchange_rates
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   └── 0001_01_01_000002_create_jobs_table.php
│   ├── factories/
│   │   └── UserFactory.php             # Factory para usuarios de prueba
│   └── seeders/
│       └── DatabaseSeeder.php          # Seeder principal
│
├── routes/
│   ├── api.php                         # Rutas API (protegidas con auth:sanctum)
│   ├── web.php                         # Rutas web (solo welcome)
│   └── console.php                     # Comandos Artisan
│
├── bootstrap/
│   ├── app.php                         # Configuración de routing, middleware, exceptions
│   └── providers.php                   # Registro de proveedores
│
├── public/                             # Assets públicos (index.php, favicon, .htaccess)
├── storage/                            # Logs, cache, sesiones, vistas compiladas
├── tests/                              # PHPUnit (Feature/ExampleTest, Unit/ExampleTest)
├── composer.json                       # Dependencias PHP
├── package.json                        # Dependencias JS (Vite, Tailwind)
├── phpunit.xml                         # Configuración de testing
└── vite.config.js                      # Build tool con laravel-vite-plugin
```

---

## Base de Datos — Modelo Entidad-Relación

### Tablas Principales (15 dominios, 57 tablas)

#### 1. Autenticación (auth)

| Tabla             | Descripción                   | Relaciones clave    |
| ----------------- | ----------------------------- | ------------------- |
| `users`           | Usuarios del sistema          | → roles (FK)        |
| `roles`           | Roles (admin, vendedor, etc.) | ← users             |
| `permissions`     | Permisos por módulo           | ← permission_role   |
| `permission_role` | Pivote roles-permisos         | roles ↔ permissions |

**Campos importantes de `users`:** id, role_id, first_name, last_name, email (unique), password, phone, is_active, registered_at, soft_deletes

#### 2. Catálogo (catalog)

| Tabla                      | Descripción                      | Relaciones clave                                     |
| -------------------------- | -------------------------------- | ---------------------------------------------------- |
| `categories`               | Categorías jerárquicas           | → categories (parent_id, auto-relación)              |
| `brands`                   | Marcas de productos              | ← products                                           |
| `products`                 | Productos principales            | → categories, brands                                 |
| `product_images`           | Imágenes de producto             | → products                                           |
| `product_variants`         | Variantes (talla, color)         | → products                                           |
| `attributes`               | Atributos (color, talla)         | ← product_attribute_values, variant_attribute_values |
| `attribute_product_values` | Valores de atributo por producto | products ↔ attributes                                |
| `attribute_variant_values` | Valores de atributo por variante | product_variants ↔ attributes                        |

**Campos importantes de `products`:** id, category_id, brand_id, name, slug (unique), sku (unique), base_price, cost, is_active, published_at, soft_deletes

#### 3. Clientes (customers)

| Tabla                | Descripción                                      |
| -------------------- | ------------------------------------------------ |
| `customers`          | Clientes de la tienda (puede o no tener user_id) |
| `customer_addresses` | Direcciones de envío/facturación                 |

#### 4. Inventario (inventory)

| Tabla                 | Descripción                         |
| --------------------- | ----------------------------------- |
| `warehouses`          | Almacenes físicos                   |
| `inventories`         | Stock por producto/variante/almacén |
| `inventory_movements` | Entradas, salidas, transferencias   |

#### 5. Compras (purchases)

| Tabla            | Descripción                     |
| ---------------- | ------------------------------- |
| `suppliers`      | Proveedores                     |
| `purchases`      | Órdenes de compra a proveedores |
| `purchase_items` | Ítems de cada compra            |

#### 6. Carritos (carts)

| Tabla            | Descripción                            |
| ---------------- | -------------------------------------- |
| `carts`          | Carritos de compra (session o usuario) |
| `cart_items`     | Productos en el carrito                |
| `wishlists`      | Listas de deseos                       |
| `wishlist_items` | Productos en wishlist                  |

#### 7. Ventas (sales)

| Tabla                      | Descripción                     |
| -------------------------- | ------------------------------- |
| `orders`                   | Pedidos de clientes             |
| `order_items`              | Ítems del pedido                |
| `order_status_histories`   | Historial de cambios de estado  |
| `invoices`                 | Facturas generadas              |
| `invoice_items`            | Ítems de factura                |
| `invoice_status_histories` | Historial de estados de factura |

**Estados de pedido:** pending → processing → shipped → delivered → cancelled

#### 8. Pagos (payments)

| Tabla                  | Descripción                                        |
| ---------------------- | -------------------------------------------------- |
| `payment_methods`      | Métodos disponibles (tarjeta, transferencia, etc.) |
| `payments`             | Pagos recibidos                                    |
| `invoice_payments`     | Pivote factura-pago                                |
| `payment_installments` | Cuotas de pago                                     |
| `payment_transactions` | Transacciones de pasarela de pago                  |

#### 9. Envío (shipping)

| Tabla                | Descripción                  |
| -------------------- | ---------------------------- |
| `shipping_companies` | Empresas de mensajería       |
| `shipping_zones`     | Zonas geográficas con costos |
| `shipments`          | Envíos asignados a pedidos   |
| `shipment_trackings` | Seguimiento de envío         |

#### 10. Marketing (marketing)

| Tabla               | Descripción               |
| ------------------- | ------------------------- |
| `coupons`           | Cupones de descuento      |
| `coupon_usages`     | Registro de uso           |
| `promotions`        | Promociones activas       |
| `product_promotion` | Pivote producto-promoción |

#### 11. Impuestos (taxes)

| Tabla         | Descripción                              |
| ------------- | ---------------------------------------- |
| `taxes`       | Definición de impuestos (IVA, ISR, etc.) |
| `product_tax` | Pivote producto-impuesto                 |

#### 12. Devoluciones (returns)

| Tabla                  | Descripción               |
| ---------------------- | ------------------------- |
| `return_requests`      | Solicitudes de devolución |
| `return_request_items` | Ítems a devolver          |
| `refunds`              | Reembolsos procesados     |

#### 13. Soporte (support)

| Tabla                     | Descripción                   |
| ------------------------- | ----------------------------- |
| `support_tickets`         | Tickets de soporte al cliente |
| `support_ticket_messages` | Mensajes del ticket           |
| `product_reviews`         | Reseñas de productos          |
| `product_questions`       | Preguntas sobre productos     |

#### 14. Sistema (system)

| Tabla           | Descripción                            |
| --------------- | -------------------------------------- |
| `notifications` | Notificaciones a usuarios              |
| `audits`        | Registro de auditoría (quién hizo qué) |

#### 15. Configuración (settings)

| Tabla            | Descripción                        |
| ---------------- | ---------------------------------- |
| `store_settings` | Configuración general de la tienda |
| `currencies`     | Monedas soportadas                 |
| `exchange_rates` | Historial de tasas de cambio       |

---

## Arquitectura de la API

### Patrón: Resource Controller + API Resource

Cada dominio sigue el mismo patrón:

```txt
Request → Route → Controller → Model → Database
                ↓
         API Resource (serialización JSON)
                ↓
           Response JSON
```

### Rutas API (todas bajo `/api/`, protegidas con `auth:sanctum`)

| Método   | Ruta                          | Acción                 |
| -------- | ----------------------------- | ---------------------- |
| `GET`    | `/api/products`               | Listar productos       |
| `POST`   | `/api/products`               | Crear producto         |
| `GET`    | `/api/products/{id}`          | Ver producto           |
| `PUT`    | `/api/products/{id}`          | Actualizar producto    |
| `DELETE` | `/api/products/{id}`          | Eliminar producto      |
| `GET`    | `/api/products/{id}/images`   | Imágenes del producto  |
| `POST`   | `/api/products/{id}/images`   | Agregar imagen         |
| `GET`    | `/api/products/{id}/variants` | Variantes del producto |

**El mismo patrón aplica para todos los recursos:** users, roles, permissions, brands, categories, customers, orders, invoices, payments, shipments, coupons, promotions, taxes, return_requests, support_tickets, audits, notifications, currencies, exchange_rates, store_settings, etc.

### Nested Resources

Algunos recursos usan rutas anidadas:

- `/api/products/{id}/images` → ProductImageController
- `/api/products/{id}/variants` → ProductVariantController
- `/api/customers/{id}/addresses` → CustomerAddressController
- `/api/orders/{id}/items` → OrderItemController
- `/api/orders/{id}/status-histories` → OrderStatusHistoryController
- `/api/invoices/{id}/items` → InvoiceItemController
- `/api/purchases/{id}/items` → PurchaseItemController
- `/api/carts/{id}/items` → CartItemController
- `/api/wishlists/{id}/items` → WishlistItemController
- `/api/shipments/{id}/tracking` → ShipmentTrackingController

### Middleware

Todas las rutas API usan `auth:sanctum` para autenticación por token Bearer.

### Excepciones JSON

En `bootstrap/app.php` se configura que las excepciones se rendericen como JSON automáticamente para requests a `api/*`.

---

## Dependencias PHP

### Producción (`require`)

- `php: ^8.3` — Versión mínima de PHP
- `laravel/framework: ^13.8` — Framework principal
- `laravel/tinker: ^3.0` — REPL interactivo

### Desarrollo (`require-dev`)

- `fakerphp/faker: ^1.23` — Generación de datos fake
- `laravel/boost: ^2.2` — Herramientas AI para Laravel
- `laravel/pail: ^1.2.5` — Tail de logs en tiempo real
- `laravel/pao: ^1.0.6` — Performance optimization
- `laravel/pint: ^1.27` — Formateo de código (PSR-12)
- `mockery/mockery: ^1.6` — Mocking para tests
- `nunomaduro/collision: ^8.6` — Errores formateados en consola
- `phpunit/phpunit: ^12.5.12` — Framework de testing

### Scripts de Composer

| Script           | Comando                                                  | Uso                 |
| ---------------- | -------------------------------------------------------- | ------------------- |
| `composer setup` | Instala dependencias, genera .env, key, migra, npm build | Primera instalación |
| `composer dev`   | Servidor + Queue + Pail + Vite concurrentes              | Desarrollo          |
| `composer test`  | Limpia config y ejecuta PHPUnit                          | Testing             |

---

## Configuración de Base de Datos

### Conexión principal: PostgreSQL

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=store
DB_USERNAME=postgres
DB_PASSWORD=
```

### Testing: SQLite en memoria

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

### Auto-discovery de migraciones

El `AppServiceProvider` usa `glob()` para cargar automáticamente todas las migraciones de subdirectorios:

```php
foreach (glob(database_path('migrations/*'), GLOB_ONLYDIR) as $directory) {
    $this->loadMigrationsFrom($directory);
}
```

---

## Patrones de Código

### Modelos (Laravel 13)

- Usa atributos PHP 8: `#[Fillable]`, `#[Hidden]`
- Relaciones Eloquent: `belongsTo`, `hasMany`, `belongsToMany`
- Soft deletes en tablas principales
- Casts automáticos para fechas y passwords

### Controllers

- Patrón CRUD completo: index, store, show, update, destroy
- Inyección de dependencias por route model binding
- Validación en tiempo de ejecución

### API Resources

- Serialización controlada de datos JSON
- Relaciones incluidas bajo demanda
- Formato consistente para el frontend

### Migraciones

- Nomenclatura: `YYYY_MM_DD_HHMMSS_create_{table}_table.php`
- Foreign keys con `constrained()`, `restrictOnDelete()`, `cascadeOnUpdate()`
- Índices en columnas frecuentemente consultadas
- Soft deletes en tablas principales

---

## Commands Disponibles

```bash
# Desarrollo
php artisan serve              # Servidor HTTP en :8000
php artisan queue:listen       # Worker de cola
php artisan pail               # Tail de logs

# Base de datos
php artisan migrate            # Ejecutar migraciones
php artisan migrate:rollback   # Revertir última migración
php artisan db:seed            # Ejecutar seeders
php artisan tinker             # REPL interactivo

# Generación de código
php artisan make:model Product -mcr   # Modelo + migración + controller + resource
php artisan make:controller Api/Catalog/ProductController --resource
php artisan make:migration create_products_table

# Testing
php artisan test               # Ejecutar todos los tests
php artisan test --filter=nombreTest  # Test específico

# Cache y optimización
php artisan config:clear       # Limpiar cache de configuración
php artisan route:list         # Listar todas las rutas
```

---

## Endpoints API — Referencia Completa

### Auth

| Método   | Ruta                    | Descripción              |
| -------- | ----------------------- | ------------------------ |
| GET/POST | `/api/users`            | CRUD de usuarios         |
| GET/POST | `/api/roles`            | CRUD de roles            |
| GET/POST | `/api/permissions`      | CRUD de permisos         |
| GET/POST | `/api/permission-roles` | Asignar permisos a roles |

### Catálogo

| Método   | Ruta                            | Descripción            |
| -------- | ------------------------------- | ---------------------- |
| GET/POST | `/api/brands`                   | CRUD de marcas         |
| GET/POST | `/api/categories`               | CRUD de categorías     |
| GET/POST | `/api/products`                 | CRUD de productos      |
| GET/POST | `/api/products/{id}/images`     | Imágenes del producto  |
| GET/POST | `/api/products/{id}/variants`   | Variantes del producto |
| GET/POST | `/api/products/{id}/attributes` | Atributos del producto |
| GET/POST | `/api/product-attribute-values` | Valores de atributo    |
| GET/POST | `/api/variant-attribute-values` | Valores de variante    |

### Clientes

| Método   | Ruta                            | Descripción             |
| -------- | ------------------------------- | ----------------------- |
| GET/POST | `/api/customers`                | CRUD de clientes        |
| GET/POST | `/api/customers/{id}/addresses` | Direcciones del cliente |

### Inventario

| Método   | Ruta                       | Descripción            |
| -------- | -------------------------- | ---------------------- |
| GET/POST | `/api/warehouses`          | CRUD de almacenes      |
| GET/POST | `/api/inventories`         | Consulta de inventario |
| GET/POST | `/api/inventory-movements` | Movimientos de stock   |

### Compras

| Método   | Ruta                        | Descripción         |
| -------- | --------------------------- | ------------------- |
| GET/POST | `/api/suppliers`            | CRUD de proveedores |
| GET/POST | `/api/purchases`            | CRUD de compras     |
| GET/POST | `/api/purchases/{id}/items` | Ítems de compra     |

### Carritos

| Método   | Ruta                        | Descripción       |
| -------- | --------------------------- | ----------------- |
| GET/POST | `/api/carts`                | CRUD de carritos  |
| GET/POST | `/api/carts/{id}/items`     | Ítems del carrito |
| GET/POST | `/api/wishlists`            | CRUD de wishlists |
| GET/POST | `/api/wishlists/{id}/items` | Ítems de wishlist |

### Ventas

| Método   | Ruta                                  | Descripción           |
| -------- | ------------------------------------- | --------------------- |
| GET/POST | `/api/orders`                         | CRUD de pedidos       |
| GET/POST | `/api/orders/{id}/items`              | Ítems del pedido      |
| GET/POST | `/api/orders/{id}/status-histories`   | Historial de estados  |
| GET/POST | `/api/invoices`                       | CRUD de facturas      |
| GET/POST | `/api/invoices/{id}/items`            | Ítems de factura      |
| GET/POST | `/api/invoices/{id}/status-histories` | Historial de facturas |

### Pagos

| Método   | Ruta                        | Descripción         |
| -------- | --------------------------- | ------------------- |
| GET/POST | `/api/payments`             | CRUD de pagos       |
| GET/POST | `/api/payment-methods`      | Métodos de pago     |
| GET/POST | `/api/payment-transactions` | Transacciones       |
| GET/POST | `/api/payment-installments` | Cuotas              |
| GET/POST | `/api/invoice-payments`     | Pivote factura-pago |

### Envío

| Método   | Ruta                           | Descripción       |
| -------- | ------------------------------ | ----------------- |
| GET/POST | `/api/shipping-companies`      | Empresas de envío |
| GET/POST | `/api/shipping-zones`          | Zonas geográficas |
| GET/POST | `/api/shipments`               | Envíos            |
| GET/POST | `/api/shipments/{id}/tracking` | Seguimiento       |

### Marketing

| Método   | Ruta                      | Descripción               |
| -------- | ------------------------- | ------------------------- |
| GET/POST | `/api/coupons`            | CRUD de cupones           |
| GET/POST | `/api/coupon-usages`      | Usos de cupón             |
| GET/POST | `/api/promotions`         | CRUD de promociones       |
| GET/POST | `/api/product-promotions` | Pivote producto-promoción |

### Impuestos

| Método   | Ruta                 | Descripción              |
| -------- | -------------------- | ------------------------ |
| GET/POST | `/api/taxes`         | CRUD de impuestos        |
| GET/POST | `/api/product-taxes` | Pivote producto-impuesto |

### Devoluciones

| Método   | Ruta                        | Descripción               |
| -------- | --------------------------- | ------------------------- |
| GET/POST | `/api/return-requests`      | Solicitudes de devolución |
| GET/POST | `/api/return-request-items` | Ítems de devolución       |
| GET/POST | `/api/refunds`              | Reembolsos                |

### Soporte

| Método   | Ruta                           | Descripción            |
| -------- | ------------------------------ | ---------------------- |
| GET/POST | `/api/support-tickets`         | Tickets de soporte     |
| GET/POST | `/api/support-ticket-messages` | Mensajes del ticket    |
| GET/POST | `/api/product-reviews`         | Reseñas de productos   |
| GET/POST | `/api/product-questions`       | Preguntas de productos |

### Sistema

| Método    | Ruta                 | Descripción              |
| --------- | -------------------- | ------------------------ |
| GET       | `/api/audits`        | Auditoría (solo lectura) |
| GET/PATCH | `/api/notifications` | Notificaciones           |

### Configuración

| Método   | Ruta                  | Descripción             |
| -------- | --------------------- | ----------------------- |
| GET/POST | `/api/currencies`     | Monedas                 |
| GET/POST | `/api/exchange-rates` | Tasas de cambio         |
| GET/POST | `/api/store-settings` | Configuración de tienda |

---

## Estado Actual del Código

### Completado ✅

- Estructura base de Laravel 13
- 57 migraciones de base de datos (todas las tablas del ERD)
- 58 modelos Eloquent (solo User tiene implementación completa)
- 58 controllers API (scaffolding con métodos CRUD vacíos)
- 58 API Resources (scaffolding con parent::toArray())
- Rutas API definidas en `routes/api.php`
- Factory y Seeder base para usuarios
- Configuración de testing con SQLite en memoria

### Pendiente 🔲

- Implementar lógica de negocio en controllers
- Implementar validación con Form Request
- Implementar relaciones en API Resources
- Agregar middleware de roles/permisos
- Crear tests unitarios y de funcionalidad
- Configurar pasarela de pagos
- Configurar servicio de envíos
- Implementar sistema de notificaciones por email
