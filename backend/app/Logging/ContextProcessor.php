<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor que enriquece cada log record con el contexto de
 * correlación de la request actual (`App\Logging\LogContext`).
 *
 * Lo inyecta Laravel automáticamente al resolver los processors
 * declarados en `config/logging.php` (la factory de channels de Laravel
 * usa el container para instanciarlos), por lo que el constructor
 * funciona sin registro adicional.
 *
 * Por qué Monolog 3 `LogRecord` y no el array legacy: Laravel 13 usa
 * Monolog 3 que retorna `LogRecord` inmutable desde los handlers y
 * processors. Usar `with(extra:)` es la única forma soportada.
 */
class ContextProcessor implements ProcessorInterface
{
    public function __construct(private readonly LogContext $context) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;
        $correlation = $this->context->toArray();

        if ($correlation === []) {
            return $record;
        }

        return $record->with(extra: array_merge($extra, $correlation));
    }
}
