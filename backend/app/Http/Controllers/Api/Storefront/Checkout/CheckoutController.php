<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront\Checkout;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\Checkout\CheckoutConfirmRequest;
use App\Http\Requests\Storefront\Checkout\CheckoutPlaceRequest;
use App\Http\Requests\Storefront\Checkout\CheckoutPreviewRequest;
use App\Http\Resources\Storefront\Checkout\CheckoutOrderResource;
use App\Http\Resources\Storefront\Checkout\CheckoutPreviewResource;
use App\Models\Carts\Cart;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Marketing\Coupon;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Order;
use App\Models\Shipping\ShippingZone;
use App\Services\Sales\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checkout multi-step (F4).
 *
 * Tres acciones explícitas — NO es un resource CRUD:
 *  - preview  (POST /storefront/checkout/preview): calcula totales sin side effects.
 *  - place    (POST /storefront/checkout/place): crea la orden real.
 *  - confirm  (POST /storefront/checkout/confirm/{orderId}): webhook payment stub.
 *
 * Patrón "Stripe-like placeholder": el PSP se simula; el `payment_intent_id`
 * es un string libre que se persiste como `payments.external_reference`.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
    ) {}

    /**
     * POST /storefront/checkout/preview
     *
     * Body: cart_id (required), coupon_code (optional), shipping_zone_id (optional).
     *
     * Devuelve los totales calculados del carrito SIN crear orden ni
     * decrementar stock. Idempotente y barato — el frontend puede
     * llamarlo varias veces antes de hacer `place`.
     */
    public function preview(CheckoutPreviewRequest $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        $cart = $this->resolveOwnedCart($request, $customer);

        $cart->load('items');

        $subtotalCents = (int) $cart->items->sum('subtotal_cents');
        $discountCents = 0;
        $couponCode = $request->input('coupon_code');
        if (is_string($couponCode) && $couponCode !== '') {
            $coupon = Coupon::query()->byCode(code: strtoupper($couponCode))->first();
            if ($coupon !== null && $coupon->isValid()) {
                $discountCents = $coupon->discount_type === 'percentage'
                    ? (int) round($subtotalCents * ((float) $coupon->value / 100))
                    : (int) round((float) $coupon->value * 100);
            }
        }

        $shippingCents = 0;
        if ($shippingZoneId = $request->integer('shipping_zone_id')) {
            $shippingCents = (int) (ShippingZone::query()
                ->where('id', $shippingZoneId)
                ->value('flat_rate_cents') ?? 0);
        }

        $taxCents = 0; // Tax snapshot is computed at `place` time via CheckoutService.
        $totalCents = max(0, $subtotalCents - $discountCents + $shippingCents + $taxCents);

        $payload = [
            'cart_id' => $cart->id,
            'currency_code' => $cart->currency?->code ?? 'USD',
            'subtotal_cents' => $subtotalCents,
            'discount_cents' => $discountCents,
            'tax_cents' => $taxCents,
            'shipping_cents' => $shippingCents,
            'total_cents' => $totalCents,
            'items_count' => $cart->items->count(),
        ];

        return response()->json(data: [
            'data' => (new CheckoutPreviewResource(resource: $payload))->resolve(),
        ]);
    }

    /**
     * POST /storefront/checkout/place
     *
     * Body: cart_id, shipping_address_id, payment_method_id, plus optional
     * shipping_zone_id, coupon_code, notes, payment_intent_id.
     *
     * Crea la orden (y su invoice) transaccionalmente. NO marca como pagada
     * — eso lo hace `confirm` cuando el PSP confirma.
     */
    public function place(CheckoutPlaceRequest $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        $cart = $this->resolveOwnedCart($request, $customer);

        $address = CustomerAddress::query()->findOrFail((int) $request->integer('shipping_address_id'));
        abort_if(
            $address->customer_id !== $customer->id,
            403,
            'La dirección no pertenece al cliente autenticado.',
        );

        $paymentMethod = PaymentMethod::query()->findOrFail((int) $request->integer('payment_method_id'));

        $shippingZone = null;
        if ($request->filled('shipping_zone_id')) {
            $shippingZone = ShippingZone::query()->findOrFail((int) $request->integer('shipping_zone_id'));
        }

        $coupon = null;
        if (is_string($couponCode = $request->input('coupon_code')) && $couponCode !== '') {
            $coupon = Coupon::query()->byCode(code: strtoupper($couponCode))->first();
        }

        try {
            $order = $this->checkout->checkout(
                cart: $cart,
                shippingAddress: $address,
                paymentMethod: $paymentMethod,
                shippingZone: $shippingZone,
                coupon: $coupon,
                notes: $request->input('notes'),
            );
        } catch (RuntimeException $e) {
            return response()->json(
                data: ['message' => $e->getMessage()],
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        // Stub: si llega payment_intent_id, persiste un Payment "pending"
        // contra la invoice recién creada.
        $paymentIntentId = $request->input('payment_intent_id');
        if (is_string($paymentIntentId) && $paymentIntentId !== '') {
            $invoice = $order->invoices()->first();
            if ($invoice !== null) {
                $payment = Payment::query()->create([
                    'customer_id' => $customer->id,
                    'store_id' => $order->store_id,
                    'currency_id' => $order->currency_id,
                    'payment_method_id' => $paymentMethod->id,
                    'amount_cents' => $order->total_cents,
                    'exchange_rate_snapshot' => '1.00000000',
                    'reference' => $paymentIntentId,
                    'status' => 'pending',
                    'paid_at' => null,
                ]);
                $payment->invoices()->attach($invoice->id, ['applied_amount_cents' => $order->total_cents]);
            }
        }

        $order->load(['items', 'currency']);

        $payload = (new CheckoutOrderResource(resource: $order))->resolve();
        $payload['payment_intent_id'] = $paymentIntentId;
        $payload['payment_status'] = $paymentIntentId !== null ? 'pending' : 'awaiting';

        return response()->json(data: [
            'data' => $payload,
        ], status: Response::HTTP_CREATED);
    }

    /**
     * POST /storefront/checkout/confirm/{orderId}
     *
     * Webhook de pago simulado. Acepta `payment_intent_id` + `status`
     * y marca el Payment correspondiente como succeeded/failed.
     */
    public function confirm(CheckoutConfirmRequest $request, int $orderId): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $order = Order::query()->findOrFail($orderId);
        abort_if(
            $order->customer_id !== $customer->id,
            403,
            'La orden no pertenece al cliente autenticado.',
        );

        $paymentIntentId = (string) $request->input('payment_intent_id');
        $status = (string) $request->input('status', 'succeeded');

        $payment = Payment::query()
            ->where(column: 'reference', operator: '=', value: $paymentIntentId)
            ->whereHas('invoices', function ($q) use ($orderId): void {
                $q->where('order_id', $orderId);
            })
            ->first();

        if ($payment === null) {
            return response()->json(data: [
                'message' => 'No se encontró el pago asociado a este payment_intent_id.',
            ], status: Response::HTTP_NOT_FOUND);
        }

        $payment->update([
            'status' => $status,
            'paid_at' => $status === 'succeeded' ? now() : null,
        ]);

        return response()->json(data: [
            'data' => [
                'order_id' => $order->id,
                'payment_intent_id' => $paymentIntentId,
                'status' => $status,
                'order_status' => $order->status->value,
            ],
        ]);
    }

    private function resolveCustomer(Request $request): Customer
    {
        $user = $request->user();
        abort_if($user === null, 401, 'Autenticación requerida.');

        $customer = Customer::query()->where('user_id', $user->id)->first();
        abort_if($customer === null, 404, 'No existe perfil de cliente para este usuario.');

        return $customer;
    }

    private function resolveOwnedCart(Request $request, Customer $customer): Cart
    {
        $cart = Cart::query()->findOrFail((int) $request->integer('cart_id'));
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
}
