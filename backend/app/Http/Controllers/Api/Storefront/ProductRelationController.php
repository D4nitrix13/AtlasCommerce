<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Enums\ProductRelationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\StorefrontProductResource;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductRelation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rieles de recomendación de la PDP desde `product_relations`.
 *
 * Público (mismo grupo `store.domain` que el resto del storefront).
 * Devuelve los dos rieles en la shape de producto del storefront para
 * que las cards del frontend no necesiten un adapter propio.
 */
class ProductRelationController extends Controller
{
    /**
     * Devuelve las relaciones curadas de un producto.
     *
     * Shape: `{ bought_together: [...], you_may_also_like: [...] }`,
     * cada elemento con la forma de `StorefrontProductResource`.
     * Los destinos no visibles en la tienda actual se descartan.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $store = $request->attributes->get('store');

        $productId = DB::table('product_stores')
            ->where(column: 'store_id', operator: '=', value: $store->id)
            ->where(column: 'is_visible', operator: '=', value: true)
            ->whereIn('product_id', DB::table('products')
                ->select('id')
                ->where(column: 'slug', operator: '=', value: $slug))
            ->value(column: 'product_id');

        if ($productId === null) {
            abort(code: 404);
        }

        return response()->json(data: [
            'bought_together' => $this->resolveRail(
                productId: $productId,
                type: ProductRelationType::BoughtTogether,
                storeId: $store->id,
            ),
            'you_may_also_like' => $this->resolveRail(
                productId: $productId,
                type: ProductRelationType::YouMayAlsoLike,
                storeId: $store->id,
            ),
        ]);
    }

    /**
     * Productos de un riel, en orden de posición y solo los visibles.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveRail(int $productId, ProductRelationType $type, int $storeId): array
    {
        $relatedIds = ProductRelation::query()
            ->where('product_id', $productId)
            ->ofType($type)
            ->pluck('related_product_id');

        if ($relatedIds->isEmpty()) {
            return [];
        }

        $visibleIds = DB::table('product_stores')
            ->where(column: 'store_id', operator: '=', value: $storeId)
            ->where(column: 'is_visible', operator: '=', value: true)
            ->whereIn('product_id', $relatedIds)
            ->pluck('product_id');

        if ($visibleIds->isEmpty()) {
            return [];
        }

        $products = Product::query()
            ->with(['category', 'brand', 'images', 'variants', 'variants.size'])
            ->whereIn('id', $visibleIds)
            ->where(column: 'is_active', operator: '=', value: true)
            ->get()
            ->keyBy('id');

        return $relatedIds
            ->filter(fn (int $id): bool => $products->has($id))
            ->map(fn (int $id): array => (new StorefrontProductResource($products->get($id)))->resolve())
            ->values()
            ->all();
    }
}
