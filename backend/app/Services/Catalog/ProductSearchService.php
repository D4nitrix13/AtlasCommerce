<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Catalog\Product;
use App\Models\Stores\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Búsqueda pública de productos en el storefront.
 *
 * RF-CAT-028 — Búsqueda pública de productos con tolerancia a typos
 * y ranking por relevancia.
 *
 * Estrategia MVP (esta implementación):
 *  - Coincidencia ILIKE case-insensitive sobre:
 *      - products.name
 *      - products.description
 *      - products.sku
 *      - product_stores.public_title (override de la tienda)
 *      - product_stores.public_description (override de la tienda)
 *  - Ranking con score numérico (suma de CASE WHEN), de mayor a menor:
 *      SKU exacto           100
 *      Name starts-with     80
 *      Public title starts-with  70
 *      Name contains        50
 *      Public title contains    40
 *      Description contains    20
 *      Public desc contains    10
 *  - Tie-breaker: published_at DESC.
 *  - Solo productos activos + publicados + visibles en la tienda actual.
 *  - Aislamiento por tenant: filtrado por `product_stores.store_id`.
 *  - Paginación: `LengthAwarePaginator` (offset-based) con cap de 50 items/página.
 *
 * Mejoras futuras (backlog):
 *  - FULL TEXT SEARCH nativo PostgreSQL con columna tsvector + índice GIN.
 *  - Fuzzy match con pg_trgm (`%` operator) cuando el set de productos crezca.
 *  - Sinónimos y stop-words por locale.
 *  - Highlight de coincidencias en el response.
 *  - Considerar Meilisearch/Typesense si el set supera ~50K productos.
 */
final class ProductSearchService
{
    /**
     * Tamaño de página por defecto y máximo permitido (alineado con
     * RNF-PERF-022 "paginación obligatoria, max 50 items/página").
     */
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 50;

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function search(
        Store $store,
        string $query,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
    ): LengthAwarePaginator {
        $trimmed = trim($query);
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        $page = max(1, $page);

        // Si el query quedó vacío tras trim, devolvemos paginator vacío
        // sin pegarle a la BD.
        if ($trimmed === '') {
            return Product::query()
                ->whereRaw(sql: '1 = 0')
                ->paginate(perPage: $perPage, page: $page);
        }

        $containsNeedle = '%'.$trimmed.'%';
        $startsWithNeedle = $trimmed.'%';

        // 8 bindings en orden: exactQuery, sku_contains, name_starts,
        // title_starts, name_contains, title_contains, desc_contains,
        // pubdesc_contains.
        $scoreBindings = [
            $trimmed,
            $containsNeedle,
            $startsWithNeedle,
            $startsWithNeedle,
            $containsNeedle,
            $containsNeedle,
            $containsNeedle,
            $containsNeedle,
        ];

        $scoreSql = $this->buildScoreSql();

        return Product::query()
            ->select('products.*')
            ->join('product_stores', 'product_stores.product_id', '=', 'products.id')
            ->where('product_stores.store_id', $store->id)
            ->where('product_stores.is_visible', true)
            ->where('products.is_active', true)
            ->whereNotNull('products.published_at')
            ->where('products.published_at', operator: '<=', value: now())
            ->where(function ($q) use ($containsNeedle): void {
                $q->where('products.name', 'ILIKE', $containsNeedle)
                    ->orWhere('products.description', 'ILIKE', $containsNeedle)
                    ->orWhere('products.sku', 'ILIKE', $containsNeedle)
                    ->orWhere('product_stores.public_title', 'ILIKE', $containsNeedle)
                    ->orWhere('product_stores.public_description', 'ILIKE', $containsNeedle);
            })
            ->orderByRaw(sql: $scoreSql, bindings: $scoreBindings)
            ->orderBy('products.published_at', 'desc')
            ->orderBy('products.id', 'desc')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * Construye el SQL del CASE que calcula el score de relevancia.
     *
     * Se usa un único CASE-WHEN (no aditivo) para que cada fila reciba
     * el score del match más fuerte, sin acumular pesos.
     *
     * Tiers (de mayor a menor):
     *   100  SKU exacto (case-insensitive)
     *    90  SKU contiene (ej. query "BUSCADO" matchea "BUSCADO-01")
     *    80  products.name empieza con query
     *    70  product_stores.public_title empieza con query
     *    50  products.name contiene
     *    40  product_stores.public_title contiene
     *    30  products.description contiene
     *    20  product_stores.public_description contiene
     *    10  cualquier otro (no debería llegar acá)
     *
     * Placeholders `?` se bindean en orden desde `search()`:
     *   [exactQuery, sku_contains, name_starts, title_starts,
     *    name_contains, title_contains, desc_contains, pubdesc_contains].
     */
    private function buildScoreSql(): string
    {
        return <<<'SQL'
            CASE
                WHEN LOWER(products.sku) = LOWER(?) THEN 100
                WHEN products.sku ILIKE ? THEN 90
                WHEN products.name ILIKE ? THEN 80
                WHEN product_stores.public_title ILIKE ? THEN 70
                WHEN products.name ILIKE ? THEN 50
                WHEN product_stores.public_title ILIKE ? THEN 40
                WHEN products.description ILIKE ? THEN 30
                WHEN product_stores.public_description ILIKE ? THEN 20
                ELSE 10
            END DESC
        SQL;
    }
}
