<?php

declare(strict_types=1);

use D4nitrix13\AtlasCommercePluginErp\Http\Controllers\ErpStatusController;
use D4nitrix13\AtlasCommercePluginErp\Http\Middleware\EnsureErpFeatureEnabled;
use Illuminate\Support\Facades\Route;

Route::prefix('api')
    ->middleware(['api', 'store.domain', EnsureErpFeatureEnabled::class])
    ->get('erp/status', ErpStatusController::class)
    ->name('erp.status');
