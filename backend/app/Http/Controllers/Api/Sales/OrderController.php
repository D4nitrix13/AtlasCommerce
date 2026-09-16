<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\Sales\InvalidOrderTransitionException;
use App\Exceptions\Sales\OrderNotCancellableException;
use App\Http\Controllers\Controller;
use App\Http\Concerns\CursorPaginates;
use App\Http\Requests\Sales\CancelOrderRequest;
use App\Http\Requests\Sales\CheckoutRequest;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Carts\Cart;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Order;
use App\Models\Shipping\ShippingZone;
use App\Services\Sales\CheckoutService;
use App\Services\Sales\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de pedidos.
 *
 * `store` es el corazón del checkout: convierte un carrito activo en
 * pedido + invoice (transaccional). El resto del CRUD es lectura.
 */
class OrderController extends Controller
{
    use CursorPaginates;

    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly OrderService $orders,
    ) {}

    /**
     * Lista los pedidos del cliente autenticado.
     *
     * Filtra automáticamente por la tienda activa (global scope
     * `BelongsToStore`). Staff/admin puede usar el endpoint sin
     * filtro de cliente y agregar ?customer_id=X en el futuro.
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $query = Order::with(['items', 'invoices', 'currency'])
            ->where('customer_id', $customer->id)
            // RF-PERF-022: cursor pagination. `id` como tiebreaker para
            // que el cursor sea estable cuando dos órdenes comparten
            // el mismo `ordered_at` (created_at ties).
            ->orderByDesc('ordered_at')
            ->orderByDesc('id');

        return $this->cursorPaginatedResponse(
            request: $request,
            query: $query,
            resourceClass: OrderResource::class,
        );
    }

    /**
     * Devuelve un pedido específico (verifica propiedad del cliente).
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        abort_if(
            $order->customer_id !== $customer->id,
            403,
            'El pedido no pertenece al cliente autenticado.',
        );

        $order->load(['items', 'invoices.items', 'currency']);

        return (new OrderResource($order))->response();
    }

    /**
     * Checkout: convierte un carrito activo en pedido + invoice.
     *
     * Body:
     *   - cart_id (required)
     *   - shipping_address_id (required, debe ser del cliente)
     *   - payment_method_id (required)
     *   - notes (optional)
     *
     * Respuesta 201 con `OrderResource` (items + invoice cargados).
     * El pago NO se crea aquí — se registra vía `POST /api/payments`
     * después de confirmar el método de pago externo.
     *
     * Errores:
     *   - 404 si cart/address/payment_method no existen.
     *   - 422 si carrito está vacío o no es `active`.
     *   - 403 si la dirección o el carrito no son del cliente.
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $cart = $this->resolveOwnedCart(request: $request, customer: $customer);
        $address = $this->resolveOwnedAddress(
            customer: $customer,
            addressId: (int) $request->integer('shipping_address_id'),
        );
        $paymentMethod = PaymentMethod::findOrFail($request->integer('payment_method_id'));

        $shippingZone = null;
        if ($request->filled('shipping_zone_id')) {
            $shippingZone = ShippingZone::findOrFail(
                $request->integer('shipping_zone_id'),
            );
        }

        $order = $this->checkout->checkout(
            cart: $cart,
            shippingAddress: $address,
            paymentMethod: $paymentMethod,
            shippingZone: $shippingZone,
            notes: $request->input('notes'),
        );

        return (new OrderResource($order->load(['items', 'invoices.items', 'currency'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Cancela una orden (cancelación hard con rollback completo).
     *
     * Body opcional:
     *   - observation (string, max 500 chars) — nota del admin para el historial.
     *
     * Respuesta 200 con `OrderResource` (items + invoices + shipments + status history).
     *
     * Errores:
     *   - 422 si la transición de estado no es válida o si un shipment está en estado no cancelable.
     *   - 403 si el usuario no tiene el permiso `sales.orders.cancel` (validado por middleware).
     *   - 404 si la orden no existe.
     */
    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        try {
            $cancelled = $this->orders->cancel(
                order: $order,
                user: $request->user(),
                observation: $request->input('observation'),
            );
        } catch (InvalidOrderTransitionException|OrderNotCancellableException $e) {
            return response()->json(data: ['message' => $e->getMessage()], status: 422);
        }

        return (new OrderResource(
            $cancelled->load(['items', 'invoices.items', 'shipments']),
        ))->response();
    }

    /**
     * Resuelve el carrito verificando que pertenece al cliente.
     *
     * El global scope `BelongsToStore` ya filtra por tienda, pero
     * igualmente verificamos `customer_id` para evitar que un cliente
     * use el carrito de otro.
     */
    private function resolveOwnedCart(Request $request, Customer $customer): Cart
    {
        $cart = Cart::with('items')->findOrFail(
            $request->integer('cart_id'),
        );

        abort_if(
            $cart->customer_id !== $customer->id,
            403,
            'El carrito no pertenece al cliente autenticado.',
        );
        abort_if(
            $cart->status !== 'active',
            422,
            "El carrito está en estado '{$cart->status}' y no puede convertirse en pedido.",
        );

        return $cart;
    }

    /**
     * Resuelve la dirección verificando que pertenece al cliente.
     */
    private function resolveOwnedAddress(Customer $customer, int $addressId): CustomerAddress
    {
        $address = CustomerAddress::findOrFail($addressId);

        abort_if(
            $address->customer_id !== $customer->id,
            403,
            'La dirección no pertenece al cliente autenticado.',
        );

        return $address;
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
