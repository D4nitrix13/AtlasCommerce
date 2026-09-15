<?php

declare(strict_types=1);

namespace App\Exceptions\Sales;

use App\Enums\OrderStatus;
use RuntimeException;

/**
 * Transición de estado no permitida en Order.
 *
 * Lanzada por `OrderService` cuando se intenta mover una orden a un
 * estado destino que la máquina de estados no permite desde el actual.
 */
final class InvalidOrderTransitionException extends RuntimeException
{
    public static function fromTo(OrderStatus $from, OrderStatus $to): self
    {
        return new self(
            "Transición de orden no permitida: {$from->value} → {$to->value}.",
        );
    }
}
