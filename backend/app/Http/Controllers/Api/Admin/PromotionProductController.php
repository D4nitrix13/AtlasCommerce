<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\Admin\PromotionProductsSyncRequest;
use App\Http\Resources\Marketing\AdminPromotionResource;
use App\Models\Marketing\Promotion;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gestión de la asociación N:N `Promotion ↔ Product` (RF-MKT-061).
 *
 * Endpoint: POST /admin/promotions/{promotion}/products
 * Body:
 *  - action: 'attach' (default) | 'detach'
 *  - product_ids: array<int>
 *
 * Retorna la promoción con la lista actualizada de `product_ids`.
 */
class PromotionProductController extends Controller
{
    public function sync(PromotionProductsSyncRequest $request, Promotion $promotion): JsonResponse
    {
        $data = $request->validated();
        $action = $data['action'] ?? 'attach';
        $productIds = $data['product_ids'];

        match ($action) {
            'attach' => $promotion->products()->syncWithoutDetaching(ids: $productIds),
            'detach' => $promotion->products()->detach(ids: $productIds),
            default => null,
        };

        $promotion->load(relations: ['products']);

        return response()->json(data: [
            'data' => (new AdminPromotionResource(resource: $promotion))->resolve(),
        ], status: Response::HTTP_OK);
    }
}
