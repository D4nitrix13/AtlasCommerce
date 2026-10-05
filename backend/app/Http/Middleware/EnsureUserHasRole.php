<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica que el usuario autenticado tenga un rol cuyo slug esté en la
 * lista permitida.
 *
 * Uso en rutas:
 *   Route::prefix('admin')->middleware('role:admin')->group(...);
 *   Route::prefix('staff')->middleware('role:admin,staff')->group(...);
 *
 * Reglas:
 *  - El usuario DEBE estar autenticado (aplicar después de `auth:sanctum`).
 *  - El usuario DEBE tener un rol activo con slug en la lista solicitada.
 *  - Usuarios inactivos (`is_active = false`) son rechazados aunque tengan
 *    un rol permitido.
 *  - Los slugs se comparan case-sensitive — los seeders usan lowercase.
 *
 * Diferencia con `CheckPermission`:
 *  - `permission:foo` consulta la tabla `permissions` vía rol.permissions.
 *  - `role:admin` compara el slug del rol directamente. Más rápido y
 *    explícito para gating por nivel (admin / staff / customer).
 *
 * Parámetros:
 *  - `...$slugs` (string variadic, comma-separated en declaración de ruta):
 *    uno o más slugs aceptados. Ej: `role:admin` o `role:admin,super-admin`.
 */
final class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$slugs  slugs aceptados (Laravel pasa "a,b,c" como 1 string)
     */
    public function handle(Request $request, Closure $next, string ...$slugs): Response
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

        // Laravel pasa los slugs como un único string "admin,super-admin".
        // Split por coma + trim para soportar ambas firmas:
        //   role:admin,super-admin   (un solo arg, lo más común)
        //   role:admin|role:super    (variadic — defensivo)
        $allowed = collect($slugs)
            ->flatMap(fn (string $s): array => explode(',', $s))
            ->map(fn (string $s): string => trim($s))
            ->filter()
            ->values()
            ->all();

        $role = $user->role;

        if ($role !== null && ! $role->is_active) {
            return response()->json(
                data: ['message' => 'Rol desactivado.'],
                status: 403,
            );
        }

        if ($role !== null && in_array($role->slug, $allowed, strict: true)) {
            return $next($request);
        }

        return response()->json(
            data: ['message' => 'No tiene un rol autorizado para acceder a este recurso.'],
            status: 403,
        );
    }
}
