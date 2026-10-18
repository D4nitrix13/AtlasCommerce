<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\CursorPaginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stores\Admin\StoreUpdateRequest;
use App\Http\Resources\Stores\AdminStoreResource;
use App\Models\Auth\User as UserModel;
use App\Models\Stores\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD admin de tiendas (RF-STOR-132).
 *
 * Reglas:
 *  - El admin sólo opera sobre tiendas accesibles (sus `user_stores`).
 *  - Aislamiento cross-tenant: store de otra tienda → 404.
 *  - `slug` y `currency_id` son inmutables vía PUT (cambiar slug rompe
 *    SEO + DNS; cambiar currency implica migración de precios/histórico).
 *
 * Permiso: `stores.manage` (admin slug bypasea).
 */
class StoreController extends Controller
{
    use CursorPaginates;

    /**
     * GET /admin/stores
     */
    public function index(Request $request): JsonResponse
    {
        $query = Store::query()
            ->whereIn('id', $this->accessibleStoreIds())
            ->orderBy('name');

        $paginator = $query->paginate(perPage: (int) $request->query('per_page', 20));

        return $this->cursorPaginatedResponse(
            request: $request, query: $query, resourceClass: AdminStoreResource::class,
        );
    }

    /**
     * GET /admin/stores/{store}
     */
    public function show(Store $store): JsonResponse
    {
        $this->assertStoreInTenantScope(store: $store);

        return response()->json(data: [
            'data' => (new AdminStoreResource(resource: $store))->resolve(),
        ]);
    }

    /**
     * PUT /admin/stores/{store}
     */
    public function update(StoreUpdateRequest $request, Store $store): JsonResponse
    {
        $this->assertStoreInTenantScope(store: $store);

        $store->update(attributes: $request->validated());

        return response()->json(data: [
            'data' => (new AdminStoreResource(resource: $store->fresh()))->resolve(),
        ]);
    }

    /**
     * POST /admin/stores/{store}/maintenance
     *
     * Body: { "enabled": true|false }
     *
     * Activa o desactiva el modo mantenimiento del storefront. El admin
     * sigue operando con normalidad.
     */
    public function toggleMaintenance(Request $request, Store $store): JsonResponse
    {
        $this->assertStoreInTenantScope(store: $store);

        $data = $request->validate(rules: [
            'enabled' => ['required', 'boolean'],
        ]);

        $store->update(['maintenance_mode' => $data['enabled']]);

        return response()->json(data: [
            'data' => (new AdminStoreResource(resource: $store->fresh()))->resolve(),
        ]);
    }

    /**
     * Helper: IDs de tiendas accesibles por el admin actual.
     *
     * @return array<int, int>
     */
    private function accessibleStoreIds(): array
    {
        $user = auth()->user();
        if (! $user instanceof UserModel) {
            abort(code: 401);
        }

        return $user->stores()->pluck('stores.id')->all();
    }

    /**
     * Helper: aborta si la tienda no es accesible al admin.
     */
    private function assertStoreInTenantScope(Store $store): void
    {
        if (! in_array((int) $store->id, $this->accessibleStoreIds(), strict: true)) {
            abort(code: 404, message: 'Tienda no encontrada en tu tenant.');
        }
    }
}
