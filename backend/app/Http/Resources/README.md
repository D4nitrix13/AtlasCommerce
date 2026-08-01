# API Resources - Ecommerce Backend

Este directorio contiene los API Resources del backend Laravel. Los Resources transforman modelos Eloquent en respuestas JSON limpias, controladas y seguras para el frontend.

## Objetivo

Los API Resources sirven como capa de transformación entre los modelos internos y la respuesta enviada al frontend.

En este proyecto, el frontend Astro consumirá la API de Laravel, por lo que los Resources ayudan a mantener respuestas consistentes y fáciles de usar.

Ejemplo de flujo:

```text
Model
    ↓
API Resource
    ↓
JSON Response
    ↓
Astro Frontend
```

## Ubicación

```text
app/Http/Resources/
```

## Organización por módulos

Los Resources están organizados según los módulos principales del store:

```text
Resources/
├── Auth/
├── Customers/
├── Catalog/
├── Inventory/
├── Purchases/
├── Carts/
├── Sales/
├── Payments/
├── Shipping/
├── Marketing/
├── Taxes/
├── Returns/
├── Support/
├── System/
└── Settings/
```

## Responsabilidades de los Resources

Cada Resource debe encargarse de:

```text
- Definir qué campos se devuelven al frontend.
- Ocultar información sensible.
- Formatear fechas.
- Formatear montos.
- Incluir relaciones cargadas.
- Mantener una estructura JSON estable.
- Evitar exponer directamente toda la tabla.
```

## Lo que no debe hacerse en los Resources

Los Resources no deben contener lógica compleja de negocio.

Evitar:

```text
- Calcular procesos de facturación completos.
- Actualizar datos.
- Crear registros.
- Consultar muchas relaciones no cargadas.
- Ejecutar lógica de pago.
- Modificar inventario.
```

La lógica debe estar antes, en modelos, servicios o controladores.

## Convención de nombres

Cada Resource debe terminar en `Resource`.

Ejemplos:

```text
UserResource
CustomerResource
ProductResource
OrderResource
InvoiceResource
PaymentResource
ShipmentResource
SupportTicketResource
```

## Estructura básica

Ejemplo:

```php
namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'base_price' => $this->base_price,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

## Campos sensibles

Nunca devolver información sensible como:

```text
password
remember_token
password_reset_token
internal_tokens
secret_keys
private_gateway_response
```

Ejemplo en `UserResource`:

```php
return [
    'id' => $this->id,
    'role_id' => $this->role_id,
    'first_name' => $this->first_name,
    'last_name' => $this->last_name,
    'email' => $this->email,
    'phone' => $this->phone,
    'is_active' => $this->is_active,
];
```

No devolver:

```php
'password' => $this->password
```

## Relaciones cargadas

Usar `whenLoaded()` para evitar consultas innecesarias.

Ejemplo:

```php
return [
    'id' => $this->id,
    'name' => $this->name,
    'category' => new CategoryResource($this->whenLoaded('category')),
    'brand' => new BrandResource($this->whenLoaded('brand')),
];
```

Para colecciones:

```php
'items' => OrderItemResource::collection($this->whenLoaded('items')),
```

## Campos condicionales

Usar `when()` para mostrar campos solo cuando sea necesario.

Ejemplo:

```php
'profit_margin' => $this->when(
    $request->user()?->can('viewFinancialData', $this->resource),
    $this->profit_margin
),
```

## Fechas

Formatear fechas de manera consistente.

Ejemplo:

```php
'created_at' => $this->created_at?->toISOString(),
'updated_at' => $this->updated_at?->toISOString(),
```

También se puede usar un formato legible si el frontend lo necesita:

```php
'created_at_formatted' => $this->created_at?->format('d/m/Y H:i'),
```

## Montos monetarios

Para store, los montos deben devolverse de forma consistente.

Ejemplo:

```php
'subtotal' => $this->subtotal,
'discount' => $this->discount,
'tax' => $this->tax,
'total' => $this->total,
```

Si se necesita una versión visual:

```php
'total_formatted' => 'C$ ' . number_format($this->total, 2),
```

Sin embargo, es mejor que el frontend se encargue del formato visual cuando sea posible.

## Resources recomendados por módulo

### Auth

```text
RoleResource
PermissionResource
PermissionRoleResource
UserResource
```

### Customers

```text
CustomerResource
CustomerAddressResource
```

### Catalog

```text
CategoryResource
BrandResource
ProductResource
ProductImageResource
ProductVariantResource
ProductAttributeResource
ProductAttributeValueResource
VariantAttributeValueResource
```

### Inventory

```text
WarehouseResource
InventoryResource
InventoryMovementResource
```

### Purchases

```text
SupplierResource
PurchaseResource
PurchaseItemResource
```

### Carts

```text
CartResource
CartItemResource
WishlistResource
WishlistItemResource
```

### Sales

```text
OrderResource
OrderItemResource
OrderStatusHistoryResource
InvoiceResource
InvoiceItemResource
InvoiceStatusHistoryResource
```

### Payments

```text
PaymentMethodResource
PaymentResource
InvoicePaymentResource
PaymentInstallmentResource
PaymentTransactionResource
```

### Shipping

```text
ShippingCompanyResource
ShippingZoneResource
ShipmentResource
ShipmentTrackingResource
```

### Marketing

```text
CouponResource
CouponUsageResource
PromotionResource
ProductPromotionResource
```

### Taxes

```text
TaxResource
ProductTaxResource
```

### Returns

```text
ReturnRequestResource
ReturnRequestItemResource
RefundResource
```

### Support

```text
ProductReviewResource
ProductQuestionResource
SupportTicketResource
SupportTicketMessageResource
```

### System

```text
NotificationResource
AuditResource
```

### Settings

```text
StoreSettingResource
CurrencyResource
ExchangeRateResource
```

## Ejemplo para factura

```php
namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Customers\CustomerResource;
use App\Http\Resources\Payments\InvoicePaymentResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'total' => $this->total,
            'paid_amount' => $this->paid_amount,
            'pending_balance' => $this->pending_balance,
            'payment_status' => $this->payment_status,
            'issued_at' => $this->issued_at?->toISOString(),
            'payments' => InvoicePaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
```

## Ejemplo para pedido

```php
namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Customers\CustomerResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'total' => $this->total,
            'status' => $this->status,
            'ordered_at' => $this->ordered_at?->toISOString(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
```

## Recomendaciones

1. No devolver modelos directamente desde los controladores.
2. Usar Resources para todas las respuestas importantes.
3. Ocultar información sensible.
4. Usar `whenLoaded()` para relaciones.
5. Mantener nombres de campos consistentes.
6. Evitar lógica compleja dentro del Resource.
7. Mantener el JSON fácil de consumir por Astro.
8. Devolver fechas y montos de manera uniforme.
9. Usar colecciones de Resources para listados.
10. Mantener compatibilidad entre backend y frontend.

## Estado actual

Los API Resources ya fueron generados como estructura base. El siguiente paso es completar cada Resource con los campos reales de su tabla y las relaciones necesarias para cada pantalla del store.
