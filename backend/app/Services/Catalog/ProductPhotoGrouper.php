<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Catalog\ProductImage;

/**
 * Define qué es una FOTO dentro de `product_images`.
 *
 * Una foto se persiste como N filas (400 / 800 / 1200 / 1600) que
 * comparten `product_id`, `variant_id` y `sort_order`, y se distinguen
 * sólo por `width` y por el `storage_key` (SHA-256 del binario). No hay
 * columna que las agrupe: la identidad de la foto es la terna
 * `(variant_id, sort_order)`.
 *
 * Esta clase existe para que esa identidad esté escrita UNA sola vez.
 * Tres consumidores dependen de ella y, si cada uno la reimplementara,
 * divergirían en silencio:
 *
 *   1. `StorefrontProductResource::galleryPerRole()` — colapsa cada foto
 *      a una entrada de galería con su `srcset`.
 *   2. `HoverImageBackfiller` — decide qué foto es la de hover y marca
 *      todas sus filas.
 *   3. `ProductImageController::reorder()` — reescribe `sort_order`
 *      sobre la foto completa, no fila por fila.
 *
 * Que el backfill y el storefront compartan la definición no es
 * opcional: sin ella, la bandera `is_hover` se escribiría sobre un
 * agrupamiento distinto del que la API agrupa al leer, y el catálogo
 * perdería la foto de hover sin que ninguna consulta devuelva un error.
 */
final class ProductPhotoGrouper
{
    /**
     * Agrupa las filas de `product_images` en fotos.
     *
     * El orden de salida es el de primera aparición de cada grupo en la
     * entrada, que es el orden de galería (`sort_order` ascendente, con
     * el desempate del orden de carga). Es el mismo criterio que usa la
     * galería del storefront, así que "la segunda foto" significa lo
     * mismo acá que en el Resource.
     *
     * @param  iterable<int, ProductImage>  $images  Filas de un solo producto.
     * @return list<array{variant_id: int|null, sort_order: int, rows: list<ProductImage>}>
     */
    public function group(iterable $images): array
    {
        /** @var array<string, array{variant_id: int|null, sort_order: int, rows: list<ProductImage>}> $byKey */
        $byKey = [];

        foreach ($images as $image) {
            $key = self::keyOf(image: $image);
            $byKey[$key] ??= [
                'variant_id' => $image->variant_id === null ? null : (int) $image->variant_id,
                'sort_order' => (int) $image->sort_order,
                'rows' => [],
            ];
            $byKey[$key]['rows'][] = $image;
        }

        return array_values($byKey);
    }

    /**
     * Fila que representa a la foto: la de mayor `width`.
     *
     * Es la que el storefront usa como `url`/`src` de la entrada de
     * galería, y la que lleva los flags (`is_primary`, `is_hover`) que el
     * consumidor lee.
     *
     * Cuando NINGUNA fila tiene `width` —filas heredadas de seeds
     * previos, sin pasar por `assets:backfill-image-variant`— no hay
     * "mayor": se devuelve la primera, y el llamador decide si eso
     * degrada el comportamiento. Adivinar sería peor que mostrar las
     * cuatro: una miniatura de 400px como portada.
     *
     * @param  list<ProductImage>  $rows  Filas de UNA foto.
     */
    public function representative(array $rows): ?ProductImage
    {
        $withWidth = array_values(array_filter(
            $rows,
            static fn (ProductImage $image): bool => $image->width !== null,
        ));

        if ($withWidth === []) {
            return $rows[0] ?? null;
        }

        // Empate por `id`: dos filas del mismo ancho son la misma foto en
        // versiones distintas del seed, y sin este criterio el representante
        // dependería del orden de lectura.
        usort(
            $withWidth,
            static fn (ProductImage $a, ProductImage $b): int => [$b->width, $a->id] <=> [$a->width, $b->id],
        );

        return $withWidth[0];
    }

    /**
     * ¿Alguna fila de la foto tiene el ancho conocido?
     *
     * `false` significa que la foto no se puede colapsar en una sola
     * entrada de galería: el storefront emite una entrada por fila para no
     * perder resoluciones.
     *
     * @param  list<ProductImage>  $rows
     */
    public function hasKnownWidths(array $rows): bool
    {
        foreach ($rows as $image) {
            if ($image->width !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clave de agrupación de una fila.
     *
     * `variant_id` NULL se agrupa bajo el mismo pseudo-escopo que usa la
     * galería del PDP ("unscoped"): fotos de producto sin acabado
     * asociado, caso legítimo y frecuente.
     */
    public static function keyOf(ProductImage $image): string
    {
        $variantKey = $image->variant_id === null ? 'unscoped' : (string) $image->variant_id;

        return $variantKey.'#'.(int) $image->sort_order;
    }
}