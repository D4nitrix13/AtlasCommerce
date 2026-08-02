<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\StoreThemeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints públicos del theme (F3.3 del roadmap multi-instance).
 *
 * El tenant se resuelve via `ResolveStoreFromDomain` middleware
 * que expone la `Store` activa en `$request->attributes->get('store')`.
 *
 * Endpoints:
 *   - GET /api/storefront/theme  → devuelve el theme del store actual.
 *
 * Si el store no tiene theme configurado, devuelve defaults vacíos
 * (ver `StoreThemeResource`).
 */
class ThemeController extends Controller
{
    /**
     * Devuelve el theme del store actual.
     *
     * Response: JSON con el shape de `StoreThemeResource`.
     */
    public function show(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');
        $theme = $store?->theme;

        return response()->json(data: [
            'data' => new StoreThemeResource($theme),
        ]);
    }
}
