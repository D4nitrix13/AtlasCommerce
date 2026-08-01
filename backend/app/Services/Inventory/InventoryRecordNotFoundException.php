<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use RuntimeException;

/**
 * No existe un registro de inventario para la combinación requerida.
 *
 * Lanzada cuando se intenta decrementar/Incrementar stock de un
 * producto/variante que no tiene fila en `inventories` para el
 * almacén dado. Es un error de integridad de datos: el stock
 * debería estar sembrado antes de aceptar ventas.
 */
final class InventoryRecordNotFoundException extends RuntimeException {}
