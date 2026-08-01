<?php

declare(strict_types=1);

namespace App\Exceptions\Returns;

use App\Enums\RefundStatus;
use App\Enums\ReturnRequestStatus;
use RuntimeException;

/**
 * Transición de estado no permitida en ReturnRequest.
 */
final class InvalidReturnTransitionException extends RuntimeException
{
    public static function fromTo(ReturnRequestStatus $from, ReturnRequestStatus $to): self
    {
        return new self(
            "Transición de return request no permitida: {$from->value} → {$to->value}.",
        );
    }
}

/**
 * Transición de estado no permitida en Refund.
 */
final class InvalidRefundTransitionException extends RuntimeException
{
    public static function fromTo(RefundStatus $from, RefundStatus $to): self
    {
        return new self(
            "Transición de refund no permitida: {$from->value} → {$to->value}.",
        );
    }
}
