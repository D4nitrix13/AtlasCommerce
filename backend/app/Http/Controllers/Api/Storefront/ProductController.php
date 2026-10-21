<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Services\Catalog\ProductSearchService;
use Illuminate\Support\Facades\DB;
use App\Services\Catalog\WholesalePricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     */
    public function index(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        // Filtra primero los product_ids visibles del tenant (en la pivot
        // product_stores), después hace el whereIn. Equivalente a whereHas
        // 'stores' pero sin el JOIN doble del mismo pivot que causa
        // "Duplicate alias: product_stores" en PostgreSQL.
        $productIds = DB::table('product_stores')
            ->where('store_id', $store->id)
            ->where('is_visible', true)
            ->pluck('product_id');

        $products = Product::whereIn('id', $productIds)->get();

        return response()->json($products);
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

        return response()->json(data: [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'query' => trim($query),
            ],
        ]);
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

        $product = $productId === null
            ? abort(code: 404)
            : Product::findOrFail($productId);

        return response()->json($product);
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
