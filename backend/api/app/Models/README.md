# Models - Ecommerce Backend

Este directorio contiene los modelos Eloquent del backend Laravel para el sistema ecommerce. Cada modelo representa una entidad principal del dominio y se encarga de definir la relación entre Laravel y las tablas de la base de datos.

## Objetivo

Los modelos son la capa encargada de representar los datos del sistema y sus relaciones.

En este proyecto, los modelos representan módulos como:

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

## Ubicación

```text
app/Models/
```

## Responsabilidades de los modelos

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

## Lo que no debe hacerse en los modelos

Los modelos no deben contener lógica compleja de negocio. Esa lógica debe moverse a servicios, acciones o clases especializadas.

Evitar en los modelos:

```text
- Procesos largos de facturación.
- Cálculo complejo de pagos.
- Flujo completo de checkout.
- Procesamiento de inventario con múltiples pasos.
- Lógica de pasarelas de pago.
- Envío de correos.
- Generación compleja de reportes.
```

Para esos casos se recomienda usar:

```text
app/Services/
app/Actions/
app/Jobs/
```

## Convención de nombres

Los modelos usan nombres en singular y PascalCase.

Ejemplos:

```text
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
```

Las tablas usan nombres en plural y snake_case.

Ejemplos:

```text
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
```

## Modelos con nombres especiales

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

También se debe revisar el modelo relacionado con la tabla `attributes`, porque `Attribute` puede generar confusión con atributos internos de Eloquent. Si se usa un nombre alternativo como `ProductAttribute`, debe declararse:

```php
protected $table = 'attributes';
```

## Relaciones recomendadas por módulo

### Auth

```text
Role hasMany User
Role belongsToMany Permission
Permission belongsToMany Role
User belongsTo Role
```

### Customers

```text
Customer belongsTo User
Customer hasMany CustomerAddress
Customer hasMany Order
Customer hasMany Invoice
Customer hasMany Payment
```

### Catalog

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
```

### Inventory

```text
Warehouse hasMany Inventory
Inventory belongsTo Warehouse
Inventory belongsTo Product
Inventory belongsTo ProductVariant
Inventory hasMany InventoryMovement
InventoryMovement belongsTo Inventory
InventoryMovement belongsTo User
```

### Purchases

```text
Supplier hasMany Purchase
Purchase belongsTo Supplier
Purchase belongsTo User
Purchase hasMany PurchaseItem
PurchaseItem belongsTo Purchase
PurchaseItem belongsTo Product
PurchaseItem belongsTo ProductVariant
```

### Sales

```text
Order belongsTo Customer
Order hasMany OrderItem
Order hasMany OrderStatusHistory
Order hasOne Invoice
Invoice belongsTo Order
Invoice belongsTo Customer
Invoice hasMany InvoiceItem
Invoice hasMany InvoiceStatusHistory
Invoice hasMany InvoicePayment
Invoice hasMany PaymentInstallment
```

### Payments

```text
Payment belongsTo Customer
Payment belongsTo PaymentMethod
Payment hasMany InvoicePayment
Payment hasMany PaymentTransaction
InvoicePayment belongsTo Invoice
InvoicePayment belongsTo Payment
PaymentInstallment belongsTo Invoice
```

### Shipping

```text
Shipment belongsTo Order
Shipment belongsTo ShippingCompany
Shipment belongsTo ShippingZone
Shipment hasMany ShipmentTracking
```

### Marketing

```text
Coupon hasMany CouponUsage
CouponUsage belongsTo Coupon
CouponUsage belongsTo Customer
CouponUsage belongsTo Order
Promotion belongsToMany Product
```

### Returns

```text
ReturnRequest belongsTo Order
ReturnRequest belongsTo Customer
ReturnRequest hasMany ReturnRequestItem
ReturnRequest hasOne Refund
Refund belongsTo ReturnRequest
Refund belongsTo PaymentMethod
```

### Support

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

### Settings

```text
Currency hasMany ExchangeRate
ExchangeRate belongsTo Currency
```

## Campos recomendados

Cada modelo debería revisar si necesita:

```php
protected $fillable = [];
```

o:

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

## Recomendaciones

1. Mantener un modelo por entidad principal.
2. Usar relaciones Eloquent en lugar de consultas manuales repetidas.
3. Definir `$fillable`, `$casts` y relaciones desde el inicio.
4. Usar nombres en inglés.
5. Evitar lógica compleja dentro del modelo.
6. Usar servicios para procesos como checkout, pagos, facturación e inventario.
7. Revisar manualmente los modelos de tablas pivote.
8. Agregar `SoftDeletes` en entidades donde no se deben borrar registros de forma permanente.

## Ejemplo base

```php
namespace App\Models;

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
