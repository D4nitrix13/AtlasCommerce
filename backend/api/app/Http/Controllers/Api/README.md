# API Controllers - Ecommerce Backend

Este directorio contiene los controladores API del backend Laravel para el sistema store. Los controladores reciben solicitudes HTTP del frontend, coordinan la ejecución necesaria y devuelven respuestas JSON.

El frontend principal del proyecto será una aplicación Astro que consumirá endpoints de Laravel mediante API.

## Objetivo del directorio

Los controladores API son la puerta de entrada entre el frontend y el backend.

Flujo recomendado:

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

El controlador no debe contener toda la lógica del sistema. Su trabajo principal es recibir la solicitud, delegar el trabajo y devolver una respuesta clara.

## Estructura principal

La ubicación oficial de los controladores API es:

```text
app/Http/Controllers/Api/
```

Estructura modular recomendada:

```text
app/Http/Controllers/
├── Api/
│   ├── Auth/
│   ├── Carts/
│   ├── Catalog/
│   ├── Customers/
│   ├── Inventory/
│   ├── Marketing/
│   ├── Payments/
│   ├── Purchases/
│   ├── Returns/
│   ├── Sales/
│   ├── Settings/
│   ├── Shipping/
│   ├── Support/
│   ├── System/
│   └── Taxes/
└── Controller.php
```

Esta estructura mantiene separados los módulos del store y facilita encontrar cada controlador.

## Nota sobre la carpeta Auth fuera de Api

No se recomienda tener esta estructura para un backend API:

```text
app/Http/Controllers/Auth/
```

Si el proyecto funciona como API para un frontend separado, los controladores de autenticación deben estar dentro de:

```text
app/Http/Controllers/Api/Auth/
```

La carpeta `app/Http/Controllers/Auth/` solo tendría sentido si el proyecto usa autenticación web tradicional con vistas Blade, por ejemplo login, registro o recuperación de contraseña renderizados por Laravel.

Para este proyecto, la estructura correcta es:

```text
app/Http/Controllers/Api/Auth/
├── UserController.php
├── RoleController.php
├── PermissionController.php
├── PermissionRoleController.php
├── TwoFactorAuthController.php
├── TotpController.php
├── PasskeyController.php
└── RecoveryCodeController.php
```

## Corrección recomendada para Auth

Si existen estos archivos:

```text
app/Http/Controllers/Auth/PasskeyController.php
app/Http/Controllers/Auth/RecoveryCodeController.php
app/Http/Controllers/Auth/TotpController.php
app/Http/Controllers/Auth/TwoFactorAuthController.php
```

Deben moverse a:

```text
app/Http/Controllers/Api/Auth/
```

Después de moverlos, se debe actualizar el namespace.

Antes:

```php
namespace App\Http\Controllers\Auth;
```

Después:

```php
namespace App\Http\Controllers\Api\Auth;
```

También se deben revisar las rutas en `routes/api.php` para importar los controladores desde el namespace correcto.

Ejemplo:

```php
use App\Http\Controllers\Api\Auth\TwoFactorAuthController;
use App\Http\Controllers\Api\Auth\TotpController;
use App\Http\Controllers\Api\Auth\PasskeyController;
use App\Http\Controllers\Api\Auth\RecoveryCodeController;
```

Después de mover los controladores, ejecutar:

```bash
composer dump-autoload
```

Si se usa Docker:

```bash
docker compose exec app composer dump-autoload
```

## Módulo API Auth

Ubicación:

```text
app/Http/Controllers/Api/Auth/
```

Controladores actuales y recomendados:

```text
UserController.php
RoleController.php
PermissionController.php
PermissionRoleController.php
TwoFactorAuthController.php
TotpController.php
PasskeyController.php
RecoveryCodeController.php
```

Responsabilidades principales:

```text
- Login
- Logout
- Registro
- Usuario autenticado
- Gestión de usuarios
- Gestión de roles
- Gestión de permisos
- Configuración de 2FA
- Validación de OTP por correo
- Activación de TOTP
- Registro de passkeys
- Códigos de recuperación
```

Endpoints sugeridos:

```text
POST   /api/auth/login
POST   /api/auth/register
POST   /api/auth/logout
GET    /api/auth/me

GET    /api/users
POST   /api/users
GET    /api/users/{user}
PUT    /api/users/{user}
DELETE /api/users/{user}

GET    /api/roles
POST   /api/roles
GET    /api/roles/{role}
PUT    /api/roles/{role}
DELETE /api/roles/{role}

GET    /api/permissions
POST   /api/permissions
GET    /api/permissions/{permission}
PUT    /api/permissions/{permission}
DELETE /api/permissions/{permission}
```

