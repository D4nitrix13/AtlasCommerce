<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\BulkCartItemStoreRequest;
use App\Http\Requests\Account\BulkCartItemUpdateRequest;
use App\Http\Resources\Account\BulkCartItemResource;
use App\Models\Account\BulkCartItem;
use App\Models\Customers\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD del bulk-cart del cliente autenticado.
 *
 * GET    /account/bulk-cart        index
 * POST   /account/bulk-cart        store
 * GET    /account/bulk-cart/{item} show
 * PUT    /account/bulk-cart/{item} update
 * DELETE /account/bulk-cart/{item} destroy
 *
 * Cada item es una línea de "pedido grande" (B2B) que normalmente se
 * termina procesando vía RFQ. Persiste en `bulk_cart_items`.
 */
class BulkCartController extends Controller
{
    /**
     * GET /account/bulk-cart
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $items = BulkCartItem::query()
            ->with(['product', 'productVariant'])
            ->where(column: 'customer_id', operator: '=', value: $customer->id)
            ->orderBy('id')
            ->get();

        return response()->json(data: [
            'data' => BulkCartItemResource::collection(resource: $items)->resolve(),
        ]);
    }

    /**
     * POST /account/bulk-cart
     */
    public function store(BulkCartItemStoreRequest $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $item = BulkCartItem::query()->create([
            'customer_id' => $customer->id,
            'product_id' => (int) $request->integer('product_id'),
            'product_variant_id' => $request->integer('product_variant_id') ?: null,
            'quantity' => (int) $request->integer('quantity'),
            'notes' => $request->input('notes'),
        ]);

        $item->load(['product', 'productVariant']);

        return response()->json(data: [
            'data' => (new BulkCartItemResource(resource: $item))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * GET /account/bulk-cart/{bulk_cart}
     */
    public function show(Request $request, int $bulk_cart): JsonResponse
    {
        $item = BulkCartItem::query()->findOrFail($bulk_cart);
        $customer = $this->resolveCustomer($request);
        $this->assertOwnership(item: $item, customer: $customer);

        $item->load(['product', 'productVariant']);

        return response()->json(data: [
            'data' => (new BulkCartItemResource(resource: $item))->resolve(),
        ]);
    }

    /**
     * PUT /account/bulk-cart/{bulk_cart}
     */
    public function update(BulkCartItemUpdateRequest $request, int $bulk_cart): JsonResponse
    {
        $item = BulkCartItem::query()->findOrFail($bulk_cart);
        $customer = $this->resolveCustomer($request);
        $this->assertOwnership(item: $item, customer: $customer);

        $item->update(attributes: $request->validated());
        $item->load(['product', 'productVariant']);

        return response()->json(data: [
            'data' => (new BulkCartItemResource(resource: $item))->resolve(),
        ]);
    }

    /**
     * DELETE /account/bulk-cart/{bulk_cart}
     */
    public function destroy(Request $request, int $bulk_cart): JsonResponse
    {
        $item = BulkCartItem::query()->findOrFail($bulk_cart);
        $customer = $this->resolveCustomer($request);
        $this->assertOwnership(item: $item, customer: $customer);

        $item->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }

    private function resolveCustomer(Request $request): Customer
    {
        $user = $request->user();
        abort_if($user === null, 401, 'Autenticación requerida.');

        $customer = Customer::query()->where('user_id', $user->id)->first();
        abort_if($customer === null, 404, 'No existe perfil de cliente para este usuario.');

        return $customer;
    }

    private function assertOwnership(BulkCartItem $item, Customer $customer): void
    {
        if ((int) $item->customer_id !== (int) $customer->id) {
            abort(
                code: 403,
                message: 'El item no pertenece al cliente autenticado. item_exists='.($item->exists ? 'yes' : 'no').' item_id='.($item->id ?? 'null').' item.customer_id='.($item->customer_id ?? 'null').' auth_customer_id='.$customer->id,
            );
        }
    }
}
