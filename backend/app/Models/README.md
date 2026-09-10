# Models — AtlasCommerce Backend

Este directorio contiene los modelos Eloquent del backend Laravel para el sistema store. Cada modelo representa una entidad principal del dominio y define la relación entre Laravel y las tablas de la base de datos.

## Objetivo del directorio

Los modelos son la capa encargada de representar los datos del sistema, sus relaciones y conversiones básicas de tipos.

En este proyecto, los modelos están organizados por módulos funcionales:

```text
Auth
Customers
Catalog
Inventory
Purchases
Carts
Sales
Payments
Shipping
Marketing
Taxes
Returns
Support
System
Settings
```

Esta organización permite mantener el backend más limpio, escalable y fácil de entender.

## Estructura modular actual

La ubicación principal de los modelos es:

```text
app/Models/
```

Después del refactor, los modelos deben organizarse por carpetas internas según el módulo al que pertenecen.

```text
app/Models/
├── Auth/
├── Carts/
├── Catalog/
├── Customers/
├── Inventory/
├── Marketing/
├── Payments/
├── Purchases/
├── Returns/
├── Sales/
├── Settings/
├── Shipping/
├── Support/
├── System/
└── Taxes/
```

Esta estructura evita tener demasiados modelos mezclados en una sola carpeta y facilita el mantenimiento del proyecto.

## Convención general de nombres

Los modelos deben crearse en inglés, en singular y usando PascalCase.

Ejemplos:

```text
User
Role
Permission
Customer
CustomerAddress
Product
ProductVariant
Inventory
Order
Invoice
Payment
Shipment
ReturnRequest
SupportTicket
StoreSetting
TwoFactorSetting
OtpChallenge
RecoveryCode
PasskeyCredential
```

Las tablas deben estar en inglés, en plural y usando snake_case.

Ejemplos:

```text
users
roles
permissions
customers
customer_addresses
products
product_variants
inventories
orders
invoices
payments
shipments
return_requests
support_tickets
store_settings
two_factor_settings
two_factor_methods
otp_challenges
recovery_codes
passkey_credentials
```

Cada modelo debe usar el namespace correspondiente a su carpeta.

Ejemplos:

```php
namespace App\Models\Auth;
namespace App\Models\Catalog;
namespace App\Models\Sales;
namespace App\Models\Payments;
namespace App\Models\System;
```

## Advertencia sobre rutas duplicadas

No se debe usar esta estructura:

```text
app/Models/Models/Auth/
```

Esa ruta es incorrecta porque duplica el segmento `Models`.

La ruta correcta es:

```text
app/Models/Auth/
```

Este error suele ocurrir cuando se ejecuta:

```bash
php artisan make:model Models/Auth/TwoFactorSetting
```

Ese comando crea una ruta incorrecta:

```text
app/Models/Models/Auth/TwoFactorSetting.php
```

El comando correcto es:

```bash
php artisan make:model Auth/TwoFactorSetting
```

Ese comando genera:

```text
app/Models/Auth/TwoFactorSetting.php
```

Si ya existe la carpeta duplicada, se debe mover el contenido a la ruta correcta:

```bash
mkdir -p app/Models/Auth

mv app/Models/Models/Auth/*.php app/Models/Auth/

rmdir app/Models/Models/Auth
rmdir app/Models/Models
```

Luego se deben actualizar los namespaces.

Antes:

```php
namespace App\Models\Models\Auth;
```

Después:

```php
namespace App\Models\Auth;
```

También se debe revisar cualquier `use` que apunte al namespace anterior.

Después de mover modelos, ejecutar:

```bash
composer dump-autoload
```

Si se usa Docker:

```bash
docker compose exec app composer dump-autoload
```

## Módulo Auth

Ubicación:

```text
app/Models/Auth/
```

Modelos:

```text
User.php
Role.php
Permission.php
PermissionRole.php
TwoFactorSetting.php
TwoFactorMethod.php
OtpChallenge.php
RecoveryCode.php
PasskeyCredential.php
```

Este módulo maneja usuarios, roles, permisos y autenticación de dos factores.

Responsabilidades principales:

