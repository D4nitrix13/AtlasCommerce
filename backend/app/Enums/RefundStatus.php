<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados posibles de un reembolso.
 *
 * Máquina de estados:
 *   Pending   → Completed  (refund procesado, stock revertido, invoice impactada)
 *   Pending   → Failed     (refund rechazado — pasarela, fraude, etc.)
 *   Completed → terminal
 *   Failed    → terminal
 *
 * MVP: una vez completado o fallido, no se puede modificar. Para reintentos
 * fallidos, se crea un nuevo Refund asociado al mismo return_request.
 */
enum RefundStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Completed, self::Failed], strict: true),
            self::Completed, self::Failed => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], strict: true);
    }
}
