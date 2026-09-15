<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados posibles de un pedido.
 *
 * Máquina de estados:
 *   Pending     → Processing   (pago confirmado → entra a fulfillment)
 *   Pending     → Cancelled    (admin cancela antes del pago)
 *   Processing  → Completed    (entregado y aceptado por el cliente)
 *   Processing  → Cancelled    (admin cancela post-pago con refund + stock revert)
 *   Completed   → terminal
 *   Cancelled   → terminal
 *
 * Las transiciones quedan registradas en `order_status_histories` con el
 * usuario responsable y una observación opcional.
 */
enum OrderStatus: string
{
    case Pending = 'pending';

    case Processing = 'processing';

    case Completed = 'completed';

    case Cancelled = 'cancelled';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Processing, self::Cancelled], strict: true),
            self::Processing => in_array($next, [self::Completed, self::Cancelled], strict: true),
            self::Completed, self::Cancelled => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], strict: true);
    }
}