```text
- Usuarios del sistema
- Roles
- Permisos
- Relación entre roles y permisos
- Configuración de 2FA
- Métodos de segundo factor
- OTP por correo
- TOTP
- Passkeys
- Códigos de recuperación
```

Relaciones recomendadas:

```text
Role hasMany User
Role belongsToMany Permission
Permission belongsToMany Role
User belongsTo Role

User hasOne TwoFactorSetting
User hasMany TwoFactorMethod
User hasMany OtpChallenge
User hasMany RecoveryCode
User hasMany PasskeyCredential
```

## Módulo Customers

Ubicación:

```text
app/Models/Customers/
```

Modelos:

```text
Customer.php
CustomerAddress.php
```

Este módulo maneja clientes comerciales y direcciones.

Responsabilidades principales:

```text
- Clientes compradores
- Direcciones de envío
- Direcciones de facturación
- Relación entre usuarios y clientes
```

Relaciones recomendadas:

```text
Customer belongsTo User
Customer hasMany CustomerAddress
Customer hasMany Order
Customer hasMany Invoice
Customer hasMany Payment
```

## Módulo Catalog

Ubicación:

```text
app/Models/Catalog/
```

Modelos:

```text
Brand.php
Category.php
Product.php
ProductAttribute.php
ProductAttributeValue.php
ProductImage.php
ProductVariant.php
VariantAttributeValue.php
```

Este módulo maneja el catálogo del store.

Responsabilidades principales:

```text
- Productos
- Categorías
- Marcas
- Imágenes
- Variantes
- Atributos de producto
- Atributos de variantes
```

Relaciones recomendadas:

```text
Category hasMany Product
Category belongsTo Category as parent
Category hasMany Category as children

Brand hasMany Product

Product belongsTo Category
Product belongsTo Brand
Product hasMany ProductImage
Product hasMany ProductVariant
Product belongsToMany ProductAttribute

ProductVariant belongsTo Product
ProductVariant hasMany VariantAttributeValue
```

## Módulo Inventory

Ubicación:

```text
app/Models/Inventory/
```

Modelos:

```text
Inventory.php
InventoryMovement.php
Warehouse.php
```

Este módulo maneja almacenes, existencias y movimientos de inventario.

Responsabilidades principales:

```text
- Almacenes
- Stock actual
- Stock mínimo
- Stock reservado
- Entradas de inventario
- Salidas de inventario
- Ajustes de inventario
```

Relaciones recomendadas:

```text
Warehouse hasMany Inventory

Inventory belongsTo Warehouse
Inventory belongsTo Product
Inventory belongsTo ProductVariant
Inventory hasMany InventoryMovement

InventoryMovement belongsTo Inventory
InventoryMovement belongsTo User
```

## Módulo Purchases

Ubicación:

```text
app/Models/Purchases/
```

Modelos:

```text
Purchase.php
PurchaseItem.php
Supplier.php
```

Este módulo maneja compras a proveedores.

Responsabilidades principales:

```text
- Proveedores
- Compras
- Detalles de compras
- Costos de adquisición
- Entrada de productos al negocio
```

Relaciones recomendadas:

```text
Supplier hasMany Purchase

Purchase belongsTo Supplier
Purchase belongsTo User
Purchase hasMany PurchaseItem

PurchaseItem belongsTo Purchase
PurchaseItem belongsTo Product
PurchaseItem belongsTo ProductVariant
```

## Módulo Carts

Ubicación:

```text
app/Models/Carts/
```

Modelos:

```text
Cart.php
CartItem.php
Wishlist.php
WishlistItem.php
```

Este módulo maneja carritos de compra y listas de deseos.

Responsabilidades principales:

```text
- Carritos de compra
- Productos agregados al carrito
- Listas de deseos
- Productos favoritos
```

Relaciones recomendadas:

```text
Cart belongsTo Customer
Cart hasMany CartItem

CartItem belongsTo Cart
CartItem belongsTo Product
CartItem belongsTo ProductVariant

Wishlist belongsTo Customer
Wishlist hasMany WishlistItem

WishlistItem belongsTo Wishlist
WishlistItem belongsTo Product
```

## Módulo Sales

Ubicación:

```text
app/Models/Sales/
```

Modelos:

```text
Order.php
OrderItem.php
OrderStatusHistory.php
Invoice.php
InvoiceItem.php
InvoicePayment.php
InvoiceStatusHistory.php
```

Este módulo maneja pedidos, facturas e historial comercial.

Responsabilidades principales:

```text
- Pedidos
- Detalles de pedidos
- Facturas
- Detalles de facturas
- Historial de estados de pedidos
- Historial de estados de facturas
- Relación entre facturas y pagos
```

Relaciones recomendadas:

```text
Order belongsTo Customer
Order belongsTo CustomerAddress
Order hasMany OrderItem
Order hasMany OrderStatusHistory
Order hasOne Invoice

OrderItem belongsTo Order
OrderItem belongsTo Product
OrderItem belongsTo ProductVariant

Invoice belongsTo Order
Invoice belongsTo Customer
Invoice hasMany InvoiceItem
Invoice hasMany InvoiceStatusHistory
Invoice hasMany InvoicePayment
Invoice hasMany PaymentInstallment

InvoiceItem belongsTo Invoice
InvoiceItem belongsTo Product
InvoiceItem belongsTo ProductVariant
```

## Módulo Payments

Ubicación:

```text
app/Models/Payments/
```

Modelos:

```text
Payment.php
PaymentInstallment.php
PaymentMethod.php
PaymentTransaction.php
```

Este módulo maneja métodos de pago, abonos, cuotas y transacciones.

Responsabilidades principales:

```text
- Métodos de pago
- Pagos
- Cuotas
- Transacciones de pago
- Información técnica de pasarelas
```

Relaciones recomendadas:

```text
Payment belongsTo Customer
Payment belongsTo PaymentMethod
Payment hasMany InvoicePayment
Payment hasMany PaymentTransaction

PaymentMethod hasMany Payment

InvoicePayment belongsTo Invoice
InvoicePayment belongsTo Payment

PaymentInstallment belongsTo Invoice

PaymentTransaction belongsTo Payment
```

## Módulo Shipping

Ubicación:

```text
app/Models/Shipping/
```

Modelos:

```text
Shipment.php
ShipmentTracking.php
ShippingCompany.php
ShippingZone.php
```

Este módulo maneja envíos y seguimiento logístico.

Responsabilidades principales:

```text
- Empresas de envío
- Zonas de envío
- Envíos
- Costos de envío
- Códigos de seguimiento
- Historial de seguimiento
```

Relaciones recomendadas:

```text
ShippingCompany hasMany Shipment
ShippingZone hasMany Shipment

Shipment belongsTo Order
Shipment belongsTo ShippingCompany
Shipment belongsTo ShippingZone
Shipment hasMany ShipmentTracking

ShipmentTracking belongsTo Shipment
```

## Módulo Marketing

Ubicación:

```text
app/Models/Marketing/
```

Modelos:

```text
Coupon.php
CouponUsage.php
Promotion.php
ProductPromotion.php
```

Este módulo maneja cupones, promociones y reglas comerciales de descuento.

Responsabilidades principales:

```text
- Cupones
- Uso de cupones
- Promociones
- Relación entre productos y promociones
```

Relaciones recomendadas:

```text
Coupon hasMany CouponUsage

CouponUsage belongsTo Coupon
CouponUsage belongsTo Customer
CouponUsage belongsTo Order

Promotion belongsToMany Product
ProductPromotion belongsTo Product
ProductPromotion belongsTo Promotion
```

Nota: `ProductPromotion` también puede considerarse parte de `Catalog`, pero en este proyecto se recomienda ubicarlo en `Marketing` porque representa reglas comerciales de descuento.

## Módulo Taxes

Ubicación:

```text
app/Models/Taxes/
```

Modelos:

```text
Tax.php
ProductTax.php
```

Este módulo maneja impuestos y su relación con productos.

Responsabilidades principales:

```text
- Impuestos
- Porcentajes de impuestos
- Relación entre productos e impuestos
```

Relaciones recomendadas:

```text
Tax hasMany ProductTax
ProductTax belongsTo Tax
ProductTax belongsTo Product
```

