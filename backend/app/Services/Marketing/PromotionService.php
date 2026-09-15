<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Models\Catalog\Product;
use App\Models\Marketing\Promotion;
use App\Models\Stores\Store;
use Carbon\Carbon;

/**
 * Servicio de promociones automáticas (RF-MKT-061).
 *
 * Responsabilidades:
 *  - Determinar si una promoción está vigente (activa + ventana temporal + tenant).
 *  - Calcular el descuento en centavos que aplicaría a un producto.
 *
 * Reglas:
 *  - Si la promoción pertenece a una tienda (`store_id` no nulo), sólo
 *    aplica en esa tienda.
 *  - Si la promoción no está vigente, retorna 0 silenciosamente.
 *  - Las promociones se evalúan a nivel producto: el caller consulta
 *    `applicableFor(productId, store)` para cada item del carrito.
 *
 * Tipos soportados:
 *  - `percentage`: descuento = precio * value / 100.
 *  - `fixed`: descuento = value * 100 cents.
 *  - Otros tipos (`buy_x_get_y`, etc.) no soportados en MVP — retorna 0.
 */
final class PromotionService
{
    /**
     * Verifica si una promoción está vigente en el contexto actual.
     *
     * Retorna false (en lugar de throw) para que el caller pueda ignorar
     * promociones inválidas sin interrumpir el flujo de cálculo.
     */
    public function isApplicable(Promotion $promotion, Store $store): bool
    {
        if (! $promotion->is_active) {
            return false;
        }

        if ($promotion->store_id !== null && (int) $promotion->store_id !== (int) $store->id) {
            return false;
        }

        $now = Carbon::now();

        if ($promotion->starts_at !== null && $promotion->starts_at->gt($now)) {
            return false;
        }

        if ($promotion->ends_at !== null && $promotion->ends_at->lt($now)) {
            return false;
        }

        return true;
    }

    /**
     * Calcula el descuento unitario en centavos que aplicaría una promoción
     * a un producto, dado un precio base.
     *
     * Retorna 0 si la promoción no aplica al contexto o si el tipo no
     * está soportado en MVP.
     */
    public function calculateDiscountCents(Promotion $promotion, Product $product, Store $store): int
    {
        if (! $this->isApplicable(promotion: $promotion, store: $store)) {
            return 0;
        }

        return match ($promotion->discount_type) {
            'percentage' => (int) round(
                (float) $product->base_price_cents * (float) $promotion->value / 100,
                0,
                PHP_ROUND_HALF_EVEN,
            ),
            'fixed' => (int) round((float) $promotion->value * 100),
            default => 0,
        };
    }
}
