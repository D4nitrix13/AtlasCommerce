<?php

declare(strict_types=1);

use App\Models\Stores\Store;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla `wholesale_prices` para la feature `wholesale_pricing`.
 *
 * Cada registro define un precio especial por (producto, tienda,
 * cantidad mínima). El servicio `WholesalePricingService` usa esta
 * tabla cuando el store tiene la feature activa.
 *
 * La combinación `(product_id, store_id, min_qty)` es única: un
 * producto tiene un solo precio mayorista por tienda y por cantidad
 * mínima.
 *
 * Tabla: `wholesale_prices` — relación N:1 con `products` y `stores`.
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::create(table: 'wholesale_prices', callback: function (Blueprint $table): void {
            $table->id();

            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products', column: 'id')
                ->cascadeOnDelete();

            $table->foreignId(column: 'store_id')
                ->constrained(table: 'stores', column: 'id')
                ->cascadeOnDelete();

            // Cantidad mínima para que aplique este precio. Default 1
            // significa "precio base al por mayor" (sin mínimo).
            $table->unsignedInteger(column: 'min_qty')->default(value: 1);

            // Precio en centavos (mismo formato que products.base_price_cents).
            $table->unsignedBigInteger(column: 'price_cents');

            $table->timestamps();

            // Un solo precio por (producto, tienda, cantidad mínima).
            $table->unique(
                columns: ['product_id', 'store_id', 'min_qty'],
                name: 'wholesale_prices_product_store_qty_unique',
            );

            // Indice compuesto para queries rápidas en storefront.
            $table->index(
                columns: ['product_id', 'store_id'],
                name: 'wholesale_prices_product_store_idx',
            );
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'wholesale_prices');
    }
};
