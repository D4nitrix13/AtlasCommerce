<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\StorefrontProductResource;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Services\Catalog\ProductSearchService;
use App\Services\Catalog\WholesalePricingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoints públicos del storefront.
 *
 * A diferencia de los controllers bajo `Api/`, este grupo NO requiere
 * autenticación Sanctum: el storefront es público. El tenant se resuelve
 * mediante `ResolveStoreFromDomain` middleware que expone la `Store`
 * activa en `$request->attributes->get('store')`.
 */
class ProductController extends Controller
{
    /**
     * Lista productos visibles en la tienda actual.
     *
     * Solo se devuelven productos publicados en `product_stores` con
     * `is_visible = true`. No expone stock, costo ni datos internos.
     *
     * Query params:
     *   - category: string opcional (slug). Si se pasa, filtra a productos
     *     cuya `category_id` apunte a una categoría con ese slug. Útil
     *     para derivar "productos relacionados por categoría" en la PDP
     *     sin necesitar un endpoint dedicado.
     *   - page:     int >= 1 (default 1).
     *   - per_page: int entre 1 y `ProductSearchService::MAX_PER_PAGE`
     *     (default `ProductSearchService::DEFAULT_PER_PAGE`).
     *
     * Response (misma envoltura que `search()`):
     *   {
     *     "data": [Product, ...],
     *     "meta": { "current_page", "per_page", "total", "last_page" }
     *   }
     */
    public function index(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $validated = $request->validate(rules: [
            'category' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ProductSearchService::MAX_PER_PAGE],
        ]);
        $categorySlugRaw = $validated['category'] ?? null;
        $categorySlug = $categorySlugRaw !== null ? trim($categorySlugRaw) : null;
        if ($categorySlug === '') {
            $categorySlug = null;
        }