Endpoints sugeridos para 2FA:

```text
GET    /api/auth/2fa
POST   /api/auth/2fa/email/send
POST   /api/auth/2fa/email/verify
POST   /api/auth/2fa/totp/setup
POST   /api/auth/2fa/totp/verify
POST   /api/auth/2fa/passkeys/register/options
POST   /api/auth/2fa/passkeys/register
GET    /api/auth/2fa/recovery-codes
POST   /api/auth/2fa/recovery-codes/regenerate
POST   /api/auth/2fa/disable
```

## Módulo API Customers

Ubicación:

```text
app/Http/Controllers/Api/Customers/
```

Controladores:

```text
CustomerController.php
CustomerAddressController.php
```

Responsabilidades principales:

```text
- Gestión de clientes
- Direcciones de envío
- Direcciones de facturación
- Relación entre usuarios y clientes
```

Endpoints sugeridos:

```text
GET    /api/customers
POST   /api/customers
GET    /api/customers/{customer}
PUT    /api/customers/{customer}
DELETE /api/customers/{customer}

GET    /api/customer-addresses
POST   /api/customer-addresses
GET    /api/customer-addresses/{customerAddress}
PUT    /api/customer-addresses/{customerAddress}
DELETE /api/customer-addresses/{customerAddress}
```

## Módulo API Catalog

Ubicación:

```text
app/Http/Controllers/Api/Catalog/
```

Controladores:

```text
BrandController.php
CategoryController.php
ProductController.php
ProductAttributeController.php
ProductAttributeValueController.php
ProductImageController.php
ProductVariantController.php
VariantAttributeValueController.php
```

Responsabilidades principales:

```text
- Productos
- Categorías
- Marcas
- Imágenes de producto
- Variantes de producto
- Atributos
- Valores de atributos
```

Endpoints sugeridos:

```text
GET    /api/categories
POST   /api/categories
GET    /api/categories/{category}
PUT    /api/categories/{category}
DELETE /api/categories/{category}

GET    /api/brands
POST   /api/brands
GET    /api/brands/{brand}
PUT    /api/brands/{brand}
DELETE /api/brands/{brand}

GET    /api/products
POST   /api/products
GET    /api/products/{product}
PUT    /api/products/{product}
DELETE /api/products/{product}

GET    /api/product-images
POST   /api/product-images
GET    /api/product-variants
POST   /api/product-variants
GET    /api/product-attributes
POST   /api/product-attributes
```

## Módulo API Inventory

Ubicación:

```text
app/Http/Controllers/Api/Inventory/
```

Controladores:

```text
InventoryController.php
InventoryMovementController.php
WarehouseController.php
```

Responsabilidades principales:

```text
- Almacenes
- Stock actual
- Stock reservado
- Stock mínimo
- Movimientos de inventario
```

Endpoints sugeridos:

```text
GET    /api/warehouses
POST   /api/warehouses
GET    /api/warehouses/{warehouse}
PUT    /api/warehouses/{warehouse}
DELETE /api/warehouses/{warehouse}

GET    /api/inventories
POST   /api/inventories
GET    /api/inventory-movements
POST   /api/inventory-movements
```

## Módulo API Purchases

Ubicación:

```text
app/Http/Controllers/Api/Purchases/
```

Controladores:

```text
SupplierController.php
PurchaseController.php
PurchaseItemController.php
```

Responsabilidades principales:

```text
- Proveedores
- Compras a proveedores
- Detalles de compras
- Costos de adquisición
```

Endpoints sugeridos:

```text
GET    /api/suppliers
POST   /api/suppliers
GET    /api/suppliers/{supplier}
PUT    /api/suppliers/{supplier}
DELETE /api/suppliers/{supplier}

GET    /api/purchases
POST   /api/purchases
GET    /api/purchases/{purchase}
PUT    /api/purchases/{purchase}
DELETE /api/purchases/{purchase}

GET    /api/purchase-items
POST   /api/purchase-items
```

## Módulo API Carts

Ubicación:

```text
app/Http/Controllers/Api/Carts/
```

Controladores:

```text
CartController.php
CartItemController.php
WishlistController.php
WishlistItemController.php
```

Responsabilidades principales:

```text
- Carritos de compra
- Productos agregados al carrito
- Listas de deseos
- Productos favoritos
```

Endpoints sugeridos:

