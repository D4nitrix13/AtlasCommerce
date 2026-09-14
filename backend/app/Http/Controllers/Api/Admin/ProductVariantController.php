<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductVariantStoreRequest;
use App\Http\Requests\Admin\ProductVariantUpdateRequest;
use App\Http\Resources\Catalog\AdminProductVariantResource;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de variantes de producto.
 *
 * Las variantes son hijas de un Product, así que heredan el aislamiento
 * por tenant: si el product no está publicado en ninguna tienda del
 * admin actual, el variant no es visible.
 *
 * El `product_id` viene en el body para POST. Para update/show/delete,
 * el variant se carga por route-model-binding y se valida que su
 * product esté en el scope del admin.
 *
 * Permiso: `catalog.manage` (admin bypasea vía CheckPermission).
 */
class ProductVariantController extends Controller
{
    /**
     * GET /admin/variants
     *
     * Query params:
     *  - page, per_page (default 20, max 100).
     *  - product_id: filtro por product (opcional).
     *  - is_active: filtro por estado activo.
     *  - q: filtro ILIKE por name, sku o barcode.
     *  - with_trashed: 1 = incluye soft-deleted.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $accessibleStoreIds = $this->accessibleStoreIds();

        // El Product::stores() está mal modelado en este repo (ver
        // sesión de memoria). Workaround: usar ProductStore directo.
        $productIdsInScope = \App\Models\Stores\ProductStore::query()
            ->whereIn(column: 'store_id', values: $accessibleStoreIds)
            ->pluck(column: 'product_id')
            ->unique()
            ->all();

        $query = ProductVariant::query()
            ->whereIn(column: 'product_id', values: $productIdsInScope);

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $needle = '%'.$search.'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('name', 'ILIKE', $needle)
                    ->orWhere('sku', 'ILIKE', $needle)
                    ->orWhere('barcode', 'ILIKE', $needle);
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('product_id')) {
            $productId = (int) $request->query('product_id');
            $query->where('product_id', $productId);
        }

        $paginator = $query
            ->orderBy('name')
            ->paginate(perPage: $perPage);

        return response()->json(data: [
            'data' => AdminProductVariantResource::collection(resource: $paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /admin/variants/{variant}
     */
    public function show(ProductVariant $variant): JsonResponse
    {
        $this->assertVariantInTenantScope(variant: $variant);

        return response()->json(data: [
            'data' => (new AdminProductVariantResource(resource: $variant))->resolve(),
        ]);
    }

    /**
     * POST /admin/variants
     */
    public function store(ProductVariantStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $product = $this->findProductInTenantScope(productId: (int) $data['product_id']);

        $variant = DB::transaction(function () use ($data, $product): ProductVariant {
            return ProductVariant::query()->create(attributes: [
                'product_id' => $product->id,
                'name' => $data['name'],
                'sku' => $data['sku'],
                'price_cents' => $data['price_cents'] ?? 0,
                'cost_cents' => $data['cost_cents'] ?? 0,
                'barcode' => $data['barcode'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });

        return response()->json(data: [
            'data' => (new AdminProductVariantResource(resource: $variant))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/variants/{variant}
     */
    public function update(ProductVariantUpdateRequest $request, ProductVariant $variant): JsonResponse
    {
        $this->assertVariantInTenantScope(variant: $variant);

        $data = $request->validated();

        $variant = DB::transaction(function () use ($variant, $data): ProductVariant {
            $variant->update(attributes: $data);

            return $variant;
        });

        return response()->json(data: [
            'data' => (new AdminProductVariantResource(resource: $variant))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/variants/{variant}
     *
     * Soft delete. El cascadeOnDelete en product_variants ya limpia
     * cuando se borra el product (lo hace ProductController::destroy).
     */
    public function destroy(ProductVariant $variant): JsonResponse
    {
        $this->assertVariantInTenantScope(variant: $variant);
        $variant->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Helper: IDs de tiendas accesibles por el admin actual.
     *
     * @return array<int, int>
     */
    private function accessibleStoreIds(): array
    {
        $user = auth()->user();
        if (! $user instanceof \App\Models\Auth\User) {
            abort(code: 401);
        }

        return $user->stores()->pluck('stores.id')->all();
    }

    /**
     * Helper: valida que el product del variant esté en el scope del
     * admin actual. Si no, aborta 404.
     */
    private function assertVariantInTenantScope(ProductVariant $variant): void
    {
        $this->findProductInTenantScope(productId: (int) $variant->product_id);
    }

    /**
     * Helper: busca el product por id y valida que esté en el scope
     * del admin. Aborta 404 si no.
     */
    private function findProductInTenantScope(int $productId): Product
    {
        $accessible = $this->accessibleStoreIds();

        $inScope = \App\Models\Stores\ProductStore::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->whereIn(column: 'store_id', values: $accessible)
            ->exists();

        if (! $inScope) {
            abort(code: 404, message: 'Producto no encontrado en tu tenant.');
        }

        $product = Product::query()->find(id: $productId);
        if ($product === null) {
            abort(code: 404);
        }

        return $product;
    }
}
