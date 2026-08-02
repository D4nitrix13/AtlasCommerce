<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PermissionController;
use App\Http\Controllers\Api\Auth\PermissionRoleController;
use App\Http\Controllers\Api\Auth\RoleController;
use App\Http\Controllers\Api\Auth\UserController;
use App\Http\Controllers\Api\Carts\CartController;
use App\Http\Controllers\Api\Carts\CartItemController;
use App\Http\Controllers\Api\Carts\WishlistController;
use App\Http\Controllers\Api\Carts\WishlistItemController;
use App\Http\Controllers\Api\Catalog\BrandController;
use App\Http\Controllers\Api\Catalog\CategoryController;
use App\Http\Controllers\Api\Catalog\ProductAttributeController;
use App\Http\Controllers\Api\Catalog\ProductAttributeValueController;
use App\Http\Controllers\Api\Catalog\ProductController;
use App\Http\Controllers\Api\Catalog\ProductImageController;
use App\Http\Controllers\Api\Catalog\ProductVariantController;
use App\Http\Controllers\Api\Catalog\VariantAttributeValueController;
use App\Http\Controllers\Api\Customers\CustomerAddressController;
use App\Http\Controllers\Api\Customers\CustomerController;
use App\Http\Controllers\Api\Inventory\InventoryController;
use App\Http\Controllers\Api\Inventory\InventoryMovementController;
use App\Http\Controllers\Api\Inventory\WarehouseController;
use App\Http\Controllers\Api\Marketing\CouponController;
use App\Http\Controllers\Api\Marketing\CouponUsageController;
use App\Http\Controllers\Api\Marketing\ProductPromotionController;
use App\Http\Controllers\Api\Marketing\PromotionController;
use App\Http\Controllers\Api\Payments\InvoicePaymentController;
use App\Http\Controllers\Api\Payments\PaymentController;
use App\Http\Controllers\Api\Payments\PaymentMethodController;
use App\Http\Controllers\Api\Payments\PaymentTransactionController;
use App\Http\Controllers\Api\Purchases\PurchaseController;
use App\Http\Controllers\Api\Purchases\PurchaseItemController;
use App\Http\Controllers\Api\Purchases\SupplierController;
use App\Http\Controllers\Api\Returns\RefundController;
use App\Http\Controllers\Api\Returns\ReturnRequestController;
use App\Http\Controllers\Api\Returns\ReturnRequestItemController;
use App\Http\Controllers\Api\Sales\InvoiceController;
use App\Http\Controllers\Api\Sales\InvoiceItemController;
use App\Http\Controllers\Api\Sales\InvoiceStatusHistoryController;
use App\Http\Controllers\Api\Sales\OrderController;
use App\Http\Controllers\Api\Sales\OrderItemController;
use App\Http\Controllers\Api\Sales\OrderStatusHistoryController;
use App\Http\Controllers\Api\Settings\CurrencyController;
use App\Http\Controllers\Api\Settings\ExchangeRateController;
use App\Http\Controllers\Api\Shipping\ShipmentController;
use App\Http\Controllers\Api\Shipping\ShippingCompanyController;
use App\Http\Controllers\Api\Shipping\ShippingZoneController;
use App\Http\Controllers\Api\Storefront\ProductController as StorefrontProductController;
use App\Http\Controllers\Api\Stores\StoreSettingController;
use App\Http\Controllers\Api\Support\ProductQuestionController;
use App\Http\Controllers\Api\Support\ProductReviewController;
use App\Http\Controllers\Api\Support\SupportTicketController;
use App\Http\Controllers\Api\Support\SupportTicketMessageController;
use App\Http\Controllers\Api\System\AuditController;
use App\Http\Controllers\Api\System\NotificationController;
use App\Http\Controllers\Api\Taxes\ProductTaxController;
use App\Http\Controllers\Api\Taxes\TaxController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Protected API Routes
|--------------------------------------------------------------------------
|
| All routes in this file require a valid Laravel Sanctum token.
| The frontend must send the token using:
|
| Authorization: Bearer {token}
|
| Do not include "/api" here. Laravel already applies the API prefix
| to routes defined in this file.
|
*/

