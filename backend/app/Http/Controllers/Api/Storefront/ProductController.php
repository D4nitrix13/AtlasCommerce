<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
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

        $products = Product::whereHas('stores', function ($query) use ($store) {
            $query->where('store_id', $store->id)
                ->where('is_visible', true);
        })->get();

        return response()->json($products);
    }

    /**
     * Devuelve un producto por slug dentro de la tienda actual.
     *
     * Lanza 404 si el producto no existe o no está publicado en la tienda.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $store = $request->attributes->get('store');

        $product = Product::where('slug', $slug)
            ->whereHas('stores', function ($query) use ($store) {
                $query->where('store_id', $store->id)
                    ->where('is_visible', true);
            })->firstOrFail();

        return response()->json($product);
    }
}
