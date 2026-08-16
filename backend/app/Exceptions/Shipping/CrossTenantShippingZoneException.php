<?php

declare(strict_types=1);

namespace App\Exceptions\Shipping;

use RuntimeException;

/**
 * Excepción cuando una `ShippingZone` pertenece a otra tienda que la
 * invoice que la referencia.
 *
 * Lanzada por `ShipmentService::createForInvoice()` cuando la zona
 * asociada a la invoice pertenece a otro tenant. Es un fail-loud —
 * siempre debería explotar en lugar de fallar silenciosamente o
 * sustituir la zona, porque:
 *
 *  1. La zona referencia tarifas distintas (cross-tenant: leak de
 *     pricing).
 *  2. La zona referencia cobertura geográfica distinta (cross-tenant:
 *     leak de geografía de envío).
 *
 * El método estático `fromZone()` construye el mensaje con el contexto
 * necesario para debugging y logging estructurado.
 */
final class CrossTenantShippingZoneException extends RuntimeException
{
    /**
     * Construye la excepción con el contexto del tenant esperado vs
     * el tenant real de la zona.
     */
    public static function fromZone(int $expectedStoreId, int $actualStoreId): self
    {
        return new self(
            "La shipping_zone pertenece a la tienda {$actualStoreId} pero la invoice pertenece a {$expectedStoreId} — referencia cross-tenant bloqueada.",
        );
    }
}
