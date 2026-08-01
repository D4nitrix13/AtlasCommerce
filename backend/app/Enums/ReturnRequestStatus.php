<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados posibles de una solicitud de devolución.
 *
 * Máquina de estados:
 *   Pending    → Approved   (admin acepta la devolución)
 *   Pending    → Rejected   (admin rechaza — no hay reembolso)
 *   Pending    → Cancelled  (cliente cancela antes de la resolución)
 *   Approved   → Completed  (reembolso procesado, stock revertido)
 *   Approved   → Cancelled  (admin cancela después de aprobar)
 *   Rejected   → terminal
 *   Completed  → terminal
 *   Cancelled  → terminal
 */
enum ReturnRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Approved, self::Rejected, self::Cancelled], strict: true),
            self::Approved => in_array($next, [self::Completed, self::Cancelled], strict: true),
            self::Rejected, self::Completed, self::Cancelled => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Completed, self::Cancelled], strict: true);
    }
}