Nota: `ProductTax` también puede considerarse parte de `Catalog`, pero en este proyecto se recomienda ubicarlo en `Taxes` porque representa configuración tributaria.

## Módulo Returns

Ubicación:

```text
app/Models/Returns/
```

Modelos:

```text
Refund.php
ReturnRequest.php
ReturnRequestItem.php
```

Este módulo maneja devoluciones y reembolsos.

Responsabilidades principales:

```text
- Solicitudes de devolución
- Productos devueltos
- Reembolsos
```

Relaciones recomendadas:

```text
ReturnRequest belongsTo Order
ReturnRequest belongsTo Customer
ReturnRequest hasMany ReturnRequestItem
ReturnRequest hasOne Refund

ReturnRequestItem belongsTo ReturnRequest
ReturnRequestItem belongsTo Product
ReturnRequestItem belongsTo ProductVariant

Refund belongsTo ReturnRequest
Refund belongsTo PaymentMethod
```

## Módulo Support

Ubicación:

```text
app/Models/Support/
```

Modelos:

```text
ProductQuestion.php
ProductReview.php
SupportTicket.php
SupportTicketMessage.php
```

Este módulo maneja atención al cliente e interacción del comprador.

Responsabilidades principales:

```text
- Reseñas de productos
- Preguntas sobre productos
- Tickets de soporte
- Mensajes de soporte
```

Relaciones recomendadas:

```text
ProductReview belongsTo Product
ProductReview belongsTo Customer

ProductQuestion belongsTo Product
ProductQuestion belongsTo Customer

SupportTicket belongsTo Customer
SupportTicket hasMany SupportTicketMessage

SupportTicketMessage belongsTo SupportTicket
SupportTicketMessage belongsTo User
```

Nota: `ProductQuestion` y `ProductReview` también pueden considerarse parte de `Catalog`. En este proyecto se ubican en `Support` porque están relacionados con atención al cliente e interacción del comprador.

## Módulo System

Ubicación:

```text
app/Models/System/
```

Modelos:

```text
Audit.php
Notification.php
```

Este módulo maneja auditoría y notificaciones internas.

Responsabilidades principales:

```text
- Auditoría
- Notificaciones internas
- Registro de acciones importantes del sistema
```

Relaciones recomendadas:

```text
Audit belongsTo User
Notification belongsTo User
```

## Módulo Settings

Ubicación:

```text
app/Models/Settings/
```

Modelos:

```text
Currency.php
ExchangeRate.php
StoreSetting.php
```

Este módulo maneja configuración general de tienda, monedas y tipos de cambio.

Responsabilidades principales:

```text
- Configuración general de la tienda
- Monedas
- Tipos de cambio
```

Relaciones recomendadas:

```text
Currency hasMany ExchangeRate
ExchangeRate belongsTo Currency
```

## Ubicación oficial de modelos compartidos

Algunos modelos pueden pertenecer conceptualmente a más de un módulo. Para evitar duplicidad, se debe elegir una sola ubicación.

| Modelo               | Ubicación recomendada | Motivo                                           |
| -------------------- | --------------------- | ------------------------------------------------ |
| `ProductPromotion`   | `Marketing`           | Representa reglas comerciales de promoción       |
| `ProductTax`         | `Taxes`               | Representa configuración tributaria              |
| `ProductReview`      | `Support`             | Representa interacción del cliente               |
| `ProductQuestion`    | `Support`             | Representa preguntas del comprador               |
| `InvoicePayment`     | `Sales`               | Une factura con pago, pero nace desde la factura |
| `PaymentInstallment` | `Payments`            | Representa cuotas o plan de pago                 |

Lo más importante es mantener una decisión consistente en todo el proyecto.

## Comandos para crear modelos por módulo

Crear modelos dentro de subcarpetas debe hacerse sin anteponer `Models/`.

Correcto:

```bash
php artisan make:model Auth/TwoFactorSetting
php artisan make:model Catalog/Product
php artisan make:model Sales/Order
```

Incorrecto:

```bash
php artisan make:model Models/Auth/TwoFactorSetting
php artisan make:model Models/Catalog/Product
php artisan make:model Models/Sales/Order
```

Comandos para Auth:

