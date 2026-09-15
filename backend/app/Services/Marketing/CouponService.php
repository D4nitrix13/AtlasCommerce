<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Models\Carts\Cart;
use App\Models\Marketing\Coupon;
use App\Models\Stores\Store;
use Carbon\Carbon;
use RuntimeException;

/**
 * Servicio de validación y cálculo de cupones (RF-MKT-060).
 *
 * Responsabilidades:
 *  - Validar un código contra las reglas del cupón (activo, vigencia,
 *    límite de uso, monto mínimo, tenant).
 *  - Calcular el descuento en centavos que aplicaría al subtotal del
 *    carrito del cliente.
 *
 * Reglas:
 *  - Si el cupón pertenece a una tienda específica (`store_id` no nulo),
 *    sólo aplica a carritos de esa tienda.
 *  - El cupón no se "aplica" acá (no incrementa used_count) — eso lo
 *    hace `CheckoutService` al confirmar la orden. `validate()` es
 *    idempotente.
 *  - Si el cupón no es válido, `findForCode()` retorna null + el caller
 *    usa el helper `lastFailureReason()` para saber por qué.
 *    (Alternativa a throw — evita el "Premature end of PHP process"
 *    que algunas versiones de phpunit reportan al capturar
 *    excepciones en hooks).
 */
final class CouponService
{
    private string $lastFailureReason = '';

    /**
     * Busca un cupón por código en el contexto de la tienda actual.
     * Retorna null si no es válido. Usar `lastFailureReason()` para
     * saber el motivo.
     */
    public function findForCode(string $code, Store $store): ?Coupon
    {
        $this->lastFailureReason = '';

        $coupon = Coupon::query()
            ->where(column: 'code', operator: '=', value: $code)
            ->first();

        if ($coupon === null) {
            $this->lastFailureReason = 'El cupón no existe.';

            return null;
        }

        if ($coupon->store_id !== null && (int) $coupon->store_id !== (int) $store->id) {
            $this->lastFailureReason = 'El cupón no existe.';

            return null;
        }

        if (! $coupon->is_active) {
            $this->lastFailureReason = 'El cupón está inactivo.';

            return null;
        }

        $now = Carbon::now();
        if ($coupon->starts_at !== null && $coupon->starts_at->gt($now)) {
            $this->lastFailureReason = 'El cupón aún no está vigente.';

            return null;
        }

        if ($coupon->ends_at !== null && $coupon->ends_at->lt($now)) {
            $this->lastFailureReason = 'El cupón está expirado.';

            return null;
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            $this->lastFailureReason = 'El cupón agotó su límite de uso.';

            return null;
        }

        return $coupon;
    }

    public function lastFailureReason(): string
    {
        return $this->lastFailureReason;
    }

    /**
     * Calcula el descuento en centavos que aplicaría al carrito.
     *
     * Si el cupón no cumple `minimum_amount`, retorna 0 (no lanza —
     * el caller decide si mostrar el cupón como "válido pero no
     * aplica al carrito actual").
     */
    public function calculateDiscountCents(Coupon $coupon, int $subtotalCents): int
    {
        if ((float) $coupon->minimum_amount > 0) {
            $minimumCents = (int) round((float) $coupon->minimum_amount * 100);
            if ($subtotalCents < $minimumCents) {
                return 0;
            }
        }

        return match ($coupon->discount_type) {
            'percentage' => (int) round(
                $subtotalCents * (float) $coupon->value / 100,
                0,
                PHP_ROUND_HALF_EVEN,
            ),
            'fixed' => (int) round((float) $coupon->value * 100),
            default => throw new RuntimeException(
                "Tipo de descuento desconocido: {$coupon->discount_type}",
            ),
        };
    }

    /**
     * Helper: valida y calcula el descuento para el carrito actual.
     *
     * Retorna null + `lastFailureReason()` si el cupón es inválido.
     * Si es válido retorna `['coupon' => Coupon, 'discount_cents' => int]`.
     */
    public function validateForCart(string $code, Cart $cart, Store $store): ?array
    {
        $coupon = $this->findForCode(code: $code, store: $store);

        if ($coupon === null) {
            return null;
        }

        $cart->loadMissing('items');
        $subtotalCents = (int) $cart->items->sum('subtotal_cents');

        $discountCents = $this->calculateDiscountCents(coupon: $coupon, subtotalCents: $subtotalCents);

        return [
            'coupon' => $coupon,
            'discount_cents' => $discountCents,
        ];
    }
}