/*
|--------------------------------------------------------------------------
| Auth (public)
|--------------------------------------------------------------------------
|
| Login es público (devuelve token Sanctum). Logout y /me requieren
| token válido vía `auth:sanctum` (definidos dentro del grupo más abajo).
|
| Todas las rutas auth están envueltas en `store.domain` para resolver
| la tienda activa y validar que el usuario tenga acceso a ella via
| tabla pivot `user_stores`.
|
*/

Route::middleware(['store.domain'])->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->name('auth.login');

    Route::post('auth/register', [AuthController::class, 'register'])
        ->name('auth.register');
});

Route::middleware(['store.domain', 'auth:sanctum'])->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout'])
        ->name('auth.logout');

    Route::get('auth/me', [AuthController::class, 'me'])
        ->name('auth.me');
});

Route::middleware('auth:sanctum')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Auth and Access Control
    |--------------------------------------------------------------------------
    |
    | Users, roles, permissions and role-permission assignments.
    | These endpoints are part of the internal administration area.
    |
    */

    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('permissions', PermissionController::class);
    Route::apiResource('permission-roles', PermissionRoleController::class);

    /*
    |--------------------------------------------------------------------------
    | Catalog
    |--------------------------------------------------------------------------
    |
    | Product catalog management: brands, categories, products, images,
    | variants and product attributes.
    |
    */

    Route::apiResource('brands', BrandController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('products.images', ProductImageController::class);
    Route::apiResource('products.variants', ProductVariantController::class);
    Route::apiResource('products.attributes', ProductAttributeController::class);
    Route::apiResource('product-attribute-values', ProductAttributeValueController::class);
    Route::apiResource('variant-attribute-values', VariantAttributeValueController::class);

    /*
    |--------------------------------------------------------------------------
    | Customers
    |--------------------------------------------------------------------------
    |
    | Customer profiles and customer addresses.
    |
    */

    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('customers.addresses', CustomerAddressController::class);

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    |
    | Warehouses, current stock and inventory movements.
    |
    */

    Route::apiResource('warehouses', WarehouseController::class);
    Route::apiResource('inventories', InventoryController::class);
    Route::apiResource('inventory-movements', InventoryMovementController::class);

    /*
    |--------------------------------------------------------------------------
    | Purchases
    |--------------------------------------------------------------------------
    |
    | Suppliers, purchases and purchase items.
    |
    */

    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('purchases', PurchaseController::class);
    Route::apiResource('purchases.items', PurchaseItemController::class);

    /*
    |--------------------------------------------------------------------------
    | Carts and Wishlists
    |--------------------------------------------------------------------------
    |
    | Shopping carts, cart items, wishlists and wishlist items.
    |
    */

    Route::apiResource('carts', CartController::class);
    Route::apiResource('carts.items', CartItemController::class);
    Route::apiResource('wishlists', WishlistController::class);
    Route::apiResource('wishlists.items', WishlistItemController::class);

    /*
    |--------------------------------------------------------------------------
    | Sales
    |--------------------------------------------------------------------------
    |
    | Orders, invoices, order items, invoice items and status histories.
    |
    */

    Route::apiResource('orders', OrderController::class);
    Route::apiResource('orders.items', OrderItemController::class);
    Route::apiResource('orders.status-histories', OrderStatusHistoryController::class);
    Route::apiResource('invoices', InvoiceController::class);
    Route::apiResource('invoices.items', InvoiceItemController::class);
    Route::apiResource('invoices.status-histories', InvoiceStatusHistoryController::class);

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | Payment methods, payments, installments, transactions and invoice payments.
    |
    */

    Route::apiResource('payments', PaymentController::class);
    Route::apiResource('payment-methods', PaymentMethodController::class);
    Route::apiResource('payment-transactions', PaymentTransactionController::class);
    Route::apiResource('invoice-payments', InvoicePaymentController::class);

    /*
    |--------------------------------------------------------------------------
    | Shipping
    |--------------------------------------------------------------------------
    |
    | Shipping companies, zones, shipments and tracking events.
    |
    */

    Route::apiResource('shipping-companies', ShippingCompanyController::class);
    Route::apiResource('shipping-zones', ShippingZoneController::class);

    // PHASE 10: transiciones de estado de shipment (deben ir antes del apiResource
    // para que las rutas /shipments/{shipment}/... no colisionen con show/update).
    Route::post('shipments/{shipment}/ship', [ShipmentController::class, 'markAsShipped'])
        ->middleware('permission:shipping.shipments.ship')
        ->name('shipments.ship');
    Route::post('shipments/{shipment}/deliver', [ShipmentController::class, 'markAsDelivered'])
        ->middleware('permission:shipping.shipments.deliver')
        ->name('shipments.deliver');
    Route::post('shipments/{shipment}/cancel', [ShipmentController::class, 'cancel'])
        ->middleware('permission:shipping.shipments.cancel')
        ->name('shipments.cancel');

    Route::apiResource('shipments', ShipmentController::class);

    /*
    |--------------------------------------------------------------------------
    | Marketing
    |--------------------------------------------------------------------------
    |
    | Coupons, coupon usage, promotions and product promotions.
    |
    */

    Route::apiResource('coupons', CouponController::class);
    Route::apiResource('coupon-usages', CouponUsageController::class);
    Route::apiResource('promotions', PromotionController::class);
    Route::apiResource('product-promotions', ProductPromotionController::class);

    /*
    |--------------------------------------------------------------------------
    | Taxes
    |--------------------------------------------------------------------------
    |
    | Tax definitions and product-tax relationships.
    |
    */

    Route::apiResource('taxes', TaxController::class);
    Route::apiResource('product-taxes', ProductTaxController::class);

    /*
    |--------------------------------------------------------------------------
    | Returns
    |--------------------------------------------------------------------------
    |
    | Return requests, returned items and refunds.
    |
    */

    // PHASE 11: transiciones de return request (deben ir antes del apiResource).
    Route::post('return-requests/{return_request}/approve', [ReturnRequestController::class, 'approve'])
        ->middleware('permission:returns.return_requests.approve')
        ->name('return-requests.approve');
    Route::post('return-requests/{return_request}/reject', [ReturnRequestController::class, 'reject'])
        ->middleware('permission:returns.return_requests.reject')
        ->name('return-requests.reject');
    Route::post('return-requests/{return_request}/cancel', [ReturnRequestController::class, 'cancel'])
        ->name('return-requests.cancel');

    // PHASE 11: transiciones de refund.
    Route::post('refunds/{refund}/process', [RefundController::class, 'process'])
        ->middleware('permission:payments.refunds.process')
        ->name('refunds.process');
    Route::post('refunds/{refund}/fail', [RefundController::class, 'fail'])
        ->name('refunds.fail');

    Route::apiResource('return-requests', ReturnRequestController::class);
    Route::apiResource('return-request-items', ReturnRequestItemController::class);
    Route::apiResource('refunds', RefundController::class);

    /*
    |--------------------------------------------------------------------------
    | Support
    |--------------------------------------------------------------------------
    |
    | Support tickets, ticket messages, product reviews and product questions.
    |
    */

    Route::apiResource('support-tickets', SupportTicketController::class);
    Route::apiResource('support-ticket-messages', SupportTicketMessageController::class);
    Route::apiResource('product-reviews', ProductReviewController::class);
    Route::apiResource('product-questions', ProductQuestionController::class);

    /*
    |--------------------------------------------------------------------------
    | System
    |--------------------------------------------------------------------------
    |
    | Audits are read-only from the API. Notifications can be listed, viewed
    | and updated.
    |
    */

    Route::apiResource('audits', AuditController::class)
        ->only(['index', 'show']);

    Route::apiResource('notifications', NotificationController::class)
        ->only(['index', 'show', 'update']);

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    |
    | Store configuration, currencies and exchange rates.
    |
    */

    Route::apiResource('currencies', CurrencyController::class);
    Route::apiResource('exchange-rates', ExchangeRateController::class);
    Route::apiResource('store-settings', StoreSettingController::class);
});

/*
|--------------------------------------------------------------------------
| Public Storefront Routes
|--------------------------------------------------------------------------
|
| Endpoints for the public storefront. Resolved by domain via
| ResolveStoreFromDomain middleware. No authentication required.
|
*/

Route::middleware(['store.domain'])->prefix('storefront')->group(function (): void {
    Route::get('/products', [StorefrontProductController::class, 'index']);
    Route::get('/products/{slug}', [StorefrontProductController::class, 'show']);
    Route::get('/products/{slug}/price', [StorefrontProductController::class, 'price']);
});
