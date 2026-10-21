<?php

declare(strict_types=1);

/**
 * Guard de base de datos para la suite de tests.
 *
 * Razón de existir: `RefreshDatabase` hace TRUNCATE de todas las tablas
 * de la base a la que apunte la conexión. Una base de desarrollo no
 * tiene protección ninguna contra eso, y este proyecto tiene cuatro
 * instancias con datos reales.
 *
 * El nombre de la base configurado en `phpunit.xml` no alcanza como
 * protección: es un valor hardcodeado que alguien puede cambiar, y
 * cuando se corre `php artisan test` desde el container de una
 * instancia concreta la configuración de esa instancia gana.
 *
 * Por eso la validación vive acá, en el bootstrap, y aborta el proceso
 * antes de que PHPUnit cree una sola conexión.
 *
 * Override: `DB_DATABASE=<instancia>_test php artisan test`.
 */

$resolveEnv = static function (string $key): string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    return is_string($value) ? trim($value) : '';
};

// PHPUnit inyecta los <env> de phpunit.xml recién después del bootstrap,
// así que se miran las tres fuentes posibles.
$database = $resolveEnv('DB_DATABASE');

if ($database !== '' && ! str_ends_with($database, '_test')) {
    fwrite(
        STDERR,
        PHP_EOL
        . '  ── Suite de tests abortada ─────────────────────────────────────' . PHP_EOL
        . '  DB_DATABASE="' . $database . '" no termina en "_test".' . PHP_EOL
        . PHP_EOL
        . '  RefreshDatabase vaciaría una base de datos real de una de las' . PHP_EOL
        . '  instancias. Para correr los tests pointed a una base de pruebas:' . PHP_EOL
        . PHP_EOL
        . '      DB_DATABASE=<instancia>_test php artisan test' . PHP_EOL
        . PHP_EOL
        . '  ──────────────────────────────────────────────────────────────'
        . PHP_EOL,
    );

    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';