```bash
php artisan make:model Auth/User
php artisan make:model Auth/Role
php artisan make:model Auth/Permission
php artisan make:model Auth/PermissionRole
php artisan make:model Auth/TwoFactorSetting
php artisan make:model Auth/TwoFactorMethod
php artisan make:model Auth/OtpChallenge
php artisan make:model Auth/RecoveryCode
php artisan make:model Auth/PasskeyCredential
```

Comandos para Customers:

```bash
php artisan make:model Customers/Customer
php artisan make:model Customers/CustomerAddress
```

Comandos para Catalog:

```bash
php artisan make:model Catalog/Brand
php artisan make:model Catalog/Category
php artisan make:model Catalog/Product
php artisan make:model Catalog/ProductAttribute
php artisan make:model Catalog/ProductAttributeValue
php artisan make:model Catalog/ProductImage
php artisan make:model Catalog/ProductVariant
php artisan make:model Catalog/VariantAttributeValue
```

Comandos para Inventory:

```bash
php artisan make:model Inventory/Inventory
php artisan make:model Inventory/InventoryMovement
php artisan make:model Inventory/Warehouse
```

Comandos para Purchases:

```bash
php artisan make:model Purchases/Purchase
php artisan make:model Purchases/PurchaseItem
php artisan make:model Purchases/Supplier
```

Comandos para Carts:

```bash
php artisan make:model Carts/Cart
php artisan make:model Carts/CartItem
php artisan make:model Carts/Wishlist
php artisan make:model Carts/WishlistItem
```

Comandos para Sales:

```bash
php artisan make:model Sales/Order
php artisan make:model Sales/OrderItem
php artisan make:model Sales/OrderStatusHistory
php artisan make:model Sales/Invoice
php artisan make:model Sales/InvoiceItem
php artisan make:model Sales/InvoicePayment
php artisan make:model Sales/InvoiceStatusHistory
```

Comandos para Payments:

```bash
php artisan make:model Payments/Payment
php artisan make:model Payments/PaymentInstallment
php artisan make:model Payments/PaymentMethod
php artisan make:model Payments/PaymentTransaction
```

Comandos para Shipping:

```bash
php artisan make:model Shipping/Shipment
php artisan make:model Shipping/ShipmentTracking
php artisan make:model Shipping/ShippingCompany
php artisan make:model Shipping/ShippingZone
```

Comandos para Marketing:

```bash
php artisan make:model Marketing/Coupon
php artisan make:model Marketing/CouponUsage
php artisan make:model Marketing/Promotion
php artisan make:model Marketing/ProductPromotion
```

Comandos para Taxes:

```bash
php artisan make:model Taxes/Tax
php artisan make:model Taxes/ProductTax
```

Comandos para Returns:

```bash
php artisan make:model Returns/Refund
php artisan make:model Returns/ReturnRequest
php artisan make:model Returns/ReturnRequestItem
```

Comandos para Support:

```bash
php artisan make:model Support/ProductQuestion
php artisan make:model Support/ProductReview
php artisan make:model Support/SupportTicket
php artisan make:model Support/SupportTicketMessage
```

Comandos para System:

```bash
php artisan make:model System/Audit
php artisan make:model System/Notification
```

Comandos para Settings:

```bash
php artisan make:model Settings/Currency
php artisan make:model Settings/ExchangeRate
php artisan make:model Settings/StoreSetting
```

## Responsabilidades permitidas en modelos

Cada modelo debe encargarse de:

```text
- Definir la tabla asociada cuando no siga la convención de Laravel.
- Definir relaciones entre entidades.
- Definir campos asignables con $fillable o protegerlos con $guarded.
- Definir conversiones de tipo con $casts.
- Definir scopes reutilizables.
- Definir atributos calculados simples.
- Usar SoftDeletes cuando la información no deba eliminarse definitivamente.
```

## Responsabilidades prohibidas en modelos

Los modelos no deben contener lógica compleja de negocio. Esa lógica debe moverse a servicios, acciones, jobs o clases especializadas.

Evitar en los modelos:

```text
- Procesos largos de facturación.
- Cálculo complejo de pagos.
- Flujo completo de checkout.
- Procesamiento de inventario con múltiples pasos.
- Lógica de pasarelas de pago.
- Envío de correos.
- Generación compleja de reportes.
- Implementación completa de WebAuthn o TOTP.
```