        // Default de `per_page`: el MISMO que usa `search()`
        // (ProductSearchService::DEFAULT_PER_PAGE), no el cap. Motivo —
        // antes este endpoint devolvía el catálogo completo del tenant
        // (346 filas / ~2.3 MB para un grid de 24 cards). Cualquier
        // consumer que omita `per_page` debe recibir una página acotada
        // y no el catálogo entero; 20 items alcanzan para el caso que hoy
        // lo usa sin pedir tamaño (los relacionados de la PDP, que
        // descarta todo pasado `RELATED_PRODUCTS_LIMIT`), mientras que
        // el cap de 50 sólo multiplicaría el payload de ese mismo fetch.
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? ProductSearchService::DEFAULT_PER_PAGE);

        // Filtra primero los product_ids visibles del tenant (en la pivot
        // product_stores), después hace el whereIn. Equivalente a whereHas
        // 'stores' pero sin el JOIN doble del mismo pivot que causa
        // "Duplicate alias: product_stores" en PostgreSQL.
        $productIds = DB::table('product_stores')
            ->where(column: 'store_id', operator: '=', value: $store->id)
            ->where(column: 'is_visible', operator: '=', value: true)
            ->pluck('product_id');

        // Eager-load de las relaciones que el Resource necesita para
        // emitir su shape completa. Sin with(), category/brand/images
        // quedan ausentes y dispara N+1 si el Resource los tocara.
        $query = Product::query()
            ->whereIn('id', $productIds)
            ->with(['category', 'brand', 'images', 'variants'])
            // Orden total y determinista. Sin un ORDER BY explícito
            // PostgreSQL no garantiza un orden estable entre consultas,
            // así que con paginación offset una fila puede caer en dos
            // páginas distintas y otra no aparecer nunca. Inofensivo
            // con `get()` (una sola respuesta), obligatorio con
            // `paginate()`.
            ->orderBy(column: 'id');

        if ($categorySlug !== null) {
            // Lookup directo por slug; el modelo respeta soft-deletes.
            $category = Category::query()
                ->where(column: 'slug', operator: '=', value: $categorySlug)
                ->first();

            if ($category === null) {
                // Categoría inexistente → catálogo vacío, NO 404: la PDP
                // consume este endpoint para derivar relacionados y
                // prefiere empty sobre error.
                //
                // Se fuerza el set a cero con `1 = 0` en vez de hacer
                // early-return: así la respuesta conserva la envoltura
                // paginada (`meta.total = 0`, `meta.last_page = 1`) sin
                // duplicar el armado de la respuesta ni tener que
                // instanciar un paginator vacío a mano.
                $query->whereRaw(sql: '1 = 0');
            } else {
                $query->where(column: 'category_id', operator: '=', value: $category->id);
            }
        }

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        return response()->json(data: $this->paginationEnvelope(
            paginator: $paginator,
            items: StorefrontProductResource::collection($paginator->items())->resolve(),
        ));
    }

    /**
     * Búsqueda pública de productos con ranking por relevancia.
     *
     * RF-CAT-028 — Soporta tolerancia (ILIKE sobre múltiples campos)
     * y paginación. El tenant se resuelve por el middleware `store.domain`.
     *
     * Query params:
     *   - q:        string requerida (mín 1 char tras trim).
     *   - page:     int >= 1 (default 1).
     *   - per_page: int entre 1 y 50 (default 20).
     *
     * Response:
     *   {
     *     "data": [Product, ...],
     *     "meta": { "current_page", "per_page", "total", "last_page", "query" }
     *   }
     */
    public function search(
        Request $request,
        ProductSearchService $search,
    ): JsonResponse {
        $store = $request->attributes->get('store');

        $validated = $request->validate(rules: [
            'q' => ['required', 'string', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ProductSearchService::MAX_PER_PAGE],
        ]);

        $query = (string) $validated['q'];
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? ProductSearchService::DEFAULT_PER_PAGE);

        $paginator = $search->search(
            store: $store,
            query: $query,
            page: $page,
            perPage: $perPage,
        );

        return response()->json(data: $this->paginationEnvelope(
            paginator: $paginator,
            items: $paginator->items(),
            query: trim($query),
        ));
    }

    /**
     * Envoltura paginada compartida por `index()` y `search()`.
     *
     * Vive acá — y no duplicada en cada método — para que los dos
     * endpoints del controller hablen el mismo idioma y un cambio futuro
     * de la envoltura no los desincronice.
     *
     * `query` sólo lo emite `search()`: el listado no tiene texto de
     * búsqueda, y mandar `"query": null` al consumidor del catálogo sería
     * ruido en el contrato.
     *
     * `mixed` en `$items` es a propósito: cada endpoint serializa sus
     * filas con su propia representación (`index()` pasa el resultado de
     * `StorefrontProductResource::collection()->resolve()`, `search()`
     * pasa los modelos del paginator). La envoltura no debe imponer qué
     * hay dentro de `data`.
     *
     * @param  LengthAwarePaginator<int, Product>  $paginator  Fuente de `meta`.
     * @param  array<int, mixed>  $items  Filas de la página actual.
     * @return array{data: array<int, mixed>, meta: array<string, int|string>}
     */
    private function paginationEnvelope(
        LengthAwarePaginator $paginator,
        array $items,
        ?string $query = null,
    ): array {
        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];

        if ($query !== null) {
            $meta['query'] = $query;
        }

        return [
            'data' => $items,
            'meta' => $meta,
        ];
    }

    /**
     * Devuelve un producto por slug dentro de la tienda actual.
     *
     * Lanza 404 si el producto no existe o no está publicado en la tienda.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $store = $request->attributes->get('store');

        // Mismo criterio que index(): se resuelve el product_id contra el
        // pivot product_stores antes de tocar Product. whereHas() es API de
        // Eloquent Builder y no existe en Query Builder, por eso falla con
        // `column "has" does not exist`.
        $productId = DB::table('product_stores')
            ->where(column: 'store_id', operator: '=', value: $store->id)
            ->where(column: 'is_visible', operator: '=', value: true)
            ->whereIn('product_id', DB::table('products')
                ->select('id')
                ->where(column: 'slug', operator: '=', value: $slug))
            ->value(column: 'product_id');

        if ($productId === null) {
            abort(code: 404);
        }

        // Eager-load de las relaciones que el Resource necesita. Sin
        // with(), `category`/`brand`/`images` quedan null y los joins
        // que necesitan el Resource no emiten esa info. findOrFail
        // respeta el filtro `id` + soft-deletes del modelo.
        $product = Product::query()
            ->with(['category', 'brand', 'images', 'variants'])
            ->findOrFail($productId);

        // Emitimos la shape plana (sin `{data: ...}`) para alinearla
        // con el contrato del listado y con el consumer del PDP.
        return response()->json(
            data: (new StorefrontProductResource($product))->resolve(),
        );
    }

    /**
     * Devuelve el precio efectivo (en centavos) de un producto para
     * una cantidad dada, considerando la feature `wholesale_pricing`
     * del store actual.
     *
     * Query params:
     *   - quantity: int (default 1)
     *
     * Response:
     *   { "product_id": int, "quantity": int, "price_cents": int }
     */
    public function price(
        Request $request,
        string $slug,
        WholesalePricingService $pricing,
    ): JsonResponse {
        $store = $request->attributes->get('store');

        $product = Product::where('slug', $slug)
            ->whereHas('stores', function ($query) use ($store): void {
                $query->where('store_id', $store->id)
                    ->where('is_visible', true);
            })
            ->firstOrFail();

        $quantity = (int) $request->query(key: 'quantity', default: 1);

        if ($quantity < 1) {
            $quantity = 1;
        }

        $priceCents = $pricing->calculatePrice(
            product: $product,
            store: $store,
            quantity: $quantity,
        );

        return response()->json(data: [
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price_cents' => $priceCents,
        ]);
    }
}
