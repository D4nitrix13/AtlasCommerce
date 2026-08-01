# API Controllers - Ecommerce Backend

Este directorio contiene los controladores API del backend Laravel para el sistema ecommerce. Los controladores reciben las solicitudes HTTP del frontend, coordinan la lógica necesaria y devuelven respuestas JSON.

## Objetivo

Los controladores API son la puerta de entrada entre el frontend y el backend.

En este proyecto, el frontend será una aplicación Astro que consumirá endpoints de Laravel.

Ejemplo de flujo:

```text
Astro Frontend
    ↓
Laravel API Route
    ↓
API Controller
    ↓
Form Request / Service / Model
    ↓
API Resource
    ↓
JSON Response
```

## Ubicación

```text
app/Http/Controllers/Api/
```

## Organización por módulos

La estructura está organizada por módulos del ecommerce:

```text
Api/
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

## Responsabilidades de los controladores

Cada controlador debe encargarse de:

```text
- Recibir solicitudes HTTP.
- Validar datos usando Form Requests.
- Consultar modelos o servicios.
- Aplicar autorización con Policies o Gates.
- Devolver respuestas usando API Resources.
- Manejar respuestas HTTP adecuadas.
```

## Lo que no debe hacerse en los controladores

Los controladores no deben contener lógica compleja de negocio.

Evitar:

```text
- Cálculos largos dentro del método store o update.
- Procesos completos de checkout en el controlador.
- Lógica compleja de pagos.
- Actualización manual de inventario en múltiples tablas.
- Generación de facturas con muchas reglas dentro del controlador.
- Envío directo de correos desde el controlador.
```

Para eso se recomienda usar:

```text
app/Services/
app/Actions/
app/Jobs/
```

Ejemplos:

```text
CheckoutService
InvoiceService
PaymentService
InventoryService
ShippingService
CouponService
RefundService
```

## Convención de nombres

Los controladores deben usar nombres en singular o plural según el recurso, terminando en `Controller`.

Ejemplos:

```text
ProductController
CustomerController
OrderController
InvoiceController
PaymentController
ShipmentController
SupportTicketController
```

## Métodos API recomendados

Los controladores generados con `--api` normalmente contienen:

```php
index()
store()
show()
update()
destroy()
```

No incluyen:

```php
create()
edit()
```

porque esos métodos son más comunes en aplicaciones web con vistas Blade, no en APIs.

## Respuestas esperadas

Las respuestas deben ser JSON y preferiblemente usar API Resources.

Ejemplo para listar:

```php
public function index()
{
    $products = Product::query()
        ->latest()
        ->paginate(15);

    return ProductResource::collection($products);
}
```

Ejemplo para mostrar un registro:

```php
public function show(Product $product)
{
    return new ProductResource($product);
}
```

Ejemplo para crear:

```php
public function store(StoreProductRequest $request)
{
    $product = Product::create($request->validated());

    return new ProductResource($product);
}
```

Ejemplo para actualizar:

```php
public function update(UpdateProductRequest $request, Product $product)
{
    $product->update($request->validated());

    return new ProductResource($product);
}
```

Ejemplo para eliminar:

```php
public function destroy(Product $product)
{
    $product->delete();

    return response()->json([
        'message' => 'Product deleted successfully.',
    ]);
}
```

## Códigos HTTP recomendados

Usar códigos HTTP claros:

```text
200 OK                  Consulta exitosa
201 Created             Registro creado
204 No Content           Eliminación exitosa sin cuerpo
400 Bad Request          Solicitud incorrecta
401 Unauthorized         Usuario no autenticado
403 Forbidden            Usuario sin permisos
404 Not Found            Recurso no encontrado
422 Unprocessable Entity Error de validación
500 Server Error         Error interno
```

## Uso recomendado de Form Requests

No validar directamente en el controlador con `$request->validate()` cuando el módulo crezca. Es mejor usar Form Requests.

Ejemplo:

```text
app/Http/Requests/Catalog/StoreProductRequest.php
app/Http/Requests/Catalog/UpdateProductRequest.php
```

Ejemplo de controlador:

```php
public function store(StoreProductRequest $request)
{
    $product = Product::create($request->validated());

    return new ProductResource($product);
}
```

## Uso recomendado de Services

Para procesos complejos, el controlador debe delegar.

Ejemplo:

```php
public function store(StoreOrderRequest $request, CheckoutService $checkoutService)
{
    $order = $checkoutService->createOrder($request->validated());

    return new OrderResource($order);
}
```

Esto evita que el controlador crezca demasiado.

## Endpoints esperados por módulo

### Auth

```text
/api/auth/login
/api/auth/register
/api/auth/logout
/api/auth/me
/api/users
/api/roles
/api/permissions
```

### Customers

```text
/api/customers
/api/customer-addresses
```

### Catalog

```text
/api/categories
/api/brands
/api/products
/api/product-images
/api/product-variants
/api/attributes
```

### Inventory

```text
/api/warehouses
/api/inventories
/api/inventory-movements
```

### Purchases

```text
/api/suppliers
/api/purchases
/api/purchase-items
```

### Carts

```text
/api/carts
/api/cart-items
/api/wishlists
/api/wishlist-items
```

### Sales

```text
/api/orders
/api/order-items
/api/order-status-histories
/api/invoices
/api/invoice-items
/api/invoice-status-histories
```

### Payments

```text
/api/payment-methods
/api/payments
/api/invoice-payments
/api/payment-installments
/api/payment-transactions
```

### Shipping

```text
/api/shipping-companies
/api/shipping-zones
/api/shipments
/api/shipment-trackings
```

### Marketing

```text
/api/coupons
/api/coupon-usages
/api/promotions
```

### Returns

```text
/api/return-requests
/api/return-request-items
/api/refunds
```

### Support

```text
/api/product-reviews
/api/product-questions
/api/support-tickets
/api/support-ticket-messages
```

### System

```text
/api/notifications
/api/audits
```

### Settings

```text
/api/store-settings
/api/currencies
/api/exchange-rates
/api/taxes
```

## Rutas API

Las rutas deberían definirse en:

```text
routes/api.php
```

Ejemplo:

```php
use App\Http\Controllers\Api\Catalog\ProductController;

Route::apiResource('products', ProductController::class);
```

Para rutas protegidas:

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('products', ProductController::class);
});
```

## Recomendaciones

1. Mantener los controladores delgados.
2. Usar Form Requests para validación.
3. Usar API Resources para respuestas.
4. Usar Services para lógica compleja.
5. Usar Policies para permisos.
6. Evitar consultas repetidas dentro de muchos controladores.
7. Usar paginación en listados administrativos.
8. Usar filtros y búsqueda en endpoints grandes.
9. No devolver modelos directamente si el recurso será consumido por frontend.
10. Mantener respuestas JSON consistentes.

## Estructura recomendada de respuesta

Ejemplo de respuesta simple:

```json
{
    "data": {
        "id": 1,
        "name": "Product name"
    }
}
```

Ejemplo de respuesta paginada:

```json
{
    "data": [],
    "links": {},
    "meta": {}
}
```

## Estado actual

Los controladores API ya fueron generados como estructura base. El siguiente paso es completar cada controlador con:

```text
- Consultas reales.
- Validaciones.
- Recursos.
- Autorización.
- Manejo de filtros.
- Paginación.
- Lógica delegada a servicios.
```
