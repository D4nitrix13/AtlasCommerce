<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PasswordResetTokenController;
use App\Http\Controllers\Api\Auth\RecoveryCodeController;
use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Http\Controllers\Api\Auth\TotpController;
use App\Http\Controllers\Api\Carts\CartController;
use App\Http\Controllers\Api\Carts\CartItemController;
use App\Http\Controllers\Api\Customers\AccountDataController;
use App\Http\Controllers\Api\Payments\PaymentController;
use App\Http\Controllers\Api\Returns\RefundController;
use App\Http\Controllers\Api\Returns\ReturnRequestController;
use App\Http\Controllers\Api\Returns\ReturnRequestItemController;
use App\Http\Controllers\Api\Sales\OrderController;
use App\Http\Controllers\Api\Shipping\ShipmentController;
use App\Http\Controllers\Api\Storefront\AssetController as StorefrontAssetController;
use App\Http\Controllers\Api\Storefront\ProductController as StorefrontProductController;
use App\Http\Controllers\Api\Storefront\ThemeController as StorefrontThemeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Protected API Routes
|--------------------------------------------------------------------------
|
| All routes in this file require a valid Laravel Sanctum token.
| The frontend must send the token using the httpOnly session cookie
| (post-PR2 of critical-sanctum-token-expiry change).
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
| Login es público (devuelve token Sanctum + cookie de sesión). Logout y
| /me requieren token válido vía `auth:sanctum` (definidos dentro del
| grupo más abajo).
|
| Todas las rutas auth están envueltas en `store.domain` para resolver
| la tienda activa y validar que el usuario tenga acceso a ella via
| tabla pivot `user_stores`.
|
*/

Route::middleware(['store.domain'])->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->name('auth.login');

    // Recuperación de contraseña (F del roadmap multi-tenant).
    // Ambas rutas son públicas (no requieren auth) y operan sobre la
    // tienda activa resuelta por store.domain.
    Route::post('auth/forgot-password', [PasswordResetTokenController::class, 'forgot'])
        ->name('auth.forgot-password');

    Route::post('auth/reset-password', [PasswordResetTokenController::class, 'reset'])
        ->name('auth.reset-password');

    // 2-step recovery flows: el usuario verifica un segundo factor
    // (recovery code o TOTP) y obtiene un challenge_token de un solo uso
    // que después envía a /auth/reset-password con `challenge_token`
    // para completar el cambio de password. Esto desacopla la
    // verificación del code de la confirmación del nuevo password.
    Route::post('auth/recover-via-recovery-code', [PasswordResetTokenController::class, 'recoverWithRecoveryCode'])
        ->name('auth.recover.recovery-code');
    Route::post('auth/recover-via-totp', [PasswordResetTokenController::class, 'recoverWithTotp'])
        ->name('auth.recover.totp');

    // Social login (Google + Facebook) — stub que devuelve 501 mientras
    // no esté instalado laravel/socialite. Ver SocialAuthController.
    Route::get('auth/social/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->middleware([
            Illuminate\Cookie\Middleware\EncryptCookies::class,
            Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            Illuminate\Session\Middleware\StartSession::class,
        ])
        ->name('auth.social.redirect');
    Route::get('auth/social/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->middleware([
            Illuminate\Cookie\Middleware\EncryptCookies::class,
            Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            Illuminate\Session\Middleware\StartSession::class,
        ])
        ->name('auth.social.callback');

    // Verificación TOTP cuando el login retorna '2fa_required'.
    // Acepta el challenge_token devuelto por /auth/login + el code de
    // 6 dígitos del authenticator app. Emite el Sanctum token.
    Route::post('auth/2fa/verify-totp-login', [AuthController::class, 'verifyTotpLogin'])
        ->name('auth.2fa.verify-totp-login');
});

Route::middleware(['store.domain', 'auth:sanctum'])->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout'])
        ->name('auth.logout');

    Route::get('auth/me', [AuthController::class, 'me'])
        ->name('auth.me');

    // 2FA / TOTP — gestión de la config TOTP del user autenticado.
    // El flujo de verify-during-login es AuthController::login (lo
    // maneja en el commit siguiente con preferred_recovery_method).
    Route::get('auth/2fa/totp', [TotpController::class, 'status'])
        ->name('auth.2fa.totp.status');
    Route::post('auth/2fa/totp/setup', [TotpController::class, 'setup'])
        ->name('auth.2fa.totp.setup');
    Route::post('auth/2fa/totp/verify', [TotpController::class, 'verify'])
        ->name('auth.2fa.totp.verify');
    Route::delete('auth/2fa/totp', [TotpController::class, 'destroy'])
        ->name('auth.2fa.totp.destroy');

    // Recovery codes — códigos de un solo uso para cuando el user
    // pierde acceso a 2FA / email.
    Route::get('auth/recovery-codes', [RecoveryCodeController::class, 'index'])
        ->name('auth.recovery-codes.index');
    Route::post('auth/recovery-codes/regenerate', [RecoveryCodeController::class, 'regenerate'])
        ->name('auth.recovery-codes.regenerate');
});

