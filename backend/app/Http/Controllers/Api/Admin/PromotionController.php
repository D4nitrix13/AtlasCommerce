<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\Admin\PromotionStoreRequest;
use App\Http\Requests\Marketing\Admin\PromotionUpdateRequest;
use App\Http\Resources\Marketing\AdminPromotionResource;
use App\Models\Auth\User as UserModel;
use App\Models\Marketing\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de promociones automáticas (RF-MKT-061).
 *
 * Reglas:
 *  - Aislamiento por tenant: promociones globales (`store_id = null`)
 *    se ven en todos los tenants; promociones scoped sólo en su tienda.
 *  - Permiso `marketing.manage` (admin slug bypasea).
 *
 * La asociación a productos se gestiona vía `PromotionProductController`
 * (POST /admin/promotions/{promotion}/products).
 */
class PromotionController extends Controller
{
    /**
     * GET /admin/promotions
     *
     * Query params:
     *  - page, per_page (default 20, max 100).
     *  - q: filtro ILIKE por name.
     *  - is_active: bool.
     *  - discount_type: percentage | fixed.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $accessibleStoreIds = $this->accessibleStoreIds();

        $query = Promotion::query()
            ->where(function ($q) use ($accessibleStoreIds): void {
                $q->whereNull('store_id')
                    ->orWhereIn('store_id', $accessibleStoreIds);
            });

        if ($search = trim((string) $request->query('q', ''))) {
            $needle = '%'.$search.'%';
            $query->where('name', 'ILIKE', $needle);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('discount_type')) {
            $query->where('discount_type', $request->query('discount_type'));
        }

        $paginator = $query
            ->orderByDesc('created_at')
            ->paginate(perPage: $perPage);

        return response()->json(data: [
            'data' => AdminPromotionResource::collection(resource: $paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /admin/promotions/{promotion}
     */
    public function show(Promotion $promotion): JsonResponse
    {
        $this->assertPromotionInTenantScope(promotion: $promotion);

        $promotion->load(relations: ['products', 'store.currency']);

        return response()->json(data: [
            'data' => (new AdminPromotionResource(resource: $promotion))->resolve(),
        ]);
    }

    /**
     * POST /admin/promotions
     */
    public function store(PromotionStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('store_id', $data) && $data['store_id'] !== null) {
            $this->assertStoresAccessible(storeIds: [(int) $data['store_id']]);
        }

        $promotion = Promotion::query()->create(attributes: $data);

        return response()->json(data: [
            'data' => (new AdminPromotionResource(resource: $promotion))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/promotions/{promotion}
     */
    public function update(PromotionUpdateRequest $request, Promotion $promotion): JsonResponse
    {
        $this->assertPromotionInTenantScope(promotion: $promotion);

        $promotion->update(attributes: $request->validated());

        return response()->json(data: [
            'data' => (new AdminPromotionResource(resource: $promotion))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/promotions/{promotion}
     *
     * Soft-delete: preserva historial de órdenes que la hayan aplicado.
     */
    public function destroy(Promotion $promotion): JsonResponse
    {
        $this->assertPromotionInTenantScope(promotion: $promotion);

        $promotion->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
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
     * Helper: aborta si la promoción pertenece a una tienda no accesible.
     * Promociones globales (store_id=null) son visibles para todos.
     */
    private function assertPromotionInTenantScope(Promotion $promotion): void
    {
        if ($promotion->store_id === null) {
            return;
        }

        $accessible = $this->accessibleStoreIds();

        if (! in_array((int) $promotion->store_id, $accessible, strict: true)) {
            abort(code: 404, message: 'Promoción no encontrada en tu tenant.');
        }
    }

    /**
     * Helper: valida que todos los store_ids pertenezcan a las tiendas
     * accesibles del admin.
     *
     * @param  array<int, int>  $storeIds
     */
    private function assertStoresAccessible(array $storeIds): void
    {
        $accessible = $this->accessibleStoreIds();

        $invalid = array_values(array_diff($storeIds, $accessible));
        if ($invalid !== []) {
            abort(
                code: 403,
                message: 'No podés asociar promociones a tiendas que no administrás: '.implode(', ', $invalid),
            );
        }
    }
}
