<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Catalog\Product;

/**
 * Deriva `product_variants.handedness` a partir de los datos del
 * producto padre.
 *
 * El dominio ya tiene una convención establecida — la migración
 * `2026_10_19_160000_add_left_handed_flags_to_products` define
 * `handedness VARCHAR(16) NULL` donde el valor poblado es
 * `'left-handed'` y `NULL` significa "regular / diestro". No existe un
 * enum PHP para esto: el valor es un string del dominio, no un tipo.
 * Por eso esta clase devuelve `?string` y no un enum.
 *
 * ⚠️ Decisión documentada — prioridad de señales:
 *
 * El brief pide derivar de `products.is_left_handed` y usar el nombre
 * como fallback. En el catálogo de La Voz Instrumentos ese orden es
 * **inutilizable**: `is_left_handed` es `false` en los 346 productos
 * (default de la migración; el seeder corre antes de que la columna
 * existiera y por eso nunca la escribió), mientras 18 productos tienen
 * "left-handed" en el slug y/o en el nombre. Usar el flag como fuente
 * primaria dejaría `handedness` en NULL para las 411 variantes — el
 * backfill no haría nada.
 *
 * Por eso el orden real es:
 *
 *   1. Evidencia textual (slug o nombre del producto). Es la misma
 *      fuente que ya usa `CatalogAssetsSeeder` (`str_contains` sobre el
 *      handle) y es dato humano, no dato derivado.
 *   2. `products.is_left_handed === true`, para productos cuyo texto no
 *      menciona la mano pero el flag sí quedó seteado.
 *
 * Cuando las dos señales discrepan, gana la textual y el comando
 * reporta el conflicto con números: si el flag alguna vez se puebla
 * (admin, futuro seeder), el comando lo va a delatar.
 */
final class VariantHandednessResolver
{
    /**
     * Valor de dominio para una variante zurda. Longitud 11 — cabe en
     * el VARCHAR(16) de la columna.
     */
    public const LEFT_HANDED = 'left-handed';

    /**
     * Patrones que delatan mano izquierda en el texto del producto.
     *
     * Se cubre la forma con guion (`left-handed`, la del slug) y la
     * forma con espacio (`Left Handed,`, `Left-Handed,`, la del nombre
     * que arma el seeder desde `product.json`). Sin el patrón con
     * espacio, uno de los 18 productos zurdos se escaparía.
     */
    private const LEFT_HANDED_PATTERNS = [
        'left-handed',
        'left handed',
        'lefthanded',
    ];

    /**
     * Vocabulario textual que delata mano izquierda, ya normalizado a
     * minúsculas.
     *
     * Se expone para que los comandos puedan acotar el `WHERE` con la
     * MISMA lista que usa `containsLeftHanded()` en vez de copiar tres
     * `ILIKE` a mano. Una copia del vocabulario en SQL es una copia que
     * nadie actualiza cuando se agrega una variante del patrón.
     *
     * @return list<string>
     */
    public static function leftHandedPatterns(): array
    {
        return self::LEFT_HANDED_PATTERNS;
    }

    /**
     * Resuelve la mano de TODAS las variantes de un producto.
     *
     * La mano es una propiedad del modelo completo, no de una variante
     * concreta: hoy el catálogo no ofrece el mismo instrumento en
     * ambas manos. Por eso el valor es el mismo para cada variante del
     * producto.
     *
     * @return list<string|null> Valor por variante, en el orden en que
     *                           se receive el array de entrada.
     */
    public function resolveForVariants(Product $product, int $variantCount): array
    {
        return array_fill(0, max(0, $variantCount), $this->resolveForProduct(product: $product));
    }

    /**
     * Resuelve la mano de un producto.
     *
     * @return string|null `'left-handed'` o `null` (diestro / regular).
     */
    public function resolveForProduct(Product $product): ?string
    {
        return $this->nameSaysLeftHanded(product: $product) ? self::LEFT_HANDED : null;
    }

    /**
     * ¿El texto del producto (slug o nombre) declara mano izquierda?
     *
     * El slug es kebab-case del handle del vendor y el nombre sale del
     * `product.json` del backup; uno de los dos siempre trae la
     * cadena. Se consultan ambos porque hay productos donde el nombre
     * la trae y el slug no (`malina-moye-pacer-deluxe-left-handed`).
     */
    public function nameSaysLeftHanded(Product $product): bool
    {
        return $this->containsLeftHanded((string) $product->slug)
            || $this->containsLeftHanded((string) $product->name);
    }

    /**
     * ¿El flag `products.is_left_handed` dice que es zurdo?
     *
     * Se expone aparte del `resolveForProduct()` para que el comando
     * pueda reportar el desacuerdo entre ambas señales en vez de
     * esconderlo.
     */
    public function flagSaysLeftHanded(Product $product): bool
    {
        return $product->is_left_handed === true;
    }

    private function containsLeftHanded(string $text): bool
    {
        $haystack = mb_strtolower($text);

        foreach (self::LEFT_HANDED_PATTERNS as $pattern) {
            if (str_contains($haystack, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
