<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados posibles de un envío.
 *
 * Máquina de estados:
 *   Pending    → Shipped   (admin marca como despachado)
 *   Pending    → Cancelled (admin cancela antes de despachar)
 *   Shipped    → InTransit (en camino, opcional)
 *   Shipped    → Delivered (entregado al cliente)
 *   InTransit  → Delivered (entregado al cliente)
 *   Delivered  → terminal
 *   Cancelled  → terminal
 */
enum ShipmentStatus: string
{
    case Pending = 'pending';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * Determina si la transición al nuevo estado es válida.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Shipped, self::Cancelled], strict: true),
            self::Shipped => in_array($next, [self::InTransit, self::Delivered], strict: true),
            self::InTransit => in_array($next, [self::Delivered], strict: true),
            self::Delivered, self::Cancelled => false,
        };
    }

    /**
     * Indica si el estado es terminal (no acepta más transiciones).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Delivered, self::Cancelled => true,
            default => false,
        };
    }
}