Para esos casos se recomienda usar:

```text
app/Services/
app/Actions/
app/Jobs/
```

Ejemplos para 2FA:

```text
app/Services/Auth/EmailOtpService.php
app/Services/Auth/TotpService.php
app/Services/Auth/PasskeyService.php
app/Services/Auth/RecoveryCodeService.php
```

## Modelos de autenticación 2FA

Los modelos nuevos para autenticación de dos factores son:

```text
TwoFactorSetting
TwoFactorMethod
OtpChallenge
RecoveryCode
PasskeyCredential
```

Ubicación recomendada:

```text
app/Models/Auth/
```

Tablas relacionadas:

```text
two_factor_settings
two_factor_methods
otp_challenges
recovery_codes
passkey_credentials
```

Responsabilidad de cada modelo:

| Modelo              | Responsabilidad                           |
| ------------------- | ----------------------------------------- |
| `TwoFactorSetting`  | Configuración general de 2FA por usuario  |
| `TwoFactorMethod`   | Métodos habilitados por usuario           |
| `OtpChallenge`      | Códigos temporales enviados por correo    |
| `RecoveryCode`      | Códigos de recuperación                   |
| `PasskeyCredential` | Credenciales públicas WebAuthn o Passkeys |

Relaciones recomendadas:

```text
User hasOne TwoFactorSetting
User hasMany TwoFactorMethod
User hasMany OtpChallenge
User hasMany RecoveryCode
User hasMany PasskeyCredential

TwoFactorSetting belongsTo User
TwoFactorMethod belongsTo User
OtpChallenge belongsTo User
RecoveryCode belongsTo User
PasskeyCredential belongsTo User
```

Ejemplo de relaciones en `User`:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

public function twoFactorSetting(): HasOne
{
    return $this->hasOne(TwoFactorSetting::class);
}

public function twoFactorMethods(): HasMany
{
    return $this->hasMany(TwoFactorMethod::class);
}

public function otpChallenges(): HasMany
{
    return $this->hasMany(OtpChallenge::class);
}

public function recoveryCodes(): HasMany
{
    return $this->hasMany(RecoveryCode::class);
}

public function passkeyCredentials(): HasMany
{
    return $this->hasMany(PasskeyCredential::class);
}
```

## Campos recomendados por modelo

Cada modelo debería definir explícitamente sus campos asignables.

Opción recomendada:

```php
protected $fillable = [];
```

Alternativa:

```php
protected $guarded = ['id'];
```

Para fechas y valores booleanos, usar `$casts`.

Ejemplo:

```php
protected $casts = [
    'is_active' => 'boolean',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
];
```

Para montos monetarios:

```php
protected $casts = [
    'total' => 'decimal:2',
    'subtotal' => 'decimal:2',
    'discount' => 'decimal:2',
    'tax' => 'decimal:2',
];
```

Para campos JSON:

```php
protected $casts = [
    'transports' => 'array',
];
```

Para fechas específicas:

```php
protected $casts = [
    'enabled_at' => 'datetime',
    'verified_at' => 'datetime',
    'expires_at' => 'datetime',
    'used_at' => 'datetime',
    'last_used_at' => 'datetime',
];
```

## Tablas que requieren configuración manual

Algunos modelos pueden necesitar definir manualmente su tabla porque el nombre del modelo no coincide exactamente con la convención automática de Laravel.

Ejemplos:

```php
protected $table = 'permission_role';
```

```php
protected $table = 'attribute_product';
```

```php
protected $table = 'attribute_variant';
```

```php
protected $table = 'product_promotion';
```

```php
protected $table = 'product_tax';
```

También se debe revisar el modelo relacionado con la tabla `attributes`, porque `Attribute` puede generar confusión con atributos internos de Eloquent.

Si se usa un nombre alternativo como `ProductAttribute`, debe declararse:

```php
protected $table = 'attributes';
```

## Ejemplo de modelo Product

```php
<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'description',
        'sku',
        'base_price',
        'cost',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
