<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\Admin\Api\PromotionStoreRequest;
use App\Http\Requests\Marketing\Admin\Api\PromotionUpdateRequest;
use App\Http\Resources\Admin\Marketing\PromotionResource;
use App\Models\Auth\User as UserModel;
use App\Models\Marketing\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de promociones bajo `/admin/marketing/promotions`.
 *
 * Espejo del legacy `Admin\PromotionController` (ruta `/admin/promotions`)
 * pero con organización `Marketing/` y Resource con eager-loaded products.
 */
class PromotionAdminController extends Controller
{
    /**
     * GET /admin/marketing/promotions
     */
    public function index(Request $request): JsonResponse
    {
        $accessibleStoreIds = $this->accessibleStoreIds();

        $query = Promotion::query()
            ->with('products')
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

        $promotions = $query->orderByDesc('created_at')->limit(200)->get();

        return response()->json(data: [
            'data' => PromotionResource::collection(resource: $promotions)->resolve(),
        ]);
    }

    /**
     * GET /admin/marketing/promotions/{promotion}
     */
    public function show(Promotion $promotion): JsonResponse
    {
        $this->assertPromotionInTenantScope(promotion: $promotion);

        $promotion->load('products');

        return response()->json(data: [
            'data' => (new PromotionResource(resource: $promotion))->resolve(),
        ]);
    }

    /**
     * POST /admin/marketing/promotions
     */
    public function store(PromotionStoreRequest $request): JsonResponse
    {
        $promotion = Promotion::query()->create(attributes: $request->validated());

        return response()->json(data: [
            'data' => (new PromotionResource(resource: $promotion))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/marketing/promotions/{promotion}
     */
    public function update(PromotionUpdateRequest $request, Promotion $promotion): JsonResponse
    {
        $this->assertPromotionInTenantScope(promotion: $promotion);

        $promotion->update(attributes: $request->validated());

        return response()->json(data: [
            'data' => (new PromotionResource(resource: $promotion))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/marketing/promotions/{promotion}
     */
    public function destroy(Promotion $promotion): JsonResponse
    {
        $this->assertPromotionInTenantScope(promotion: $promotion);

        $promotion->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }

    /**
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
}
