<?php

declare(strict_types=1);

namespace App\Exceptions\Sales;

use RuntimeException;

/**
 * La orden no puede cancelarse por una razón externa a su máquina de estados.
 *
 * Ejemplos: tiene shipments ya despachados, tiene invoices con pagos que
 * requieren un refund flow distinto, etc. A diferencia de
 * `InvalidOrderTransitionException` (transición prohibida por el enum), esta
 * excepción representa bloqueos del entorno.
 */
final class OrderNotCancellableException extends RuntimeException
{
    public static function dueToShipmentInTransit(string $shipmentStatus): self
    {
        return new self(
            "La orden no puede cancelarse: tiene un envío en estado '{$shipmentStatus}'.",
        );
    }
}
