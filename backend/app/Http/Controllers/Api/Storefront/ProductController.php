<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $products = Product::whereHas('stores', function ($query) use ($store) {
            $query->where('store_id', $store->id)
                ->where('is_visible', true);
        })->get();

        return response()->json($products);
    }

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
