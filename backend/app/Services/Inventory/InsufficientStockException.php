<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use RuntimeException;

/**
 * Stock disponible insuficiente para satisfacer la operación.
 *
 * `available = current_stock - reserved_stock`. Si la cantidad
 * solicitada supera el disponible, se lanza esta excepción.
 */
final class InsufficientStockException extends RuntimeException {}
