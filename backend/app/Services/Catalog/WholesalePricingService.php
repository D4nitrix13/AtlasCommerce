<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\WholesalePrice;
use App\Models\Stores\Store;

/**
 * Servicio de precios mayoristas.
 *
 * Implementa la feature `wholesale_pricing` del sistema multi-instance.
 *
 * Comportamiento:
 *   - Si el store tiene la feature `wholesale_pricing` activa, busca
 *     en la tabla `wholesale_prices` el precio aplicable según la
 *     cantidad (`min_qty` mas alto que sea <= qty solicitada).
 *   - Si encuentra, retorna ese precio.
 *   - Si no encuentra (o el store no tiene la feature), retorna el
 *     precio base del producto.
 *
 * Ejemplo:
 * ```php
 * $store = Store::find(1);
 * $product = Product::find(1);
 * $service = app(WholesalePricingService::class);
 * $priceCents = $service->calculatePrice($product, $store, quantity: 10);
 * ```
 */
class WholesalePricingService
{
    /**
     * Feature slug que activa este comportamiento.
     */
    public const FEATURE_SLUG = 'wholesale_pricing';

    /**
     * Calcula el precio en centavos para (producto, store, cantidad).
     *
     * @param  Product  $product  Producto a cotizar.
     * @param  Store  $store  Tienda actual (resuelta por middleware).
     * @param  int  $quantity  Cantidad solicitada.
     * @return int Precio en centavos.
     */
    public function calculatePrice(Product $product, Store $store, int $quantity): int
    {
        // Si el store no tiene la feature, retornar precio base.
        if (! $store->hasFeature(self::FEATURE_SLUG)) {
            return $this->basePriceCents($product);
        }

        // Si qty es 0 o negativo, no hay descuento aplicable. Fallback.
        if ($quantity < 1) {
            return $this->basePriceCents($product);
        }

        // Buscar el precio mayorista con min_qty mas alto que aplique.
        $wholesale = WholesalePrice::query()
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->where(column: 'store_id', operator: '=', value: $store->id)
            ->where(column: 'min_qty', operator: '<=', value: $quantity)
            ->orderBy(column: 'min_qty', direction: 'desc')
            ->first();

        if ($wholesale === null) {
            return $this->basePriceCents($product);
        }

        return (int) $wholesale->price_cents;
    }

    /**
     * Precio base en centavos del producto.
     */
    private function basePriceCents(Product $product): int
    {
        return (int) $product->base_price_cents;
    }
}
