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
 * Existe como servicio aparte — y no como método del seeder — porque TRES
 * consumidores necesitan exactamente el mismo parseo:
 *
 *   1. `CatalogAssetsSeeder`, que descubre el árbol y persiste
 *      `product_images.width` y `sort_order`.
 *   2. `BackfillImageVariantCommand`, que re-walkea el mismo árbol para
 *      rellenar las columnas que el seeder histórico dejó vacías.
 *   3. `GalleryRoleAuditor`, que compara el árbol contra `product_images`.
 *
 * Duplicar el parseo entre ellos es exactamente la clase de bug que la
 * regla del repo ya sufrió con las llamadas `where()` de 2 argumentos:
 * dos copias del mismo patrón se divergen en silencio y cada una miente
 * sobre la mitad de los archivos.
 *
 * Clase pura: sin I/O, sin globals, testeable en isolation.
 */
final class AssetFilenameParser
{
    /**
     * Parsea "{...}-{role}-WxH" → ['role' => ..., 'size_w' => 'W'].
     *
     * ── Por qué se lee desde la DERECHA ───────────────────────────────
     *
     * El nombre del archivo no es sólo el rol: en los productos con slug
     * largo el pipeline deja el slug pegado al rol. La versión anterior
     * usaba `(?P<role>[a-z\-]+)-(?P<size_w>[0-9]+)x[0-9]+`, que sólo
     * admitía letras y guiones en el rol, así que:
     *
     *   - `gibson-mod-collection-es-335-...-front-1600x1600` no matcheaba
     *     (el slug tiene dígitos) → `null`, y el seeder descartaba el
     *     archivo en silencio: 11 de los 356 productos del árbol
     *     quedaban sin ninguna imagen.
     *   - `gibson-mod-collection-les-paul-junior-...-back-1600x1600`
     *     matcheaba con el slug entero como "rol": 24 archivos de basura
     *     que el seeder persistía con `sort_order = 99` y que terminarían
     *     sirviendo de portada.
     *
     * El sufijo `-WxH` es el único ancla inequívoca (lo agrega el
     * pipeline y nunca es parte de un slug), así que se parte por ahí y el
     * rol se busca como el sufijo MÁS LARGO que exista en el vocabulario.
     *
     * ── Por qué se valida contra un vocabulario ──────────────────────
     *
     * Aceptar cualquier último segmento convertiría en "rol" cualquier
     * palabra del slug (`les-paul-junior` → rol `junior`) y el bug
     * reaparecería con otro nombre. La política del repo es explícita:
     * un `null` es un dato pendiente, una basura es un bug. Ante un
     * nombre que no respeta la convención se devuelve `null` y el
     * archivo se reporta, no se adivina.
     *
     * El vocabulario es el MISMO que ordena la galería:
     * `CatalogAssetsSeeder::ROLE_ORDER`. `GalleryRoleAuditor` ya lo
     * consulta para el `sort_order`, así que no existe una segunda lista
     * que pueda divergir de la que el seeder persiste.
     *
     * Se busca el sufijo MÁS LARGO a propósito: los roles compuestos
     * (`back-headstock`, `finish-peghead`, `sn-label`) son prefijos de
     * otros válidos (`headstock`), y el más largo es el que expressa el
     * encuadre. Es además el comportamiento del regex anterior (greedy),
     * así que ningún archivo válido cambia de rol.
     *
     * El prefijo indexado legacy `NN-` no necesita tratamiento
     * explícito: al buscar el sufijo más largo contra el vocabulario,
     * `01-front` resuelve en `front` igual que antes.
     *
     * Del `WxH` sólo se devuelve el ANCHO (W) porque es lo que usan los
     * consumidores: el seeder compara con '1600' para marcar la primary y
     * el backfill persiste `product_images.width` para armar el `srcset`.
     * La altura es irrelevante para esa lógica.
     *
     * @param  string  $base  Nombre del archivo SIN la extensión (`front-1600x1600`).
     * @return null|array{role: string, size_w: string} `null` cuando el nombre no matchea la convención o el sufijo no es un rol conocido.
     */
    public function parseRoleAndSize(string $base): ?array
    {
        // Match solo el ANCHO (no "WxH" completo). El grupo `size_w`
        // captura los dígitos que preceden a la 'x'.
        //
        // `.*` es greedy a propósito: con el `$` anclado toma el ÚLTIMO
        // `-WxH` del nombre, que es donde el pipeline pone el tamaño.
        if (preg_match(pattern: '/^(?P<stem>.*)-(?P<size_w>[0-9]+)x[0-9]+$/', subject: $base, matches: $matches) !== 1) {
            return null;
        }

        $role = $this->roleFromStem(stem: $matches['stem']);

        if ($role === null) {
            return null;
        }

        return [
            'role' => $role,
            'size_w' => $matches['size_w'],
        ];
    }

    /**
     * Sufijo más largo del stem que sea un rol conocido, o `null`.
     *
     * @param  string  $stem  Nombre sin el sufijo `-WxH` ni la extensión.
     */
    private function roleFromStem(string $stem): ?string
    {
        $segments = explode(separator: '-', string: $stem);

        for ($length = count($segments); $length >= 1; $length--) {
            $candidate = implode(separator: '-', array: array_slice(array: $segments, offset: -$length));

            if (isset(CatalogAssetsSeeder::ROLE_ORDER[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }
}
