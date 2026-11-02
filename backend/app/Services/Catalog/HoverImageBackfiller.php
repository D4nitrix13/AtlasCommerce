<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Catalog\ProductImage;

/**
 * Decide qué foto de cada producto es la de hover (`is_hover = true`).
 *
 * CONTEXTO — POR QUÉ EXISTE ESTA CLASE
 *
 * La card del catálogo pasaba a la foto de hover buscando en el `alt_text`
 * el sufijo " - vista back", con `backbody` y `body` como escalones. Ese
 * vocabulario salió del scraper que rotula las fotos del fabricante; el
 * administrador no lo piensa y la card no debería adivinarlo.
 *
 * El paso de `is_hover` tiene un costo oculto: el catálogo sembrado trae
 * 10.244 imágenes y NINGUNA tiene la bandera. Si el frontend pasa a leer
 * la bandera sin derivarla primero, los 355 productos con fotos pierden la
 * imagen de hover de una, sin error y sin log. Esta clase es el paso
 * intermedio: reproduce exactamente lo que la card elegía antes y lo
 * escribe como dato.
 *
 * LA REGLA REPRODUCIDA (en este orden)
 *
 *   1. Foto portada = la primera foto (orden de galería) cuya fila
 *      representante tenga `is_primary`. Sin primaria marcada, la primera.
 *      Es lo que hacía `resolvePrimaryImage()`.
 *   2. Ámbito de búsqueda = las fotos de la MISMA variante que la portada.
 *      Si esa variante no tiene ninguna foto —caso real de los productos
 *      con imágenes huérfanas de `variant_id`— el ámbito es todo el
 *      producto. Es el `fallbackSource` de `resolveBackImage()`.
 *   3. Dentro del ámbito, gana la primera foto cuyo rol sea `back`; si no
 *      hay, `backbody`; si no, `body`. En ese orden, y sin roles de detalle
 *      (`headstock`, `pickup`, `knobs`, …). Es la cascada `BACK_ROLES`.
 *   4. Sin ninguna de las tres, el producto NO tiene hover y se deja como
 *      está: la card degrada a la portada, que es lo que ya hacía.
 *
 * El rol se lee del `alt_text` igual que lo leía la card: el último
 * segmento tras " - vista ", en minúsculas.
 *
 * POR QUÉ EL ÁMBITO ES LA VARIANTE Y NO EL PRODUCTO
 *
 * El acabado ES la identidad del SKU en un catálogo de instrumentos: si la
 * card anuncia Ébony y al pasar el cursor aparece la espalda de un Sunburst,
 * se está mostrando otro producto del que el que se vende. La cascada se
 * limita a la variante de la portada por el mismo motivo por el que la
 * portada misma se elige dentro de una variante.
 *
 * LA MARCA VA EN TODAS LAS FILAS DE LA FOTO
 *
 * Ver la nota de granularidad en la migración
 * `2026_10_23_210000_add_is_hover_to_product_images_table`. Acá se ve el
 * efecto: marcar una sola fila haría que `galleryPerRole()` —que colapsa
 * cada foto a su fila de mayor `width`— podría devolver una entrada sin
 * bandera para un producto que sí tiene hover.
 */
final class HoverImageBackfiller
{
    /**
     * Cascada de roles que el runtime usaba antes, en orden de prioridad.
     *
     * Vive acá, y no en el frontend, porque es la traducción del
     * vocabulario de procedencia del scraper al dominio. Es lo último que
     * queda de ese vocabulario en el camino de escritura: cuando el
     * catálogo se vuelva a sembrar por la API, esta lista puede morir sin
     * tocar una sola línea del storefront.
     *
     * @var list<string>
     */
    private const BACK_ROLES = ['back', 'backbody', 'body'];

    /**
     * Sufijo con el que el scraper rotula el rol en `alt_text`.
     */
    private const ROLE_MARKER = ' - vista ';

