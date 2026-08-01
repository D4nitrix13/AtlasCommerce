<?php

namespace App\Services\Cart;

/**
 * Servicio para generar claves únicas de línea de carrito.
 *
 * Cada línea del carrito (producto + variante + diseño + opciones) necesita
 * una clave única para identificarla. La clave se genera como un hash SHA-256
 * de los componentes concatenados con separador `|`.
 *
 * Ejemplo de clave: `sha256("product:1|variant:3|design:7|color:red")`.
 *
 * Esto permite:
 * - Detectar duplicados al agregar al carrito.
 * - Identificar líneas para actualizar cantidad o eliminar.
 * - Mantener la integridad del carrito entre sesiones.
 */
class CartLineKeyService
{
    /**
     * Genera una clave única para una línea del carrito.
     *
     * Los componentes se ordenan alfabéticamente antes de concatenar
     * para garantizar que la misma combinación siempre produzca la
     * misma clave, sin importar el orden de las opciones.
     *
     * @param  int  $productId  ID del producto.
     * @param  int|null  $productVariantId  ID de la variante (null si es el producto base).
     * @param  int|null  $customDesignId  ID del diseño personalizado (null si es estándar).
     * @param  array<string, mixed>  $options  Opciones adicionales (color, talla, etc.).
     * @return string Hash SHA-256 de 64 caracteres.
     */
    public function generate(
        int $productId,
        ?int $productVariantId = null,
        ?int $customDesignId = null,
        array $options = [],
    ): string {
        ksort(array: $options);

        $parts = [
            'product' => $productId,
            'variant' => $productVariantId ?? 'none',
            'design' => $customDesignId ?? 'standard',
        ];

        foreach ($options as $key => $value) {
            $parts[$key] = $value;
        }

        return hash(algo: 'sha256', data: implode(separator: '|', array: $parts));
    }
}
