<?php

use App\Http\Controllers\Api\Auth\PermissionController;
use App\Http\Controllers\Api\Auth\PermissionRoleController;
use App\Http\Controllers\Api\Auth\RecoveryCodeController;
use App\Http\Controllers\Api\Auth\RoleController;
use App\Http\Controllers\Api\Auth\TwoFactorAuthController;
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
use App\Http\Controllers\Api\Payments\PaymentInstallmentController;
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
use App\Http\Controllers\Api\Settings\StoreSettingController;
use App\Http\Controllers\Api\Shipping\ShipmentController;
use App\Http\Controllers\Api\Shipping\ShipmentTrackingController;
use App\Http\Controllers\Api\Shipping\ShippingCompanyController;
use App\Http\Controllers\Api\Shipping\ShippingZoneController;
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
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    |
    | Security endpoints for account protection.
    | These routes are grouped under /auth/2fa but they are already protected
    | by the parent auth:sanctum middleware.
    |
    */

    Route::prefix('auth')->group(function (): void {
        Route::get('/2fa', [TwoFactorAuthController::class, 'show']);

        Route::post('/2fa/email/send', [TwoFactorAuthController::class, 'sendEmailOtp']);
        Route::post('/2fa/email/verify', [TwoFactorAuthController::class, 'verifyEmailOtp']);

        Route::get('/2fa/recovery-codes', [RecoveryCodeController::class, 'index']);
        Route::post('/2fa/recovery-codes/regenerate', [RecoveryCodeController::class, 'regenerate']);

        Route::post('/2fa/disable', [TwoFactorAuthController::class, 'disable']);
    });

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
    Route::apiResource('payment-installments', PaymentInstallmentController::class);
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
    Route::apiResource('shipments', ShipmentController::class);
    Route::apiResource('shipments.tracking', ShipmentTrackingController::class);

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
