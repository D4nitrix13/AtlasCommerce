<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Carts\Cart;
use App\Services\Marketing\CouponService;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint público de validación de cupones (RF-MKT-060).
 *
 * Recibe un código + cart_id, valida el cupón contra las reglas del
 * store activo y devuelve el descuento que aplicaría al carrito actual.
 *
 * El cupón NO se aplica acá (no incrementa `used_count`) — eso lo
 * hace `CheckoutService` al confirmar la orden.
 */
class CouponValidateController extends Controller
{
    public function __construct(
        private readonly CouponService $coupons,
        private readonly StoreContextService $storeContext,
    ) {}

    /**
     * POST /api/coupons/validate
     *
     * Body:
     *  - code (required, string)
     *  - cart_id (required, int)
     *
     * Respuesta 200:
     *  {
     *    "data": {
     *      "code": "PROMO10",
     *      "discount_type": "percentage",
     *      "value": 10.0,
     *      "discount_cents": 1234
     *    }
     *  }
     *
     * Errores:
     *  - 422 si el cupón no existe, está inactivo, expirado, fuera de
     *    vigencia, agotó su límite, pertenece a otra tienda, o el
     *    carrito no cumple el `minimum_amount`.
     */
    public function validate(Request $request): JsonResponse
    {
        $data = $request->validate(rules: [
            'code' => ['required', 'string', 'max:60'],
            'cart_id' => ['required', 'integer', 'exists:carts,id'],
        ]);

        $store = $this->storeContext->getStore();
        if ($store === null) {
            return response()->json(
                data: ['message' => 'No hay tienda activa.'],
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $cart = Cart::query()->findOrFail(id: $data['cart_id']);

        $result = $this->coupons->validateForCart(
            code: strtoupper(string: $data['code']),
            cart: $cart,
            store: $store,
        );

        if ($result === null) {
            return response()->json(
                data: ['message' => $this->coupons->lastFailureReason()],
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $coupon = $result['coupon'];
        $discountCents = $result['discount_cents'];

        return response()->json(data: [
            'data' => [
                'code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'value' => (float) $coupon->value,
                'discount_cents' => $discountCents,
            ],
        ]);
    }
}