Route::middleware('auth:sanctum')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Account (GDPR)
    |--------------------------------------------------------------------------
    |
    | RF-CUST-042: exportación + borrado de datos personales del cliente
    | autenticado (derecho de acceso y olvido, GDPR + Ley 896 Nicaragua).
    |
    */

    Route::get('account/data-export', [AccountDataController::class, 'export'])
        ->name('account.data-export');

    Route::post('account/deletion-request', [AccountDataController::class, 'requestDeletion'])
        ->name('account.deletion-request');

    Route::delete('account', [AccountDataController::class, 'destroy'])
        ->name('account.destroy');

    Route::post('account/deletion-cancel', [AccountDataController::class, 'cancelDeletion'])
        ->name('account.deletion-cancel');

    /*
    |--------------------------------------------------------------------------
    | Admin (RF-AUTH-008)
    |--------------------------------------------------------------------------
    |
    | CRUD admin de roles y catálogo de permisos.
    | Requiere `auth:sanctum` + `permission:auth.roles.manage`
    | (los admins con slug `admin` bypasean el check).
    |
    */

    Route::prefix('admin')->group(function (): void {
        Route::get('permissions', [RoleController::class, 'listPermissions'])
            ->middleware('permission:auth.permissions.manage')
            ->name('admin.permissions.index');

        Route::get('roles', [RoleController::class, 'index'])
            ->middleware('permission:auth.roles.manage')
            ->name('admin.roles.index');

        Route::post('roles', [RoleController::class, 'store'])
            ->middleware('permission:auth.roles.manage')
            ->name('admin.roles.store');

        Route::get('roles/{role}', [RoleController::class, 'show'])
            ->middleware('permission:auth.roles.manage')
            ->name('admin.roles.show');

        Route::put('roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:auth.roles.manage')
            ->name('admin.roles.update');

        Route::delete('roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:auth.roles.manage')
            ->name('admin.roles.destroy');

        Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])
            ->middleware('permission:auth.roles.manage')
            ->name('admin.roles.permissions.sync');

        /*
        | RF-AUTH-009: CRUD admin de usuarios internos. Requiere
        | `auth.users.manage` (admin slug bypasea).
        */

        Route::get('users', [UserController::class, 'index'])
            ->middleware('permission:auth.users.manage')
            ->name('admin.users.index');

        Route::post('users', [UserController::class, 'store'])
            ->middleware('permission:auth.users.manage')
            ->name('admin.users.store');

        Route::get('users/{user}', [UserController::class, 'show'])
            ->middleware('permission:auth.users.manage')
            ->name('admin.users.show');

        Route::put('users/{user}', [UserController::class, 'update'])
            ->middleware('permission:auth.users.manage')
            ->name('admin.users.update');

        Route::post('users/{user}/suspend', [UserController::class, 'suspend'])
            ->middleware('permission:auth.users.manage')
            ->name('admin.users.suspend');

        Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])
            ->middleware('permission:auth.users.manage')
            ->name('admin.users.reactivate');

        Route::delete('users/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:auth.users.manage')
            ->name('admin.users.destroy');

        /*
        | RF-CAT-026: CRUD admin de productos. Requiere
        | `catalog.manage` (admin slug bypasea).
        */

        Route::get('products', [ProductController::class, 'index'])
            ->middleware('permission:catalog.manage')
            ->name('admin.products.index');

        Route::post('products', [ProductController::class, 'store'])
            ->middleware('permission:catalog.manage')
            ->name('admin.products.store');

        Route::get('products/{product}', [ProductController::class, 'show'])
            ->middleware('permission:catalog.manage')
            ->name('admin.products.show');

        Route::put('products/{product}', [ProductController::class, 'update'])
            ->middleware('permission:catalog.manage')
            ->name('admin.products.update');

        Route::post('products/{product}/archive', [ProductController::class, 'archive'])
            ->middleware('permission:catalog.manage')
            ->name('admin.products.archive');

        Route::post('products/{product}/publish', [ProductController::class, 'publish'])
            ->middleware('permission:catalog.manage')
            ->name('admin.products.publish');

        Route::put('products/{product}/stores', [ProductController::class, 'syncStores'])
            ->middleware('permission:catalog.manage')
            ->name('admin.products.stores.sync');

        Route::delete('products/{product}', [ProductController::class, 'destroy'])
            ->middleware('permission:catalog.manage')
            ->name('admin.products.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Cart
    |--------------------------------------------------------------------------
    |
    | Shopping cart management. Cart items are nested under carts.
    |
    */

    Route::apiResource('carts', CartController::class);
    Route::apiResource('carts.items', CartItemController::class);

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    |
    | Order management. Order items are nested under orders.
    |
    */

    Route::apiResource('orders', OrderController::class);

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | Payment application to invoices.
    |
    */

    Route::apiResource('payments', PaymentController::class);

    /*
    |--------------------------------------------------------------------------
    | Shipping
    |--------------------------------------------------------------------------
    |
    | Shipment management with state transition endpoints.
    |
    */

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
    Route::get('/products/search', [StorefrontProductController::class, 'search'])
        ->name('storefront.products.search');
    Route::get('/products', [StorefrontProductController::class, 'index'])
        ->name('storefront.products.index');
    Route::get('/products/{slug}', [StorefrontProductController::class, 'show'])
        ->name('storefront.products.show');
    Route::get('/products/{slug}/price', [StorefrontProductController::class, 'price'])
        ->name('storefront.products.price');
    Route::get('/theme', [StorefrontThemeController::class, 'show']);
    // Proxy de assets del tenant desde FTP al browser.
    // El {path} acepta slashes via where() regex para que coincida
    // con paths completos tipo tenants/panda-estampados/branding/logo.webp.
    Route::get('/assets/{path}', [StorefrontAssetController::class, 'show'])
        ->where('path', '.*');
});
