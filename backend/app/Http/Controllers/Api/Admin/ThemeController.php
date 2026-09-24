<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ThemeUpdateRequest;
use App\Http\Resources\Storefront\StoreThemeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints admin para gestionar el theme del store actual.
 *
 * El tenant se resuelve via `ResolveStoreFromDomain` middleware.
 * 1 relationship:1 entre Store y StoreTheme — `updateOrCreate`.
 *
 * Endpoints:
 *   - GET /api/admin/theme    → devuelve el theme actual (o defaults vacíos)
 *   - PATCH /api/admin/theme  → crea o actualiza el theme
 */
final class ThemeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');
        $theme = $store?->theme;

        return response()->json(data: [
            'data' => new StoreThemeResource($theme),
        ]);
    }

    public function update(ThemeUpdateRequest $request): JsonResponse
    {
        $store = $request->attributes->get('store');
        $theme = $store->theme()->updateOrCreate(
            attributes: [],
            values: $request->validated(),
        );

        return response()->json(data: [
            'data' => new StoreThemeResource($theme),
        ]);
    }
}
