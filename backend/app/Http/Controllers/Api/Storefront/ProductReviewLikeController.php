<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customers\Customer;
use App\Models\Support\ProductReview;
use App\Models\Support\ProductReviewLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Me gusta" sobre reseñas de producto — endpoints del cliente
 * autenticado y públicos (T8 del plan PDP reseñas).
 *
 * Rutas:
 *   - POST   /storefront/reviews/{review}/like   cliente autenticado da like (idempotente)
 *   - DELETE /storefront/reviews/{review}/like   cliente autenticado quita su like (idempotente)
 *   - GET    /storefront/reviews/{review}/like   público: count + liked-by-current
 *
 * El dislike no existe — el "off" se hace eliminando la fila via DELETE.
 * El rating like/unlike es responsabilidad del cliente (frontend
 * optimistic UI + rollback si falla); el backend sólo persiste el toggle.
 *
 * Idempotencia:
 *  - POST: firstOrCreate — segundo insert devuelve el row existente.
 *  - DELETE: where().delete() — borrar un row inexistente no es error.
 *  - GET: lee estado actual sin side effects.
 */
class ProductReviewLikeController extends Controller
{
    /**
     * POST /storefront/reviews/{review}/like
     *
     * Cliente autenticado registra su "me gusta" sobre una reseña.
     * Si ya existía, devuelve 200 con el mismo payload (idempotente);
     * si se acaba de crear, devuelve 201.
     *
     * Response 201: { liked: true, count: N } cuando se creó.
     * Response 200: { liked: true, count: N } cuando ya existía.
     * Response 401: autenticación requerida (auth:sanctum middleware).
     * Response 404: reseña no existe (route-model-binding falla).
     */
    public function store(ProductReview $review, Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        // firstOrCreate devuelve [model, created] — usamos el flag para
        // diferenciar 201 (recién creado) vs 200 (ya existía).
        [, $created] = ProductReviewLike::firstOrCreate(attributes: [
            'product_review_id' => $review->id,
            'customer_id' => $customer->id,
        ]);

        return response()->json(data: [
            'liked' => true,
            'count' => $this->countLikes($review),
        ], status: $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * DELETE /storefront/reviews/{review}/like
     *
     * Cliente autenticado quita su "me gusta". Idempotente: si no
     * existía el like, devuelve 200 con count actual igual.
     *
     * Response 200: { liked: false, count: N }.
     * Response 401: autenticación requerida.
     * Response 404: reseña no existe.
     */
    public function destroy(ProductReview $review, Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        ProductReviewLike::query()
            ->where(column: 'product_review_id', operator: '=', value: $review->id)
            ->where(column: 'customer_id', operator: '=', value: $customer->id)
            ->delete();

        return response()->json(data: [
            'liked' => false,
            'count' => $this->countLikes($review),
        ], status: Response::HTTP_OK);
    }

    /**
     * GET /storefront/reviews/{review}/like
     *
     * Público: devuelve count total + flag liked-by-current para que
     * el frontend hidrate el botón. Sin auth: liked siempre false.
     * Con auth: liked refleja si el cliente autenticado dio like.
     *
     * Response 200: { liked: bool, count: N }.
     * Response 404: reseña no existe.
     */
    public function show(ProductReview $review, Request $request): JsonResponse
    {
        return response()->json(data: [
            'liked' => $this->likedByCurrent($review, $request),
            'count' => $this->countLikes($review),
        ], status: Response::HTTP_OK);
    }

    /**
     * Cuenta total de "me gusta" de una reseña.
     */
    private function countLikes(ProductReview $review): int
    {
        return ProductReviewLike::query()
            ->where(column: 'product_review_id', operator: '=', value: $review->id)
            ->count();
    }

    /**
     * Verifica si el cliente autenticado dio like. Si no hay sesión,
     * devuelve false (el botón aparece sin fill).
     */
    private function likedByCurrent(ProductReview $review, Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        $customer = Customer::query()
            ->where(column: 'user_id', operator: '=', value: $user->id)
            ->first();

        if ($customer === null) {
            return false;
        }

        return ProductReviewLike::query()
            ->where(column: 'product_review_id', operator: '=', value: $review->id)
            ->where(column: 'customer_id', operator: '=', value: $customer->id)
            ->exists();
    }

    /**
     * Resuelve el Customer asociado al User autenticado por Sanctum.
     * Replica el patrón de ProductReviewController y CartItemController:
     *  - auth:sanctum garantiza $user no es null al llegar acá.
     *  - abort 404 si el User no tiene perfil Customer (caso borde:
     *    admin/staff autenticado en ruta storefront).
     */
    private function resolveCustomer(Request $request): Customer
    {
        $user = $request->user();
        abort_if($user === null, 401, 'Autenticación requerida.');

        $customer = Customer::query()
            ->where(column: 'user_id', operator: '=', value: $user->id)
            ->first();

        abort_if($customer === null, 404, 'No existe perfil de cliente para este usuario.');

        return $customer;
    }
}
