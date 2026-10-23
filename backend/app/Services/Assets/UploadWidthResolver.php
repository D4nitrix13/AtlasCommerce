<?php

declare(strict_types=1);

namespace App\Services\Assets;

use InvalidArgumentException;

/**
 * Resuelve la lista de anchos que {@see ProductImageUploader} genera por
 * cada imagen subida, leyendo `assets.upload.widths` de `config/assets.php`.
 *
 * ── Fuente de verdad ──────────────────────────────────────────────────
 *
 * La lista por defecto vive en {@see self::DEFAULT_WIDTHS} y el archivo de
 * configuración la referencia para armar el default de
 * `ASSETS_UPLOAD_WIDTHS`, así que el valor está escrito una sola vez en el
 * repo. `ProductImageUploader` ya NO expone `WIDTHS`: aquella constante
 * había quedado como una segunda fuente de verdad que el config podía
 * contradecir sin que nada lo dijera — el código de la galería y el del
 * operador declaraban anchos distintos y el síntoma ("no sale el 1600")
 * no señalaba a ninguna de las dos.
 *
 * ── Por qué una lista inválida se RECHAZA en vez de corregirse ────────
 *
 * Una lista inválida es un error del operador, no del admin que sube la
 * foto. Sanearla (quitar el 0, ordenar, deduplicar) devolvería un 201 con
 * una galería cuyos anchos NO son los que nadie configuró, y el síntoma
 * —"la miniatura mide 780 en vez de 800", "no aparece la de 1600"— no
 * apunta nunca a la causa. Detectarlo después cuesta más que rechazarlo.
 *
 * Por eso `resolve()` lanza `InvalidArgumentException` con un mensaje que
 * nombra la clave de configuración y el valor culpable. El endpoint de
 * upload queda en 500 con ese error en el log: es una egregious
 * misrepresentación de "se subió bien" y un 422 haría creer al admin que
 * su archivo está mal.
 *
 * ── Por qué no en el boot ─────────────────────────────────────────────
 *
 * Validar al arrancar convertiría un error del bloque `upload` —que sólo
 * afecta a una operación de administración— en un 500 de todas las rutas,
 * storefront incluido. Y el valor puede cambiar por request (lo hacen los
 * tests con `config()->set`), así que el momento honesto de detectarlo es
 * el momento en que se usa.
 *
 * ── Lista vacía ───────────────────────────────────────────────────────
 *
 * Es el peor caso si se acepta en silencio: el upload responde 201, el
 * admin cree que subió la foto, y no se crea ni una fila ni un archivo.
 * Por eso una lista vacía se trata igual que cualquier otra lista
 * inválida: error explícito, no no-op silencioso.
 */
final class UploadWidthResolver
{
    /**
     * Clave de configuración de la que se lee la lista de anchos.
     */
    public const CONFIG_KEY = 'assets.upload.widths';

    /**
     * Anchos por defecto, en px.
     *
     * Los mismos cuatro que produce `scripts/migrate-gibson-images.sh`
     * para el catálogo sembrado, y los mismos que la PDP espera: 1600 es
     * la full y 400 la miniatura de tarjeta. Cambiarlos cambia el peso de
     * la galería de TODO el catálogo, por eso son config y no un literal
     * repartido por el código.
     *
     * @var array<int, int>
     */
    public const DEFAULT_WIDTHS = [400, 800, 1200, 1600];

    /**
     * Divide la lista CSV de `ASSETS_UPLOAD_WIDTHS` SIN validarla.
     *
     * Existe para que `config/assets.php` pueda leer la variable de
     * entorno sin duplicar el parseo. Deliberadamente no castea: un token
     * que no es un entero literal se conserva tal cual para que
     * `resolve()` pueda nombrarlo en el mensaje de error.
     *
     * @param  string  $list  CSV tipo `400,800,1200,1600`.
     * @return array<int, int|string> Tokens ya recortados; los vacíos se descartan.
     */
    public static function fromEnvList(string $list): array
    {
        $widths = [];

        foreach (explode(',', $list) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $widths[] = preg_match('/^[+-]?\d+$/', $part) === 1 ? (int) $part : $part;
        }

        return $widths;
    }

    /**
     * Anchos a generar, ya validados y como enteros.
     *
     * @return array<int, int> Lista ascendente y sin repetidos.
     *
     * @throws InvalidArgumentException Si `assets.upload.widths` no es una
     *                                  lista de enteros positivos, únicos y
     *                                  ascendente. Ver el docblock de la clase.
     */
    public function resolve(): array
    {
        $configured = config(key: self::CONFIG_KEY);

        // Clave ausente: comportamiento idéntico al de antes de que los
        // anchos fueran configurables.
        if ($configured === null) {
            return self::DEFAULT_WIDTHS;
        }

        // Se acepta el CSV crudo además del array para que una override
        // puntual por request no dependa del tipo con el que se escribió.
        $tokens = is_string($configured) ? self::fromEnvList(list: $configured) : $configured;

        if (! is_array($tokens)) {
            throw self::misconfigured(detail: sprintf('el valor %s no es una lista.', self::describe($tokens)));
        }

        if ($tokens === []) {
            throw self::misconfigured(
                detail: 'la lista está vacía. Sin anchos no se genera ningún archivo, así que el upload no crearía ninguna fila.',
            );
        }

        $widths = [];

        foreach ($tokens as $token) {
            if (! self::isIntegerLike($token)) {
                throw self::misconfigured(detail: sprintf('el ancho %s no es un entero.', self::describe($token)));
            }

            $widths[] = (int) $token;
        }

        foreach ($widths as $width) {
            if ($width <= 0) {
                throw self::misconfigured(detail: sprintf('el ancho %d no es positivo.', $width));
            }
        }

        // Duplicados antes que ascendente: en `[400, 400]` el orden es
        // correcto y el diagnóstico útil es "repetido", no "no ascendente".
        if (count(array_unique($widths)) !== count($widths)) {
            throw self::misconfigured(detail: 'la lista tiene anchos repetidos.');
        }

        $sorted = $widths;
        sort($sorted);

        if ($sorted !== $widths) {
            throw self::misconfigured(detail: sprintf(
                'la lista no está en orden ascendente (llega %s).',
                implode(separator: ', ', array: $widths),
            ));
        }

        return $widths;
    }

    /**
     * Un token de la config es entero si ya es `int` o si es un string
     * que representa un entero literal.
     *
     * `is_numeric()` NO alcanza: acepta `3.5` y `1e3`, y castearlos
     * truncaría en silencio el ancho que el operador quiso escribir.
     */
    private static function isIntegerLike(mixed $token): bool
    {
        if (is_int($token)) {
            return true;
        }

        return is_string($token) && preg_match('/^[+-]?\d+$/', trim($token)) === 1;
    }

    /**
     * Renderiza un token para el mensaje de error sin perder el valor
     * original (que es justo el dato accionable para el operador).
     */
    private static function describe(mixed $token): string
    {
        return is_scalar($token) ? var_export($token, true) : gettype($token);
    }

    private static function misconfigured(string $detail): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf(
            '%s está mal configurada: %s Se esperan enteros positivos, sin repetidos y en orden ascendente.',
            self::CONFIG_KEY,
            $detail,
        ));
    }
}
