<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Carts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Carts\StoreCartItemRequest;
use App\Http\Requests\Carts\UpdateCartItemRequest;
use App\Http\Resources\Carts\CartItemResource;
use App\Models\Carts\Cart;
use App\Models\Carts\CartItem;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Customers\Customer;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoints para manipular ítems dentro de un carrito.
 *
 * Reglas operativas:
 * - El precio unitario se toma del producto en el servidor (nunca del
 *   request). El cliente no puede inflar el total.
 * - Si el mismo producto+variante+custom_design se agrega dos veces,
 *   la cantidad se SUMA en lugar de duplicarse (UNIQUE cart_line_key).
 * - Stock: no se valida en MVP. La validación se hace en `CheckoutService`
 *   al momento de cerrar la orden.
 */
class CartItemController extends Controller
{
    public function __construct(
        private readonly StoreContextService $storeContext,
    ) {}

    /**
     * Lista los ítems de un carrito (con producto cargado).
     */
    public function index(Request $request, Cart $cart): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        abort_if(
            $cart->customer_id !== $customer->id,
            403,
            'El carrito no pertenece al cliente autenticado.',
        );

        $items = $cart->items()->with(['product', 'productVariant'])->get();

        return CartItemResource::collection($items)->response();
    }

    /**
     * Agrega un producto al carrito (o suma cantidad si ya existe).
     *
     * Body esperado:
     *   - product_id (required)
     *   - product_variant_id (optional)
     *   - custom_design_id (optional)
     *   - quantity (required, 1..999)
     */
    public function store(StoreCartItemRequest $request, Cart $cart): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        abort_if(
            $cart->customer_id !== $customer->id,
            403,
            'El carrito no pertenece al cliente autenticado.',
        );

        // Solo carritos activos aceptan items.
        abort_if(
            $cart->status !== 'active',
            422,
            "El carrito está en estado '{$cart->status}' y no acepta cambios.",
        );

        $product = Product::findOrFail($request->integer('product_id'));
        $variant = $request->filled('product_variant_id')
            ? ProductVariant::findOrFail($request->integer('product_variant_id'))
            : null;

        // El precio del servidor manda: toma del variant si hay, sino del producto.
        $unitPriceCents = $variant !== null
            ? (int) $variant->price_cents
            : (int) $product->base_price_cents;

        $quantity = $request->integer('quantity');
        $cartLineKey = $request->cartLineKey();

        $customDesignId = $request->filled('custom_design_id')
            ? $request->integer('custom_design_id')
            : null;

        [$item, $isNew] = DB::transaction(function () use (
            $cart,
            $product,
            $variant,
            $unitPriceCents,
            $quantity,
            $cartLineKey,
            $customDesignId,
        ): array {
            // Upsert por cart_line_key: si ya existe, suma cantidad.
            $existing = $cart->items()
                ->where('cart_line_key', $cartLineKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $newQty = $existing->quantity + $quantity;
                abort_if(
                    $newQty > 999,
                    422,
                    'La cantidad máxima por línea es 999.',
                );

                $existing->update([
                    'quantity' => $newQty,
                    'subtotal_cents' => $unitPriceCents * $newQty,
                ]);

                return [$existing->fresh(), false];
            }

            return [CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'custom_design_id' => $customDesignId,
                'cart_line_key' => $cartLineKey,
                'quantity' => $quantity,
                'unit_price_cents' => $unitPriceCents,
                'subtotal_cents' => $unitPriceCents * $quantity,
            ]), true];
        });

        $item->load(['product', 'productVariant']);

        $resource = (new CartItemResource($item))->response();

        return $isNew
            ? $resource->setStatusCode(201)
            : $resource;
    }

    /**
     * Actualiza la cantidad de un ítem existente.
     */
    public function update(
        UpdateCartItemRequest $request,
        Cart $cart,
        CartItem $item,
    ): JsonResponse {
        $customer = $this->resolveCustomer($request);
        abort_if(
            $cart->customer_id !== $customer->id,
            403,
            'El carrito no pertenece al cliente autenticado.',
        );
        abort_if(
            $item->cart_id !== $cart->id,
            404,
            'El ítem no pertenece a este carrito.',
        );

        $newQty = $request->integer('quantity');
        $item->update([
            'quantity' => $newQty,
            'subtotal_cents' => (int) $item->unit_price_cents * $newQty,
        ]);

        return (new CartItemResource($item->fresh()->load(['product', 'productVariant'])))
            ->response();
    }

    /**
     * Elimina un ítem del carrito.
     */
    public function destroy(
        Request $request,
        Cart $cart,
        CartItem $item,
    ): JsonResponse {
        $customer = $this->resolveCustomer($request);
        abort_if(
            $cart->customer_id !== $customer->id,
            403,
            'El carrito no pertenece al cliente autenticado.',
        );
        abort_if(
            $item->cart_id !== $cart->id,
            404,
            'El ítem no pertenece a este carrito.',
        );

        $item->delete();

        return response()->json(data: null, status: 204);
    }

    private function resolveCustomer(Request $request): Customer
    {
        $user = $request->user();
        abort_if($user === null, 401, 'Autenticación requerida.');

        $customer = Customer::where('user_id', $user->id)->first();
        abort_if($customer === null, 404, 'No existe perfil de cliente para este usuario.');

        return $customer;
    }
}
