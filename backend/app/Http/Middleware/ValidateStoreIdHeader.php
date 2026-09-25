<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ValidateStoreIdHeader — Valida que el header `X-Store-Id` (si presente)
 * coincida con el store activo resuelto por `ResolveStoreFromDomain` middleware.
 *
 * Caso de uso:
 *   - Cliente SPA manda `X-Store-Id: 5` para indicar que quiere
 *     data del store #5 (multi-tenant context switch via cookie).
 *   - Backend chequea que coincide con el store resuelto por Host
 *     (vía ResolveStoreFromDomain middleware).
 *   - Si no matchea → 400 con mensaje.
 *   - Si header NO esta presente → allow (el host es la fuente de verdad).
 *
 * Por que es importante:
 *   - Multi-tenant API requiere consistencia entre el dominio de
 *     la request (Host) y el contexto que el cliente cree tener.
 *   - Si no matchean, ataque de IDOR potential (acceder datos de
 *     otro tenant).
 *
 * **No confundir con**:
 *   - `ResolveStoreFromDomain`: resuelve tenant del Host (pre-request).
 *   - `SetSessionDomainFromHost`: cookie de session scoped al tenant.
 *   - Este middleware: valida que el header `X-Store-Id` enviado
 *     por el cliente matchee con la resolucion anterior.
 *
 * Uso:
 * ```php
 * Route::middleware(['store.domain', 'validate.store_id'])->group(function () {
 *     // admin/* routes
 * });
 * ```
 */
class ValidateStoreIdHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $headerStoreId = $request->header(key: 'X-Store-Id');
        if ($headerStoreId === null || $headerStoreId === '') {
            // Header opcional — host es la fuente de verdad.
            return $next($request);
        }

        $resolvedStore = $request->attributes->get(key: 'store');
        if ($resolvedStore === null) {
            // ResolveStoreFromDomain no seteo store (raro). Allow pero log.
            return $next($request);
        }

        // Coercion tolerante: el cliente puede mandar "5" o "5" como string.
        $headerStoreIdInt = (int) $headerStoreId;
        if ($headerStoreIdInt !== (int) $resolvedStore->id) {
            return response()->json(data: [
                'message' => 'X-Store-Id header does not match the resolved store for this host.',
                'header_store_id' => $headerStoreIdInt,
                'resolved_store_id' => (int) $resolvedStore->id,
            ], status: 400);
        }

        return $next($request);
    }
}
