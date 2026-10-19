<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Services\Catalog\ProductSearchService;
use App\Services\Catalog\WholesalePricingService;
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
     */
    public function index(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $products = Product::query()
            ->whereExists(callback: function ($query) use ($store): void {
                $query->select(DB::raw(value: 1))
                    ->from(table: 'product_stores')
                    ->whereColumn(
                        first: 'product_stores.product_id',
                        operator: '=',
                        second: 'products.id',
                    )
                    ->where(column: 'store_id', operator: '=', value: $store->id)
                    ->where(column: 'is_visible', operator: '=', value: true);
            })
            ->with(relations: [
                'highlights',
                'groupedSpecs.values',
                'faqs',
                'extendedDescriptions',
            ])
            ->get();

        return response()->json(data: $products->map(
            fn (Product $product): array => $this->serializeProductWithExtended($product),
        )->all());
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
     * Eager-loads el contenido extendido (Fase 8) para que el response
     * incluya highlights, groupedSpecs, faqs y extendedDescriptions en
     * una sola query batched por tabla.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $store = $request->attributes->get('store');

        $product = Product::where('slug', $slug)
            ->whereExists(callback: function ($query) use ($store): void {
                $query->select(DB::raw(value: 1))
                    ->from(table: 'product_stores')
                    ->whereColumn(
                        first: 'product_stores.product_id',
                        operator: '=',
                        second: 'products.id',
                    )
                    ->where(column: 'store_id', operator: '=', value: $store->id)
                    ->where(column: 'is_visible', operator: '=', value: true);
            })
            ->with(relations: [
                'highlights',
                'groupedSpecs.values',
                'faqs',
                'extendedDescriptions',
            ])
            ->firstOrFail();

        return response()->json(data: $this->serializeProductWithExtended($product));
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
            ->whereExists(callback: function ($query) use ($store): void {
                $query->select(DB::raw(value: 1))
                    ->from(table: 'product_stores')
                    ->whereColumn(
                        first: 'product_stores.product_id',
                        operator: '=',
                        second: 'products.id',
                    )
                    ->where(column: 'store_id', operator: '=', value: $store->id)
                    ->where(column: 'is_visible', operator: '=', value: true);
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

    /**
     * Serializa un Product mergeando el contenido extendido (Fase 8)
     * al payload crudo del modelo.
     *
     * Mantiene la firma histórica del endpoint (raw model JSON) y
     * sólo agrega los 4 arrays anidados cuando existen en la eager
     * load. Las relaciones llegan ya ordenadas por `position` vía el
     * `orderBy` declarado en `Product::highlights()` etc.
     *
     * @return array<string, mixed>
     */
    private function serializeProductWithExtended(Product $product): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $product->toArray();

        $payload['highlights'] = $product->highlights
            ->map(fn ($highlight): array => [
                'icon' => $highlight->icon,
                'label' => $highlight->label,
            ])
            ->all();

        $payload['groupedSpecs'] = $product->groupedSpecs
            ->map(fn ($group): array => [
                'category' => $group->category,
                'items' => $group->values
                    ->map(fn ($value): array => [
                        'label' => $value->label,
                        'value' => $value->value,
                    ])
                    ->all(),
            ])
            ->all();

        $payload['faqs'] = $product->faqs
            ->map(fn ($faq): array => [
                'question' => $faq->question,
                'answer' => $faq->answer,
            ])
            ->all();

        $payload['extendedDescriptions'] = $product->extendedDescriptions
            ->map(fn ($entry): array => [
                'title' => $entry->title,
                'body' => $entry->body,
            ])
            ->all();

        return $payload;
    }
}