```text
GET    /api/carts
POST   /api/carts
GET    /api/cart-items
POST   /api/cart-items
PUT    /api/cart-items/{cartItem}
DELETE /api/cart-items/{cartItem}

GET    /api/wishlists
POST   /api/wishlists
GET    /api/wishlist-items
POST   /api/wishlist-items
DELETE /api/wishlist-items/{wishlistItem}
```

## Módulo API Sales

Ubicación:

```text
app/Http/Controllers/Api/Sales/
```

Controladores:

```text
OrderController.php
OrderItemController.php
OrderStatusHistoryController.php
InvoiceController.php
InvoiceItemController.php
InvoiceStatusHistoryController.php
```

Responsabilidades principales:

```text
- Pedidos
- Detalles de pedidos
- Estados de pedidos
- Facturas
- Detalles de facturas
- Estados de facturas
```

Endpoints sugeridos:

```text
GET    /api/orders
POST   /api/orders
GET    /api/orders/{order}
PUT    /api/orders/{order}
DELETE /api/orders/{order}

GET    /api/order-items
POST   /api/order-items
GET    /api/order-status-histories

GET    /api/invoices
POST   /api/invoices
GET    /api/invoices/{invoice}
PUT    /api/invoices/{invoice}
DELETE /api/invoices/{invoice}

GET    /api/invoice-items
POST   /api/invoice-items
GET    /api/invoice-status-histories
```

## Módulo API Payments

Ubicación:

```text
app/Http/Controllers/Api/Payments/
```

Controladores:

```text
PaymentController.php
PaymentMethodController.php
InvoicePaymentController.php
PaymentInstallmentController.php
PaymentTransactionController.php
```

Responsabilidades principales:

```text
- Métodos de pago
- Pagos
- Abonos a facturas
- Cuotas de pago
- Transacciones de pago
```

Endpoints sugeridos:

```text
GET    /api/payment-methods
POST   /api/payment-methods
GET    /api/payment-methods/{paymentMethod}
PUT    /api/payment-methods/{paymentMethod}
DELETE /api/payment-methods/{paymentMethod}

GET    /api/payments
POST   /api/payments
GET    /api/payments/{payment}
PUT    /api/payments/{payment}
DELETE /api/payments/{payment}

GET    /api/invoice-payments
POST   /api/invoice-payments

GET    /api/payment-installments
POST   /api/payment-installments

GET    /api/payment-transactions
POST   /api/payment-transactions
```

## Módulo API Shipping

Ubicación:

```text
app/Http/Controllers/Api/Shipping/
```

Controladores:

```text
ShippingCompanyController.php
ShippingZoneController.php
ShipmentController.php
ShipmentTrackingController.php
```

Responsabilidades principales:

```text
- Empresas de envío
- Zonas de envío
- Envíos
- Seguimiento de envíos
```

Endpoints sugeridos:

```text
GET    /api/shipping-companies
POST   /api/shipping-companies
GET    /api/shipping-zones
POST   /api/shipping-zones
GET    /api/shipments
POST   /api/shipments
GET    /api/shipment-trackings
POST   /api/shipment-trackings
```

## Módulo API Marketing

Ubicación:

```text
app/Http/Controllers/Api/Marketing/
```

Controladores:

```text
CouponController.php
CouponUsageController.php
PromotionController.php
ProductPromotionController.php
```

Responsabilidades principales:

```text
- Cupones
- Uso de cupones
- Promociones
- Relación entre productos y promociones
```

Endpoints sugeridos:

```text
GET    /api/coupons
POST   /api/coupons
GET    /api/coupons/{coupon}
PUT    /api/coupons/{coupon}
DELETE /api/coupons/{coupon}

GET    /api/coupon-usages
POST   /api/coupon-usages

GET    /api/promotions
POST   /api/promotions
GET    /api/product-promotions
POST   /api/product-promotions
```

## Módulo API Taxes

Ubicación:

```text
app/Http/Controllers/Api/Taxes/
```

Controladores:

```text
TaxController.php
ProductTaxController.php
```

Responsabilidades principales:

```text
- Impuestos
- Relación entre productos e impuestos
```

Endpoints sugeridos:

```text
GET    /api/taxes
POST   /api/taxes
GET    /api/taxes/{tax}
PUT    /api/taxes/{tax}
DELETE /api/taxes/{tax}

GET    /api/product-taxes
POST   /api/product-taxes
```

## Módulo API Returns

Ubicación:

```text
app/Http/Controllers/Api/Returns/
```

Controladores:

```text
ReturnRequestController.php
ReturnRequestItemController.php
RefundController.php
```

Responsabilidades principales:

