<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
// Override error_reporting ANTES del bootstrap de Laravel.
// HandleExceptions::bootstrap() hace error_reporting(-1) (todos los
// niveles), lo que captura warnings de PHP (incluido el de tempnam()
// que Laravel hace via Filesystem.php:222) y los convierte en 500
// Internal Server Error. Excluimos E_WARNING, E_DEPRECATED y
// E_USER_DEPRECATED para que esos warnings se registren pero no
// exploten como exceptions.
error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
