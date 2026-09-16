<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Trait para paginación cursor-based escalable (RF-PERF-022).
 *
 * Por qué cursor vs offset:
 *  - `->paginate(N)` usa OFFSET. En MySQL/PostgreSQL, OFFSET K hace que
 *    el motor escanee + descarte K filas antes de retornar N. O(n+k).
 *    Con 1M de filas y página 1000, se escanean ~999k filas inútiles.
 *  - `->cursorPaginate(N)` usa un CURSOR (last_id + direction). El motor
 *    hace un rango scan. O(log n). Con 1M de filas y página 1000, sigue
 *    siendo O(1).
 *
 * Restricciones:
 *  - El query DEBE tener un `orderBy` por una columna ÚNICA e indexada
 *    (típicamente `id` o `created_at, id` para desempate).
 *  - El cursor NO soporta "saltar a página N" — sólo next/prev.
 *
 * Uso:
 * ```php
 * class OrderController extends Controller
 * {
 *     use CursorPaginates;
 *
 *     public function index(Request $request): JsonResponse
 *     {
 *         $query = Order::query()->orderByDesc('id');
 *         return $this->cursorPaginatedResponse($request, $query, OrderResource::class);
 *     }
 * }
 * ```
 */
trait CursorPaginates
{
    /**
     * Constante default para max per_page. Coincide con RNF-PERF-022
     * (cursor pagination generalizada — max 50 items por página según
     * el spec original del proyecto).
     */
    protected const DEFAULT_PER_PAGE = 20;

    protected const MAX_PER_PAGE = 100;

    /**
     * Devuelve el per_page del request validado (clamp a MAX_PER_PAGE).
     */
    protected function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->query(key: 'per_page', default: self::DEFAULT_PER_PAGE);

        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * Cursor de la request (opcional). Viene como string base64 en
     * `?cursor=...`.
     */
    protected function resolveCursor(Request $request): ?string
    {
        $cursor = $request->query(key: 'cursor');

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }

    /**
     * Aplica cursorPaginate y devuelve response JSON estandarizado.
     *
     * Shape del response (estable, escalable):
     * {
     *   "data": [...items...],
     *   "meta": {
     *     "next_cursor": "eyJpZCI6MTIzfQ==" | null,
     *     "prev_cursor": "eyJpZCI6NDZ9" | null,
     *     "has_more": true,
     *     "per_page": 20
     *   }
     * }
     *
     * @param  Builder  $query  Query builder con orderBy aplicado.
     * @param  class-string  $resourceClass  Resource class para los items.
     * @return \Illuminate\Http\JsonResponse
     */
    protected function cursorPaginatedResponse(
        Request $request,
        Builder $query,
        string $resourceClass,
    ): \Illuminate\Http\JsonResponse {
        $perPage = $this->resolvePerPage($request);
        $cursor = $this->resolveCursor($request);

        $paginator = $query->cursorPaginate(
            perPage: $perPage,
            cursor: $cursor,
        );

        return response()->json(data: [
            'data' => $resourceClass::collection(resource: $paginator->items())->resolve(),
            'meta' => [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more' => $paginator->hasMorePages(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }
}
