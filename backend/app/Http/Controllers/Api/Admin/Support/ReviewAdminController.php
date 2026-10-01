<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Support;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Support\ReviewResource;
use App\Models\Auth\User as UserModel;
use App\Models\Support\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Moderación admin de reseñas bajo `/admin/support/reviews`.
 *
 * Espejo del `Admin\ProductReviewController` legacy. Filtros:
 *  - pending=1 → sólo reseñas no aprobadas.
 *  - product_id → filtra por producto.
 *  - rating → filtra por calificación.
 */
class ReviewAdminController extends Controller
{
    /**
     * GET /admin/support/reviews
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductReview::query()
            ->with('product')
            ->whereIn('store_id', $this->accessibleStoreIds());

        if ($request->boolean('pending')) {
            $query->pending();
        }

        if ($productId = $request->query('product_id')) {
            $query->where(column: 'product_id', operator: '=', value: (int) $productId);
        }

        if ($rating = $request->query('rating')) {
            $query->where(column: 'rating', operator: '=', value: (int) $rating);
        }

        $reviews = $query->orderByDesc('reviewed_at')->limit(200)->get();

        return response()->json(data: [
            'data' => ReviewResource::collection(resource: $reviews)->resolve(),
        ]);
    }

    /**
     * GET /admin/support/reviews/{review}
     */
    public function show(ProductReview $review): JsonResponse
    {
        $this->assertReviewInTenantScope(review: $review);

        $review->load('product');

        return response()->json(data: [
            'data' => (new ReviewResource(resource: $review))->resolve(),
        ]);
    }

    /**
     * POST /admin/support/reviews/{review}/approve
     */
    public function approve(ProductReview $review): JsonResponse
    {
        $this->assertReviewInTenantScope(review: $review);

        $review->update(['is_approved' => true]);
        $review->load('product');

        return response()->json(data: [
            'data' => (new ReviewResource(resource: $review))->resolve(),
        ]);
    }

    /**
     * POST /admin/support/reviews/{review}/reject
     */
    public function reject(ProductReview $review): JsonResponse
    {
        $this->assertReviewInTenantScope(review: $review);

        $review->update(['is_approved' => false]);
        $review->load('product');

        return response()->json(data: [
            'data' => (new ReviewResource(resource: $review))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/support/reviews/{review}
     */
    public function destroy(ProductReview $review): JsonResponse
    {
        $this->assertReviewInTenantScope(review: $review);

        $review->delete();

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

    private function assertReviewInTenantScope(ProductReview $review): void
    {
        if ($review->store_id === null) {
            return;
        }

        if (! in_array((int) $review->store_id, $this->accessibleStoreIds(), strict: true)) {
            abort(code: 404, message: 'Reseña no encontrada en tu tenant.');
        }
    }
}
