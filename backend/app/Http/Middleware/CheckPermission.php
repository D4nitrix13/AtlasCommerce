<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica que el usuario autenticado tenga el permiso requerido (por slug).
 *
 * Uso en rutas:
 *   Route::post('shipments/{id}/ship')->middleware('permission:shipments.ship');
 *
 * Reglas:
 *  - El usuario DEBE estar autenticado (aplicar después de `auth:sanctum`).
 *  - El usuario DEBE tener un rol activo con el permiso solicitado.
 *  - Los super-admins (rol `slug = 'admin'`) bypasean el chequeo.
 *  - Usuarios inactivos (`is_active = false`) son rechazados aunque tengan rol.
 *
 * Parámetros:
 *  - `$permission` (string, requerido): slug del permiso, ej. `shipments.ship`.
 */
final class CheckPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(
                data: ['message' => 'Autenticación requerida.'],
                status: 401,
            );
        }

        if (! $user->is_active) {
            return response()->json(
                data: ['message' => 'Cuenta desactivada.'],
                status: 403,
            );
        }

        if ($user->hasPermission(slug: $permission)) {
            return $next($request);
        }

        return response()->json(
            data: ['message' => "No tiene el permiso requerido: {$permission}."],
            status: 403,
        );
    }
}
