<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reviews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\ProductReviewStoreRequest;
use App\Http\Resources\Support\ProductReviewResource;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Support\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reseñas de producto — endpoints del cliente y públicos (RF-SUP-161).
 *
 * Rutas:
 *   - POST   /products/{product}/reviews        cliente autenticado crea reseña
 *   - GET    /products/{product}/reviews        público, lista aprobadas
 *   - GET    /account/reviews                   cliente lista SUS reseñas
 *
 * Reglas:
 *  - Una reseña por cliente por producto (DB unique + controller check).
 *  - Reseñas requieren `is_approved=true` para ser visibles (admin modera).
 *  - Cliente sólo ve/edita SUS propias reseñas.
 */
class ProductReviewController extends Controller
{
    /**
     * POST /products/{product}/reviews
     */
    public function store(
        ProductReviewStoreRequest $request,
        Product $product,
    ): JsonResponse {
        $customer = $this->resolveCustomer($request);

        // DB::table directo — ProductReview::query() no ve el row recién
        // creado por el factory (issue de visibilidad transaccional en tests).
        $existing = DB::table('product_reviews')
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->where(column: 'customer_id', operator: '=', value: $customer->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($existing) {
            return response()->json(
                data: ['message' => 'Ya tenés una reseña para este producto.'],
                status: Response::HTTP_CONFLICT,
            );
        }

        $review = ProductReview::query()->create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'store_id' => $customer->stores()->first()?->id ?? $product->store_id,
            'rating' => $request->integer('rating'),
            'comment' => $request->input('comment'),
            'is_approved' => false,
            'reviewed_at' => now(),
        ]);

        return response()->json(data: [
            'data' => (new ProductReviewResource(resource: $review))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * GET /products/{product}/reviews
     *
     * Público. Lista solo reseñas aprobadas.
     */
    public function indexForProduct(Request $request, Product $product): JsonResponse
    {
        $rows = DB::table('product_reviews')
            ->where('product_id', $product->id)
            ->where('is_approved', true)
            ->whereNull('deleted_at')
            ->orderByDesc('reviewed_at')
            ->limit(50)
            ->get();

        return response()->json(data: [
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'product_id' => $r->product_id,
                'customer_id' => $r->customer_id,
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'is_approved' => (bool) $r->is_approved,
                'reviewed_at' => $r->reviewed_at,
            ])->all(),
            'meta' => [
                'average_rating' => $rows->avg('rating'),
                'count' => $rows->count(),
            ],
        ]);
    }

    /**
     * GET /account/reviews
     *
     * Cliente autenticado lista SUS reseñas (incluyendo pendientes).
     */
    public function indexForCustomer(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $rows = DB::table('product_reviews')
            ->where(column: 'customer_id', operator: '=', value: $customer->id)
            ->whereNull('deleted_at')
            ->orderByDesc('reviewed_at')
            ->get();

        return response()->json(data: [
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'product_id' => $r->product_id,
                'customer_id' => $r->customer_id,
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'is_approved' => (bool) $r->is_approved,
                'reviewed_at' => $r->reviewed_at,
            ])->all(),
        ]);
    }

    /**
     * Helper: resuelve el Customer asociado al User autenticado.
     */
    private function resolveCustomer(Request $request): Customer
    {
        $user = $request->user();
        abort_if($user === null, 401, 'Autenticación requerida.');

        $customer = Customer::query()->where('user_id', $user->id)->first();
        abort_if($customer === null, 404, 'No existe perfil de cliente para este usuario.');

        return $customer;
    }
}
