<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stores;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stores\UpdateThemeRequest;
use App\Http\Resources\Stores\StoreThemeAdminResource;
use App\Models\Stores\Store;
use App\Models\Stores\StoreTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints admin para gestionar el theme del store actual.
 *
 * Requiere autenticacion (auth:sanctum). El tenant se resuelve via
 * `store.domain` middleware. El usuario solo puede editar el theme
 * de su propio store (resuelto por la relacion user_stores).
 *
 * Endpoints:
 *   - GET  /api/admin/theme  → devuelve el theme actual.
 *   - PATCH /api/admin/theme → actualiza el theme (idempotente).
 */
class ThemeAdminController extends Controller
{
    /**
     * Devuelve el theme del store actual.
     *
     * Si el store no tiene theme configurado, devuelve defaults
     * vacíos para que el admin UI pueda popular el form.
     */
    public function show(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');
        $theme = $store?->theme;

        return response()->json(data: [
            'data' => new StoreThemeAdminResource($theme),
        ]);
    }

    /**
     * Actualiza el theme del store actual.
     *
     * Crea el StoreTheme si no existe. Idempotente: solo se actualizan
     * los campos que vienen en el body.
     */
    public function update(UpdateThemeRequest $request): JsonResponse
    {
        $store = $request->attributes->get('store');
        $this->ensureStore($store);

        $data = array_filter(
            $request->validated(),
            static fn (mixed $value): bool => $value !== null,
        );

        $theme = StoreTheme::query()->updateOrCreate(
            attributes: ['store_id' => $store->id],
            values: $data,
        );

        return response()->json(data: [
            'data' => new StoreThemeAdminResource($theme),
        ]);
    }

    /**
     * Verifica que hay un store resuelto por el middleware.
     */
    private function ensureStore(?Store $store): void
    {
        if ($store === null) {
            abort(
                code: 404,
                message: 'Store no resuelto por el middleware store.domain.',
            );
        }
    }
}
