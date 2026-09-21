<?php

declare(strict_types=1);

namespace App\Providers;

use App\Logging\LogContext;
use Core\Shared\Events\EventBus;
use Core\Shared\Events\InProcessEventBus;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El contexto de tienda debe ser único por request para que el
        // global scope `BelongsToStore` y el middleware vean la misma instancia.
        $this->app->singleton(StoreContextService::class);
        $this->app->singleton(EventBus::class, InProcessEventBus::class);

        // LogContext: singleton por request — middleware, processors y
        // servicios comparten la misma instancia de correlación
        // (RF-OBS-130 — Fase 13).
        $this->app->singleton(LogContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Laravel carga automáticamente los archivos ubicados directamente en:
         *
         * database/migrations/*.php
         *
         * Este bloque solo registra las subcarpetas modulares, por ejemplo:
         *
         * database/migrations/auth
         * database/migrations/catalog
         * database/migrations/customization
         *
         * Así mantenemos las migraciones organizadas por dominio sin perder
         * el comportamiento nativo de Laravel para cargar las migraciones raíz.
         */
        foreach (
            glob(
                pattern: database_path(path: 'migrations/*'),
                flags: GLOB_ONLYDIR,
            ) ?: [] as $directory
        ) {
            // Permite que Laravel cargue migraciones desde esa subcarpeta
            $this->loadMigrationsFrom(paths: $directory);
        }

        $this->configureRateLimiting();
    }

    /**
     * Define los named rate limiters usados por los endpoints de auth.
     *
     * Aplicación inline (no `throttle:` middleware) para mantener los
     * counters atómicos con la lógica del controller (éxito limpia,
     * fallo acumula) y mensajes de error en español consistentes con
     * PasswordResetTokenController.
     */
    protected function configureRateLimiting(): void
    {
        // Login: 5 intentos cada 5 min por (email + IP).
        RateLimiter::for(name: 'login', callback: function (Request $request): Limit {
            return Limit::perMinutes(decayMinutes: 5, maxAttempts: 5)
                ->by(key: $request->input(key: 'email').'|'.$request->ip());
        });

        // Regeneración de recovery codes: 5 intentos cada hora por (user_id + IP).
        RateLimiter::for(name: 'recovery-codes', callback: function (Request $request): Limit {
            return Limit::perHour(maxAttempts: 5)
                ->by(key: (string) $request->user()?->id.'|'.$request->ip());
        });

        // Verificación TOTP (activación 2FA): 10 intentos cada 5 min por IP.
        RateLimiter::for(name: 'totp-verify', callback: function (Request $request): Limit {
            return Limit::perMinutes(decayMinutes: 5, maxAttempts: 10)
                ->by(key: $request->ip());
        });

        // Verificación TOTP durante login (verify-totp-login): 10/5min/IP.
        // Defense-in-depth contra brute force de los ~30 codes válidos
        // por ventana de challenge (3 codes × ±1 step × 5 min).
        RateLimiter::for(name: 'verify-totp-login', callback: function (Request $request): Limit {
            return Limit::perMinutes(decayMinutes: 5, maxAttempts: 10)
                ->by(key: $request->ip());
        });
    }
}
