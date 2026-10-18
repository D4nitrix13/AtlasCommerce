<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\CursorPaginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductImageStoreRequest;
use App\Http\Requests\Admin\ProductImageUpdateRequest;
use App\Http\Resources\Catalog\AdminProductImageResource;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de imágenes de producto (RF-CAT-022).
 *
 * Las imágenes viven anidadas bajo un producto:
 *   /api/admin/products/{product}/images
 *
 * Aislamiento por tenant: si el producto no está publicado en
 * ninguna tienda del admin actual, devuelve 404.
 *
 * Lógica especial:
 *  - Al marcar una imagen como principal (is_primary=true), se
 *    desmarcan las demás del mismo producto (solo una principal
 *    por producto).
 *  - sort_order auto-asignado al final (max + 1) si no se pasa.
 *
 * Permiso: `catalog.manage`.
 */
class ProductImageController extends Controller
{
    use CursorPaginates;

    /**
     * GET /admin/products/{product}/images
     *
     * Query params:
     *  - page, per_page (default 20, max 100).
     *  - is_primary: bool.
     */
    public function index(Request $request, Product $product): JsonResponse
    {
        $this->assertProductInTenantScope(product: $product);

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $query = ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $product->id);

        if ($request->has('is_primary')) {
            $query->where('is_primary', $request->boolean('is_primary'));
        }

        $paginator = $query
            ->ordered()
            ->cursorPaginate($perPage);

        return $this->cursorPaginatedResponse(
            request: $request, query: $query, resourceClass: AdminProductImageResource::class,
        );
    }

    /**
     * GET /admin/products/{product}/images/{image}
     */
    public function show(Product $product, ProductImage $image): JsonResponse
    {
        $this->assertProductInTenantScope(product: $product);
        $this->assertImageBelongsToProduct(image: $image, product: $product);

        return response()->json(data: [
            'data' => (new AdminProductImageResource(resource: $image))->resolve(),
        ]);
    }

    /**
     * POST /admin/products/{product}/images
     */
    public function store(ProductImageStoreRequest $request, Product $product): JsonResponse
    {
        $this->assertProductInTenantScope(product: $product);

        $data = $request->validated();
        $isPrimary = (bool) ($data['is_primary'] ?? false);

        $image = DB::transaction(function () use ($data, $product, $isPrimary): ProductImage {
            if ($isPrimary) {
                $this->clearPrimaryFlag(productId: $product->id);
            }

            $sortOrder = $data['sort_order'] ?? $this->nextSortOrder(productId: $product->id);

            return ProductImage::query()->create(attributes: [
                'product_id' => $product->id,
                'image_url' => $data['image_url'],
                'alt_text' => $data['alt_text'] ?? null,
                'is_primary' => $isPrimary,
                'sort_order' => $sortOrder,
            ]);
        });

        return response()->json(data: [
            'data' => (new AdminProductImageResource(resource: $image))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/products/{product}/images/{image}
     */
    public function update(
        ProductImageUpdateRequest $request,
        Product $product,
        ProductImage $image,
    ): JsonResponse {
        $this->assertProductInTenantScope(product: $product);
        $this->assertImageBelongsToProduct(image: $image, product: $product);

        $data = $request->validated();
        $becomingPrimary = array_key_exists('is_primary', $data) && (bool) $data['is_primary'];

        $image = DB::transaction(function () use ($data, $image, $product, $becomingPrimary): ProductImage {
            if ($becomingPrimary) {
                $this->clearPrimaryFlag(productId: $product->id);
            }
            $image->update(attributes: $data);

            return $image;
        });

        return response()->json(data: [
            'data' => (new AdminProductImageResource(resource: $image))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/products/{product}/images/{image}
     */
    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        $this->assertProductInTenantScope(product: $product);
        $this->assertImageBelongsToProduct(image: $image, product: $product);
        $image->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Helper: desmarca todas las imágenes primarias de un producto.
     * Llamar dentro de una transacción que ya marca la nueva primaria.
     */
    private function clearPrimaryFlag(int $productId): void
    {
        ProductImage::query()
            ->where('product_id', $productId)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }

    /**
     * Helper: siguiente sort_order disponible (= max + 1, default 0).
     */
    private function nextSortOrder(int $productId): int
    {
        $max = (int) ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->max(column: 'sort_order');

        return $max + 1;
    }

    /**
     * Helper: valida que el producto esté en el scope del admin. 404 si no.
     */
    private function assertProductInTenantScope(Product $product): void
    {
        $user = auth()->user();
        if (! $user instanceof \App\Models\Auth\User) {
            abort(code: 401);
        }

        $accessible = $user->stores()->pluck('stores.id')->all();

        $inScope = \App\Models\Stores\ProductStore::query()
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->whereIn(column: 'store_id', values: $accessible)
            ->exists();

        if (! $inScope) {
            abort(code: 404, message: 'Producto no encontrado en tu tenant.');
        }
    }

    /**
     * Helper: valida que la imagen pertenece al producto de la URL.
     * Si no, 404 (evita fuga de datos cross-product).
     */
    private function assertImageBelongsToProduct(ProductImage $image, Product $product): void
    {
        if ((int) $image->product_id !== (int) $product->id) {
            abort(code: 404, message: 'Imagen no encontrada para este producto.');
        }
    }
}