```text
- Solicitudes de devolución
- Productos devueltos
- Reembolsos
```

Endpoints sugeridos:

```text
GET    /api/return-requests
POST   /api/return-requests
GET    /api/return-request-items
POST   /api/return-request-items
GET    /api/refunds
POST   /api/refunds
```

## Módulo API Support

Ubicación:

```text
app/Http/Controllers/Api/Support/
```

Controladores:

```text
ProductReviewController.php
ProductQuestionController.php
SupportTicketController.php
SupportTicketMessageController.php
```

Responsabilidades principales:

```text
- Reseñas de productos
- Preguntas de productos
- Tickets de soporte
- Mensajes de soporte
```

Endpoints sugeridos:

```text
GET    /api/product-reviews
POST   /api/product-reviews
GET    /api/product-questions
POST   /api/product-questions
GET    /api/support-tickets
POST   /api/support-tickets
GET    /api/support-ticket-messages
POST   /api/support-ticket-messages
```

## Módulo API System

Ubicación:

```text
app/Http/Controllers/Api/System/
```

Controladores:

```text
AuditController.php
NotificationController.php
```

Responsabilidades principales:

```text
- Auditoría
- Notificaciones internas
- Registro de acciones importantes
```

Endpoints sugeridos:

```text
GET    /api/audits
GET    /api/notifications
PUT    /api/notifications/{notification}
DELETE /api/notifications/{notification}
```

## Módulo API Settings

Ubicación:

```text
app/Http/Controllers/Api/Settings/
```

Controladores:

```text
StoreSettingController.php
CurrencyController.php
ExchangeRateController.php
```

Responsabilidades principales:

```text
- Configuración general de tienda
- Monedas
- Tipos de cambio
```

Endpoints sugeridos:

```text
GET    /api/store-settings
PUT    /api/store-settings/{storeSetting}
GET    /api/currencies
POST   /api/currencies
GET    /api/exchange-rates
POST   /api/exchange-rates
```

## Responsabilidades permitidas en controladores

Cada controlador debe encargarse de:

```text
- Recibir solicitudes HTTP.
- Validar datos usando Form Requests.
- Consultar modelos o servicios.
- Aplicar autorización con Policies o Gates.
- Devolver respuestas usando API Resources.
- Manejar respuestas HTTP adecuadas.
- Delegar procesos complejos a servicios o acciones.
```

## Responsabilidades prohibidas en controladores

Los controladores no deben contener lógica compleja de negocio.

Evitar:

```text
- Cálculos largos dentro de store o update.
- Procesos completos de checkout dentro del controlador.
- Lógica compleja de pagos.
- Actualización manual de inventario en múltiples tablas.
- Generación de facturas con muchas reglas dentro del controlador.
- Envío directo de correos desde el controlador.
- Implementación completa de TOTP o WebAuthn dentro del controlador.
```

Para estos casos se recomienda usar:

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
EmailOtpService
TotpService
PasskeyService
RecoveryCodeService
```

## Convención de nombres para controladores

Los controladores deben usar nombres en PascalCase y terminar en `Controller`.

Ejemplos:

```text
ProductController
CustomerController
OrderController
InvoiceController
PaymentController
ShipmentController
SupportTicketController
TwoFactorAuthController
RecoveryCodeController
```

Los namespaces deben coincidir con la carpeta.

Ejemplo para productos:

```php
namespace App\Http\Controllers\Api\Catalog;
```

Ejemplo para 2FA:

```php
namespace App\Http\Controllers\Api\Auth;
```

Ejemplo para pagos:

```php
namespace App\Http\Controllers\Api\Payments;
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

Los métodos `create()` y `edit()` son más comunes en aplicaciones web con vistas Blade, no en APIs JSON.

## Uso recomendado de Form Requests

No se recomienda validar directamente en el controlador con `$request->validate()` cuando el módulo crece.

Es mejor usar Form Requests.

Ejemplo:

```text
app/Http/Requests/Catalog/StoreProductRequest.php
app/Http/Requests/Catalog/UpdateProductRequest.php
```

Ejemplo de uso:

```php
public function store(StoreProductRequest $request)
{
    $product = Product::create($request->validated());

    return new ProductResource($product);
}
```

## Uso recomendado de API Resources

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

Esto evita que el controlador crezca demasiado y mantiene la lógica de negocio en una capa reutilizable.

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

## Estructura de respuesta JSON

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

Ejemplo de respuesta con mensaje:

```json
{
    "message": "Product deleted successfully."
}
```

