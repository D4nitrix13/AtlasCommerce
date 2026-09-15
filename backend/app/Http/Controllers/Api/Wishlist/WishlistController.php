<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wishlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wishlist\WishlistItemAddRequest;
use App\Http\Requests\Wishlist\WishlistStoreRequest;
use App\Http\Resources\Carts\WishlistItemResource;
use App\Http\Resources\Carts\WishlistResource;
use App\Models\Carts\Wishlist;
use App\Models\Carts\WishlistItem;
use App\Models\Customers\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD del wishlist del cliente autenticado (RF-CART-013).
 *
 * Reglas:
 *  - El cliente sólo opera sobre SUS listas. Cross-customer → 404.
 *  - `is_default` exclusivo: marcar una lista como predeterminada
 *    desmarca las demás del mismo cliente (transaccional).
 *  - Items se gestionan vía sub-endpoints (add/remove).
 */
class WishlistController extends Controller
{
    /**
     * GET /api/account/wishlists
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $wishlists = $customer->wishlists()
            ->with('items')
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return response()->json(data: [
            'data' => WishlistResource::collection(resource: $wishlists)->resolve(),
        ]);
    }

    /**
     * POST /api/account/wishlists
     */
    public function store(WishlistStoreRequest $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $data = $request->validated();

        $wishlist = DB::transaction(function () use ($customer, $data): Wishlist {
            if (($data['is_default'] ?? true) === true) {
                $customer->wishlists()->update(['is_default' => false]);
            }

            return $customer->wishlists()->create([
                'store_id' => $customer->stores()->first()?->id,
                'name' => $data['name'],
                'is_default' => $data['is_default'] ?? true,
            ]);
        });

        $wishlist->load('items');

        return response()->json(data: [
            'data' => (new WishlistResource(resource: $wishlist))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * GET /api/account/wishlists/{wishlist}
     */
    public function show(Request $request, Wishlist $wishlist): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $this->assertOwnedBy(customer: $customer, wishlist: $wishlist);

        $wishlist->load('items');

        return response()->json(data: [
            'data' => (new WishlistResource(resource: $wishlist))->resolve(),
        ]);
    }

    /**
     * DELETE /api/account/wishlists/{wishlist}
     */
    public function destroy(Request $request, Wishlist $wishlist): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $this->assertOwnedBy(customer: $customer, wishlist: $wishlist);

        $wishlist->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/account/wishlists/{wishlist}/items
     */
    public function addItem(
        WishlistItemAddRequest $request,
        Wishlist $wishlist,
    ): JsonResponse {
        $customer = $this->resolveCustomer($request);

        $this->assertOwnedBy(customer: $customer, wishlist: $wishlist);

        $productId = $request->integer('product_id');

        // Idempotente: si ya existe, no duplica.
        $item = WishlistItem::query()->firstOrCreate(
            attributes: [
                'wishlist_id' => $wishlist->id,
                'product_id' => $productId,
            ],
            values: ['added_at' => now()],
        );

        return response()->json(data: [
            'data' => (new WishlistItemResource(resource: $item))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * DELETE /api/account/wishlists/{wishlist}/items/{item}
     */
    public function removeItem(
        Request $request,
        Wishlist $wishlist,
        WishlistItem $item,
    ): JsonResponse {
        $customer = $this->resolveCustomer($request);

        $this->assertOwnedBy(customer: $customer, wishlist: $wishlist);
        $this->assertItemInWishlist(wishlist: $wishlist, item: $item);

        $item->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
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

    /**
     * Helper: aborta si la lista no pertenece al cliente autenticado.
     */
    private function assertOwnedBy(Customer $customer, Wishlist $wishlist): void
    {
        abort_if(
            $wishlist->customer_id !== $customer->id,
            404,
            'Lista no encontrada.',
        );
    }

    /**
     * Helper: aborta si el item no pertenece a la lista (evita delete cross-lista).
     */
    private function assertItemInWishlist(Wishlist $wishlist, WishlistItem $item): void
    {
        abort_if(
            $item->wishlist_id !== $wishlist->id,
            404,
            'Item no encontrado en esta lista.',
        );
    }
}
