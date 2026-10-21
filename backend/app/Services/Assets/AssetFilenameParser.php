<?php

declare(strict_types=1);

namespace App\Services\Assets;

/**
 * Parsea el nombre legible de un webp del árbol de assets.
 *
 * Convención (`.ai/rules/assets-naming-policy.md` §1):
 *
 *     assets/products/{tenant}/{brand}/{product}/{variant}/{role}-{WxH}.webp
 *
 * El pipeline de assets acepta dos formas: `role-WxH` y el legacy
 * indexado `NN-role-WxH` (`01-front-1600x1600.webp`).
 *
 * Existe como servicio aparte — y no como método del seeder — porque DOS
 * consumidores necesitan exactamente el mismo parseo:
 *
 *   1. `CatalogAssetsSeeder`, que discovery el árbol y persiste
 *      `product_images.width`.
 *   2. `BackfillImageVariantCommand`, que re-walkea el mismo árbol para
 *      rellenar las columnas que el seeder histórico dejó vacías.
 *
 * Duplicar el regex entre los dos es exactamente la clase de bug que la
 * regla del repo ya suffered con las llamadas `where()` de 2 argumentos:
 * dos copias del mismo patrón se divergen en silencio y cada una miente
 * sobre la mitad de los archivos.
 *
 * Clase pura: sin I/O, sin globals, testeable en isolation.
 */
final class AssetFilenameParser
{
    /**
     * Parsea "NN-role-WxH" o "role-WxH" → ['role' => ..., 'size_w' => 'W'].
     *
     * Filenames esperados: `01-front-1600x1600.webp`, `body-400x400.webp`,
     * `06-back-headstock-1200x1200.webp`, `17-finish-top-800x800.webp`.
     *
     * Solo extrae el ANCHO (W) del WxH porque los dos consumidores
     * trabajan con el ancho: el seeder compara con '1600' para identificar
     * la imagen de mayor resolución, y el backfill lo persiste en
     * `product_images.width` para armar el `srcset`. La altura (H) es
     * irrelevante para esa lógica.
     *
     * @param  string  $base  Nombre del archivo SIN la extensión (`front-1600x1600`).
     * @return null|array{role: string, size_w: string} `null` cuando el nombre no matchea la convención.
     */
    public function parseRoleAndSize(string $base): ?array
    {
        // Match solo el ANCHO (no "WxH" completo). Regex con grupo 'size_w'
        // que captura solo dígitos antes de la 'x'.
        if (preg_match(pattern: '/^(?:[0-9]{2}-)?(?P<role>[a-z\-]+)-(?P<size_w>[0-9]+)x[0-9]+$/', subject: $base, matches: $matches) !== 1) {
            return null;
        }

        return [
            'role' => $matches['role'],
            'size_w' => $matches['size_w'],
        ];
    }
}
