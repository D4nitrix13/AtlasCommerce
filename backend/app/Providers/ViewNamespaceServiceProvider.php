<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registra namespaces de views que Laravel 13 no incluye por default.
 *
 * Laravel 13 renderiza errores HTTP (404, 403, 500) usando `errors::404`
 * etc., pero NO registra el namespace `errors` apuntando a
 * `resources/views/errors/`. El vendor solo tiene los errores 401/419/429/503
 * en `vendor/laravel/framework/.../Exceptions/views/`. Sin este provider,
 * `NotFoundHttpException` falla con `No hint path defined for [errors]`,
 * que se manifiesta como `FileNotFoundException` en storage/framework/views/.
 *
 * Este provider lo arregla agregando:
 *   - 'errors'            -> resources/views/errors/  (nuestro fallback)
 *   - 'laravel-exceptions' -> vendor/laravel/framework/.../Exceptions/views/
 *     (los 401/419/429/503 defaults de Laravel)
 */
final class ViewNamespaceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(resource_path(path: 'views/errors'), 'errors');
    }
}
