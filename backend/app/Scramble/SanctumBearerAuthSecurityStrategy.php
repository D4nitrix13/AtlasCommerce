<?php

declare(strict_types=1);

namespace App\Scramble;

use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Configuration\SecurityDocumentationContext;
use Dedoc\Scramble\Contracts\SecurityDocumentationStrategy;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;

/**
 * SanctumBearerAuthSecurityStrategy — Workaround para OpenAPI security.
 *
 * El MiddlewareAuthSecurityStrategy default de Scramble NO detecta
 * correctamente las rutas con middleware `auth:sanctum` (devuelve
 * securitySchemes count=0 incluso cuando debería detectarlo).
 *
 * Esta strategy reemplaza al default. Misma logica pero verifica
 * `auth:sanctum` y `auth:*` correctamente via prefix matching.
 *
 * Adicionalmente usa OperationTransformers para limpiar security
 * en operaciones publicas (sin middleware auth).
 *
 * Se registra en config/scramble.php via:
 *   'security_strategy' => SanctumBearerAuthSecurityStrategy::class
 *
 * Si Scramble arregla el upstream, esta clase puede eliminarse y
 * restaurar el default.
 */
class SanctumBearerAuthSecurityStrategy implements SecurityDocumentationStrategy
{
    private SecurityScheme $scheme;

    /**
     * @param  list<string>  $middleware  patterns que indican ruta autenticada
     */
    public function __construct(
        private array $middleware = ['auth', 'auth:sanctum', 'auth:*'],
        ?SecurityScheme $scheme = null,
    ) {
        $this->scheme = $scheme ?? SecurityScheme::http('bearer', 'sanctum');
    }

    public function configure(SecurityDocumentationContext $context): GeneratorConfig
    {
        $authenticatedCount = 0;
        foreach ($context->routes as $route) {
            if ($this->routeHasMiddleware($route)) {
                $authenticatedCount++;
            }
        }

        if ($authenticatedCount === 0) {
            return $context->config;
        }

        return $context->config
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->secure($this->scheme);
            })
            ->withOperationTransformers(function (OperationTransformers $transformers): void {
                $transformers->prepend(function (Operation $operation, RouteInfo $routeInfo): void {
                    if ($this->routeHasMiddleware($routeInfo->route)) {
                        // Mantiene el global security (Bearer) que ya aplicamos
                        return;
                    }

                    // Las rutas publicas NO requieren Bearer
                    $operation->security = [];
                });
            });
    }

    private function routeHasMiddleware(Route $route): bool
    {
        $routeMiddleware = $route->gatherMiddleware();

        foreach ($routeMiddleware as $middleware) {
            // Normalizar a string (puede ser object Middleware)
            $name = is_string($middleware) ? $middleware : ($middleware->name ?? '');

            foreach ($this->middleware as $pattern) {
                if (str_starts_with($name, $pattern) || $name === $pattern) {
                    return true;
                }
            }
        }

        return false;
    }
}
