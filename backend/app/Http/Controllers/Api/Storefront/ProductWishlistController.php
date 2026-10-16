<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Carts\Wishlist;
use App\Models\Carts\WishlistItem;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Toggle "favorito" sobre un producto (T14b del plan PDP reseñas).
 *
 * Rutas:
 *   - POST   /storefront/products/{product}/wishlist   agregar al wishlist default
 *   - DELETE /storefront/products/{product}/wishlist   quitar del wishlist default
 *   - GET    /storefront/products/{product}/wishlist   público: estado actual
 *
 * Estrategia: el wishlist "default" del customer se crea automáticamente
 * si no existe (RF-CART-013). El toggle es idempotente — agregar 2 veces
 * no duplica (usa firstOrCreate por wishlist_id + product_id, además la
 * migración garantiza UNIQUE en wishlist_items).
 *
 * GET público devuelve { in_wishlist: bool } sin auth (default false),
 * o el estado real si hay auth (auth:sanctum).
 */
class ProductWishlistController extends Controller
{
    /**
     * POST /storefront/products/{product}/wishlist
     *
     * Cliente autenticado agrega el producto al wishlist default. Si
     * el wishlist default no existe, se crea automáticamente.
     * Idempotente: si ya estaba agregado, firstOrCreate no duplica.
     *
     * Response 200: { in_wishlist: true }.
     * Response 401: autenticación requerida (auth:sanctum middleware).
     * Response 404: producto no existe (route-model-binding falla).
     */
    public function store(Product $product, Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        $wishlist = $this->getOrCreateDefaultWishlist($customer);

        WishlistItem::query()->firstOrCreate(
            attributes: [
                'wishlist_id' => $wishlist->id,
                'product_id' => $product->id,
            ],
            values: ['added_at' => now()],
        );

        return response()->json(data: [
            'in_wishlist' => true,
        ], status: Response::HTTP_OK);
    }

    /**
     * DELETE /storefront/products/{product}/wishlist
     *
     * Cliente autenticado quita el producto de su wishlist default.
     * Idempotente: si no estaba, el where().delete() no falla.
     *
     * Response 200: { in_wishlist: false }.
     * Response 401: autenticación requerida.
     * Response 404: producto no existe.
     */
    public function destroy(Product $product, Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        $wishlist = $this->getOrCreateDefaultWishlist($customer);

        WishlistItem::query()
            ->where(column: 'wishlist_id', operator: '=', value: $wishlist->id)
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->delete();

        return response()->json(data: [
            'in_wishlist' => false,
        ], status: Response::HTTP_OK);
    }

    /**
     * GET /storefront/products/{product}/wishlist
     *
     * Público: devuelve el estado actual para que el storefront hidrate
     * el botón antes de que el cliente se autentique. Sin auth:
     * in_wishlist siempre false. Con auth: refleja el wishlist default
     * del cliente.
     *
     * Response 200: { in_wishlist: bool }.
     * Response 404: producto no existe.
     */
    public function show(Product $product, Request $request): JsonResponse
    {
        return response()->json(data: [
            'in_wishlist' => $this->inDefaultWishlist($product, $request),
        ], status: Response::HTTP_OK);
    }

    /**
     * Resuelve el Customer asociado al User autenticado por Sanctum.
     * Replica el patrón de ProductReviewLikeController y CartItemController.
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

    /**
     * Devuelve (y crea si falta) el wishlist predeterminado del cliente.
     * El nombre "Favoritos" es el default visible; el cliente puede
     * renombrarlo desde el account area si lo desea.
     */
    private function getOrCreateDefaultWishlist(Customer $customer): Wishlist
    {
        return $customer->wishlists()->firstOrCreate(
            attributes: ['is_default' => true],
            values: [
                'store_id' => $customer->stores()->first()?->id,
                'name' => 'Favoritos',
            ],
        );
    }

    /**
     * Verifica si el producto está en el wishlist default del cliente
     * autenticado. Sin sesión o sin wishlist default → false. La PDP
     * puede llamar a este método en GET público para hidratar el icono.
     */
    private function inDefaultWishlist(Product $product, Request $request): bool
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

        $wishlist = $customer->wishlists()
            ->where(column: 'is_default', operator: '=', value: true)
            ->first();
        if ($wishlist === null) {
            return false;
        }

        return WishlistItem::query()
            ->where(column: 'wishlist_id', operator: '=', value: $wishlist->id)
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->exists();
    }
}
