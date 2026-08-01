<?php

namespace App\Http\Middleware;

use App\Models\Stores\StoreDomain;
use App\Services\Stores\StoreContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que resuelve la tienda activa desde el dominio de la request.
 *
 * Busca en la tabla `store_domains` un registro que coincida con el host
 * de la request y que esté activo. Si lo encuentra, carga la tienda
 * asociada y la almacena en el `StoreContextService` y en los atributos
 * de la request para que esté disponible en controladores y otros middlewares.
 *
 * Si no se encuentra un dominio activo o la tienda está inactiva,
 * retorna un error 404 JSON.
 *
 * Uso en rutas:
 * ```php
 * Route::middleware(['store.domain'])->group(function () { ... });
 * ```
 */
class ResolveStoreFromDomain
{
    public function __construct(
        private readonly StoreContextService $storeContext,
    ) {}

    /**
     * Resuelve la tienda desde el dominio de la request.
     *
     * @param  Request  $request  Request HTTP entrante.
     * @param  Closure  $next  Siguiente middleware en la cadena.
     * @return Response Respuesta HTTP con la tienda resuelta en el contexto.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        $domain = StoreDomain::query()
            ->where('domain', $host)
            ->where('is_active', true)
            ->with('store')
            ->first();

        if (! $domain?->store?->is_active) {
            return response()->json([
                'message' => 'Store not found or inactive.',
            ], status: 404);
        }

        $this->storeContext->setStore($domain->store);

        $request->attributes->set('store', $domain->store);

        return $next($request);
    }
}