```

## Ejemplo de modelo TwoFactorSetting

```php
<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorSetting extends Model
{
    protected $fillable = [
        'user_id',
        'enabled',
        'preferred_method',
        'required',
        'enabled_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'required' => 'boolean',
        'enabled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Como `User` también queda dentro de `App\Models\Auth`, no es necesario importarlo si está en el mismo namespace.

Si en algún momento `User` queda en otro namespace, entonces sí debe importarse manualmente.

## Actualización de imports después del refactor

Cuando un modelo se mueve de carpeta, las relaciones deben importar la nueva clase.

Ejemplo:

```php
use App\Models\Auth\User;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Sales\Order;
```

Antes o después de mover archivos, se pueden buscar referencias antiguas con:

```bash
grep -R "App\\Models\\Product" -n app database routes
grep -R "App\\Models\\User" -n app database routes
grep -R "App\\Models\\Order" -n app database routes
```

También se puede buscar cualquier import antiguo:

```bash
grep -R "App\\Models\\" -n app database routes
```

## Proceso seguro de refactorización

No se recomienda mover modelos sin revisar namespaces e imports.

Proceso recomendado:

```text
1. Crear carpetas por módulo.
2. Mover modelos por grupos.
3. Cambiar namespace de cada modelo.
4. Actualizar imports en modelos, controladores, servicios, factories, seeders y policies.
5. Ejecutar composer dump-autoload.
6. Ejecutar php artisan route:list.
7. Ejecutar php artisan migrate:status.
8. Revisar errores de clases no encontradas.
9. Repetir con el siguiente módulo si se hace por fases.
```

## Comandos de revisión del proyecto

Verificar estructura:

```bash
lsd app/Models --tree
```

Regenerar autoload:

```bash
composer dump-autoload
```

Con Docker:

```bash
docker compose exec app composer dump-autoload
```

Verificar rutas:

```bash
php artisan route:list
```

Con Docker:

```bash
docker compose exec app php artisan route:list
```

Verificar migraciones:

```bash
php artisan migrate:status
```

Con Docker:

```bash
docker compose exec app php artisan migrate:status
```

Buscar namespaces duplicados incorrectos:

```bash
grep -R "App\\Models\\Models" -n app database routes
```

Buscar modelos que todavía quedaron con namespace plano:

```bash
grep -R "namespace App\\Models;" -n app/Models
```

## Recomendaciones finales

1. Mantener un modelo por entidad principal.
2. Usar relaciones Eloquent en lugar de consultas manuales repetidas.
3. Definir `$fillable`, `$casts` y relaciones desde el inicio.
4. Usar nombres en inglés.
5. Usar tablas en plural y `snake_case`.
6. Evitar lógica compleja dentro del modelo.
7. Usar servicios para procesos como checkout, pagos, facturación, inventario y 2FA.
8. Revisar manualmente los modelos de tablas pivote.
9. Agregar `SoftDeletes` en entidades donde no se deben borrar registros de forma permanente.
10. Refactorizar por carpetas cuando haya muchos modelos.
11. Evitar rutas duplicadas como `app/Models/Models`.
12. Después de mover modelos, actualizar namespaces e imports.
13. Ejecutar `composer dump-autoload` después del refactor.
14. Verificar que los controladores, servicios, factories, seeders y policies usen los namespaces nuevos.
15. Documentar cada nuevo modelo en este README.

## Conclusión del README

Para este proyecto sí conviene mantener los modelos organizados por carpetas, porque el dominio ya es grande y contiene muchos módulos.

La estructura oficial recomendada es:

```text
app/Models/Auth
app/Models/Carts
app/Models/Catalog
app/Models/Customers
app/Models/Inventory
app/Models/Marketing
app/Models/Payments
app/Models/Purchases
app/Models/Returns
app/Models/Sales
app/Models/Settings
app/Models/Shipping
app/Models/Support
app/Models/System
app/Models/Taxes
```

La estructura incorrecta que debe evitarse es:

```text
app/Models/Models/Auth
```

La estructura correcta para los modelos de autenticación es:

```text
app/Models/Auth
```

Después de cualquier movimiento de modelos, siempre se debe actualizar el namespace, corregir imports y ejecutar:

```bash
composer dump-autoload
```
