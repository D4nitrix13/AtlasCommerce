<?php

declare(strict_types=1);

namespace App\Providers;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;

/**
 * ScrambleConfigServiceProvider — Workaround para binding de GeneratorConfig.
 *
 * El default `GeneratorConfig::__construct($config = [])` no lee de Laravel
 * config repository. Sin este binding, `app(GeneratorConfig::class)` crea
 * una instancia con config vacio, ignorando `config/scramble.php`.
 *
 * Esto rompe especialmente:
 *   - `security_strategy` → siempre null → OpenAPI spec sin Bearer docs
 *   - Cualquier otro config custom del scramble
 *
 * Solucion: registrar un singleton binding que pasa `config('scramble')`
 * al constructor del GeneratorConfig.
 *
 * Registrado en `bootstrap/providers.php` (Laravel 11+ provider registration).
 *
 * Si Scramble arregla el upstream (lee config repository automaticamente),
 * este provider puede eliminarse.
 */
class ScrambleConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            GeneratorConfig::class,
            fn () => new GeneratorConfig(
                name: Scramble::DEFAULT_API,
                config: config(key: 'scramble', default: []),
            ),
        );
    }
}