    public function __construct(private readonly ProductPhotoGrouper $grouper) {}

    /**
     * Calcula la foto de hover de un producto, sin escribir.
     *
     * @param  iterable<int, ProductImage>  $images  Filas del producto, en
     *                                               cualquier orden: el
     *                                               agrupamiento se hace
     *                                               acá.
     * @return list<ProductImage> Filas a marcar con `is_hover`. Vacío
     *                           cuando el producto no tiene hover.
     */
    public function hoverRowsFor(iterable $images): array
    {
        $photos = $this->grouper->group($images);

        if ($photos === []) {
            return [];
        }

        $primaryPhoto = $this->primaryPhoto(photos: $photos);

        if ($primaryPhoto === null) {
            return [];
        }

        foreach (self::BACK_ROLES as $role) {
            $match = $this->firstPhotoWithRole(
                photos: $this->searchScope(photos: $photos, primaryPhoto: $primaryPhoto),
                role: $role,
            );

            if ($match !== null) {
                return $match['rows'];
            }
        }

        return [];
    }

    /**
     * Rol de una foto: el último segmento del `alt_text` tras el marcador,
     * en minúsculas. `null` cuando el texto no lo trae.
     *
     * @param  list<ProductImage>  $rows  Filas de UNA foto.
     */
    public function roleOf(array $rows): ?string
    {
        $representative = $this->grouper->representative(rows: $rows);
        $altText = (string) ($representative?->alt_text ?? '');
        $position = strrpos(haystack: $altText, needle: self::ROLE_MARKER);

        if ($position === false) {
            return null;
        }

        $role = trim(substr(string: $altText, offset: $position + strlen(self::ROLE_MARKER)));
        $role = mb_strtolower(string: $role);

        return $role === '' ? null : $role;
    }

    /**
     * Foto de portada: la primera marcada `is_primary`, o la primera foto.
     *
     * Se mira el REPRESENTANTE y no cualquier fila porque `is_primary` la
     * escribe el seeder sólo en la fila de mayor ancho: si una de las
     * resoluciones más chicas de la foto de portada tuviera la marca por un
     * descuido, la portada no cambiaría pero este cálculo sí.
     *
     * @param  list<array{variant_id: int|null, sort_order: int, rows: list<ProductImage>}>  $photos
     * @return array{variant_id: int|null, sort_order: int, rows: list<ProductImage>}|null
     */
    private function primaryPhoto(array $photos): ?array
    {
        foreach ($photos as $photo) {
            if ((bool) ($this->grouper->representative(rows: $photo['rows'])?->is_primary)) {
                return $photo;
            }
        }

        return $photos[0] ?? null;
    }

    /**
     * Ámbito de búsqueda de la cascada: las fotos de la variante de la
     * portada, o todas si esa variante no tiene ninguna.
     *
     * @param  list<array{variant_id: int|null, sort_order: int, rows: list<ProductImage>}>  $photos
     * @param  array{variant_id: int|null, sort_order: int, rows: list<ProductImage>}  $primaryPhoto
     * @return list<array{variant_id: int|null, sort_order: int, rows: list<ProductImage>}>
     */
    private function searchScope(array $photos, array $primaryPhoto): array
    {
        $sameVariant = array_values(array_filter(
            $photos,
            static fn (array $photo): bool => $photo['variant_id'] === $primaryPhoto['variant_id'],
        ));

        return $sameVariant !== [] ? $sameVariant : $photos;
    }

    /**
     * @param  list<array{variant_id: int|null, sort_order: int, rows: list<ProductImage>}>  $photos
     * @return array{variant_id: int|null, sort_order: int, rows: list<ProductImage>}|null
     */
    private function firstPhotoWithRole(array $photos, string $role): ?array
    {
        foreach ($photos as $photo) {
            if ($this->roleOf(rows: $photo['rows']) === $role) {
                return $photo;
            }
        }

        return null;
    }
}