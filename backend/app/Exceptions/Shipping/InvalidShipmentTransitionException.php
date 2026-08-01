<?php

declare(strict_types=1);

namespace App\Exceptions\Shipping;

use App\Enums\ShipmentStatus;
use RuntimeException;

/**
 * Transición de estado de shipment no permitida.
 *
 * Lanzada cuando se intenta cambiar el status de un shipment
 * a un estado que la máquina de estados no permite desde el
 * estado actual (ej. delivered → shipped).
 */
final class InvalidShipmentTransitionException extends RuntimeException
{
    public static function fromTo(ShipmentStatus $from, ShipmentStatus $to): self
    {
        return new self(
            "Transición de estado de shipment no permitida: {$from->value} → {$to->value}.",
        );
    }
}