Ejemplo de respuesta de error:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "name": [
            "The name field is required."
        ]
    }
}
```

## Definición de rutas API

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

Para rutas agrupadas por módulo:

```php
use App\Http\Controllers\Api\Catalog\ProductController;
use App\Http\Controllers\Api\Catalog\CategoryController;
use App\Http\Controllers\Api\Catalog\BrandController;

Route::middleware('auth:sanctum')->prefix('catalog')->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('brands', BrandController::class);
});
```

Para rutas de autenticación:

```php
use App\Http\Controllers\Api\Auth\TwoFactorAuthController;
use App\Http\Controllers\Api\Auth\TotpController;
use App\Http\Controllers\Api\Auth\PasskeyController;
use App\Http\Controllers\Api\Auth\RecoveryCodeController;

Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::get('2fa', [TwoFactorAuthController::class, 'show']);
    Route::post('2fa/email/send', [TwoFactorAuthController::class, 'sendEmailCode']);
    Route::post('2fa/email/verify', [TwoFactorAuthController::class, 'verifyEmailCode']);

    Route::post('2fa/totp/setup', [TotpController::class, 'setup']);
    Route::post('2fa/totp/verify', [TotpController::class, 'verify']);

    Route::post('2fa/passkeys/register/options', [PasskeyController::class, 'registrationOptions']);
    Route::post('2fa/passkeys/register', [PasskeyController::class, 'register']);

    Route::get('2fa/recovery-codes', [RecoveryCodeController::class, 'index']);
    Route::post('2fa/recovery-codes/regenerate', [RecoveryCodeController::class, 'regenerate']);
});
```

## Comandos para crear controladores API

Crear controladores dentro de subcarpetas debe hacerse desde `Api/`.

Correcto:

```bash
php artisan make:controller Api/Auth/TwoFactorAuthController --api
php artisan make:controller Api/Catalog/ProductController --api
php artisan make:controller Api/Sales/OrderController --api
```

Incorrecto para este proyecto API:

```bash
php artisan make:controller Auth/TwoFactorAuthController --api
php artisan make:controller Catalog/ProductController --api
php artisan make:controller Sales/OrderController --api
```

El segundo grupo crea controladores fuera de `Api`, lo cual genera una estructura menos clara para este backend.

## Proceso seguro para mover controladores

Si se mueven controladores ya existentes:

```text
1. Mover el archivo a la carpeta correcta.
2. Cambiar el namespace del controlador.
3. Actualizar imports en routes/api.php.
4. Actualizar cualquier referencia en tests.
5. Ejecutar composer dump-autoload.
6. Ejecutar php artisan route:list.
7. Probar los endpoints principales.
```

Buscar namespaces antiguos:

```bash
grep -R "App\\Http\\Controllers\\Auth" -n app routes tests
```

Buscar controladores fuera de Api:

```bash
find app/Http/Controllers -type f -name "*Controller.php" | sort
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

## Recomendaciones finales

1. Mantener todos los controladores de API dentro de `app/Http/Controllers/Api`.
2. Evitar duplicar carpetas de autenticación entre `Controllers/Auth` y `Controllers/Api/Auth`.
3. Usar Form Requests para validación.
4. Usar API Resources para respuestas JSON.
5. Mantener los controladores delgados.
6. Delegar lógica compleja a servicios, acciones o jobs.
7. Usar Policies o Gates para autorización.
8. Usar paginación en listados administrativos.
9. Agregar filtros y búsqueda en endpoints grandes.
10. Evitar devolver modelos directamente si la respuesta será consumida por frontend.
11. Mantener respuestas JSON consistentes.
12. Mantener nombres en inglés y namespaces coherentes con las carpetas.
13. Ejecutar `php artisan route:list` después de mover controladores.
14. Documentar cada nuevo controlador en este README.

## Estado recomendado del directorio

La estructura recomendada final es:

```text
app/Http/Controllers/
├── Api/
│   ├── Auth/
│   ├── Carts/
│   ├── Catalog/
│   ├── Customers/
│   ├── Inventory/
│   ├── Marketing/
│   ├── Payments/
│   ├── Purchases/
│   ├── Returns/
│   ├── Sales/
│   ├── Settings/
│   ├── Shipping/
│   ├── Support/
│   ├── System/
│   ├── Taxes/
│   └── README.md
└── Controller.php
```

No se recomienda mantener esta carpeta si no se usan vistas Blade:

```text
app/Http/Controllers/Auth/
```

Para este proyecto, los controladores de 2FA deben vivir en:

```text
app/Http/Controllers/Api/Auth/
```
