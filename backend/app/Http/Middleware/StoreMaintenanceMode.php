<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Stores\Store;
use Closure;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que bloquea el acceso al storefront cuando la tienda activa
 * tiene `maintenance_mode = true` (RF-STOR-132).
 *
 * Comportamiento:
 *  - Si el tenant resuelto por `store.domain` está en mantenimiento,
 *    retorna 503 con view branded (`errors::503`).
 *  - Si NO hay tenant resuelto (middleware se aplicó fuera del grupo
 *    storefront), deja pasar — esto evita falsos positivos en rutas
 *    admin o auth.
 *  - Las tiendas con `is_active = false` ya son bloqueadas por
 *    `ResolveStoreFromDomain` con 404 antes de llegar acá. Este
 *    middleware NO duplica esa lógica.
 *
 * Notas:
 *  - Se aplica DESPUÉS de `ResolveStoreFromDomain` en el orden del grupo.
 *  - Las rutas admin NUNCA deben usar este middleware — el admin sigue
 *    operativo durante el mantenimiento.
 */
class StoreMaintenanceMode
{
    public function __construct(
        private readonly StoreContextService $storeContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $store = $this->storeContext->getStore();
        if (! $store instanceof Store) {
            // Sin tenant resuelto: no bloqueamos (probablemente es una
            // ruta no-storefront que no debería usar este middleware).
            return $next($request);
        }

        if ($store->maintenance_mode === true) {
            return response()->view(
                view: 'errors.503',
                data: [
                    'storeName' => $store->name,
                ],
                status: Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return $next($request);
    }
}
