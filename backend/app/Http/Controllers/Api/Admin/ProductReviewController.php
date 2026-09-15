<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Support\ProductReviewResource;
use App\Models\Auth\User as UserModel;
use App\Models\Support\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Moderación admin de reseñas de producto (RF-SUP-161).
 *
 * Reglas:
 *  - Aislamiento por tenant: admin sólo ve reseñas de tiendas accesibles.
 *  - Approve/Reject cambian `is_approved` (moderación antes de publicar).
 *  - Destroy soft-delete (preserva auditoría).
 */
class ProductReviewController extends Controller
{
    /**
     * GET /admin/reviews
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);

        $query = ProductReview::query()
            ->whereIn('store_id', $this->accessibleStoreIds());

        if ($request->has('product_id')) {
            $query->where(column: 'product_id', operator: '=', value: (int) $request->query('product_id'));
        }

        if ($request->has('rating')) {
            $query->where(column: 'rating', operator: '=', value: (int) $request->query('rating'));
        }

        if ($request->has('is_approved')) {
            $isApproved = filter_var($request->query('is_approved'), FILTER_VALIDATE_BOOLEAN);
            $query->where(column: 'is_approved', operator: '=', value: $isApproved);
        }

        $paginator = $query->orderByDesc('reviewed_at')
            ->paginate(perPage: $perPage);

        return response()->json(data: [
            'data' => ProductReviewResource::collection(resource: $paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /admin/reviews/{review}
     */
    public function show(ProductReview $review): JsonResponse
    {
        $this->assertReviewInTenantScope(review: $review);

        return response()->json(data: [
            'data' => (new ProductReviewResource(resource: $review))->resolve(),
        ]);
    }

    /**
     * POST /admin/reviews/{review}/approve
     */
    public function approve(ProductReview $review): JsonResponse
    {
        $this->assertReviewInTenantScope(review: $review);

        $review->update(['is_approved' => true]);

        return response()->json(data: [
            'data' => (new ProductReviewResource(resource: $review))->resolve(),
        ]);
    }

    /**
     * POST /admin/reviews/{review}/reject
     */
    public function reject(ProductReview $review): JsonResponse
    {
        $this->assertReviewInTenantScope(review: $review);

        $review->update(['is_approved' => false]);

        return response()->json(data: [
            'data' => (new ProductReviewResource(resource: $review))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/reviews/{review}
     */
    public function destroy(ProductReview $review): JsonResponse
    {
        $this->assertReviewInTenantScope(review: $review);

        $review->delete();

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
     * Helper: aborta si la reseña pertenece a una tienda no accesible.
     */
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
