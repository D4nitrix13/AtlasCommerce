<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\Admin\Api\PromotionProductStoreRequest;
use App\Http\Resources\Admin\Marketing\PromotionProductResource;
use App\Models\Marketing\ProductPromotion;
use App\Models\Marketing\Promotion;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vinculación promotion ↔ product bajo `/admin/marketing/promotion-products`.
 *
 * Modelo `apiResource`: index, store (vincular), show, destroy (desvincular).
 * NO expone update — la asociación no tiene campos mutables.
 */
class PromotionProductAdminController extends Controller
{
    /**
     * GET /admin/marketing/promotion-products
     */
    public function index(): JsonResponse
    {
        $links = ProductPromotion::query()->with(['product', 'promotion'])->orderBy('id')->limit(500)->get();

        return response()->json(data: [
            'data' => PromotionProductResource::collection(resource: $links)->resolve(),
        ]);
    }

    /**
     * GET /admin/marketing/promotion-products/{promotionProduct}
     */
    public function show(ProductPromotion $promotionProduct): JsonResponse
    {
        $promotionProduct->load(['product', 'promotion']);

        return response()->json(data: [
            'data' => (new PromotionProductResource(resource: $promotionProduct))->resolve(),
        ]);
    }

    /**
     * POST /admin/marketing/promotion-products
     */
    public function store(PromotionProductStoreRequest $request): JsonResponse
    {
        $promotionId = (int) $request->integer('promotion_id');
        $productId = (int) $request->integer('product_id');

        $promotion = Promotion::query()->findOrFail($promotionId);

        $exists = ProductPromotion::query()
            ->where(column: 'promotion_id', operator: '=', value: $promotionId)
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->exists();

        if ($exists) {
            return response()->json(data: [
                'message' => 'El producto ya está vinculado a esta promoción.',
            ], status: Response::HTTP_CONFLICT);
        }

        $link = ProductPromotion::query()->create([
            'promotion_id' => $promotionId,
            'product_id' => $productId,
        ]);

        $link->load(['product', 'promotion']);

        return response()->json(data: [
            'data' => (new PromotionProductResource(resource: $link))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * DELETE /admin/marketing/promotion-products/{promotionProduct}
     */
    public function destroy(ProductPromotion $promotionProduct): JsonResponse
    {
        $promotionProduct->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }
}
