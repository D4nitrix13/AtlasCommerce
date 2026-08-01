<?php

declare(strict_types=1);

namespace App\Services\Payments;

use RuntimeException;

/**
 * Excepción lanzada cuando un pago excede el saldo pendiente de una invoice.
 *
 * El controller HTTP la captura y la traduce a HTTP 422.
 */
final class OverpaymentException extends RuntimeException {}
