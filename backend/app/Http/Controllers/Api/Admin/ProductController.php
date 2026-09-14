<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductStoreRequest;
use App\Http\Requests\Admin\ProductStoresSyncRequest;
use App\Http\Requests\Admin\ProductUpdateRequest;
use App\Http\Resources\Catalog\AdminProductResource;
use App\Models\Catalog\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de productos (RF-CAT-026).
 *
 * Permite a un admin con `catalog.manage`:
 *  - Listar, ver, crear, editar productos.
 *  - Archivar (soft) y des-archivar.
 *  - Asignar a qué tiendas se publica (sync atómico de stores).
 *  - Borrar duro (con cleanup de FKs).
 *
 * Aislamiento por tenant:
 *  El listado y los accesos filtran por `product_stores.store_id`
 *  contra las tiendas accesibles. Si un producto no está publicado
 *  en ninguna tienda del tenant actual, el admin no lo ve.
 *
 * Protecciones:
 *  - Al borrar, se desliga de product_stores + variants + images
 *    (cascade es restrict, hay que limpiar explícito).
 *  - El slug y sku son únicos globalmente; validar antes de
 *    escribir.
 */
class ProductController extends Controller
{
    /**
     * GET /admin/products
     *
     * Query params:
     *  - page, per_page (default 20, max 100).
     *  - q: filtro ILIKE por name, slug o sku.
     *  - is_active: bool.
     *  - category_id: filtro por categoría.
     *  - brand_id: filtro por marca.
     *  - store_id: filtro por tienda (debe estar en las tiendas del admin).
     *  - with_trashed: 1 = incluye soft-deleted.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $accessibleStoreIds = $this->accessibleStoreIds();

        $query = Product::query()
            ->whereIn(column: 'id', values: \App\Models\Stores\ProductStore::query()
                ->whereIn(column: 'store_id', values: $accessibleStoreIds)
                ->pluck(column: 'product_id')
                ->all(),
            );

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $needle = '%'.$search.'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('name', 'ILIKE', $needle)
                    ->orWhere('slug', 'ILIKE', $needle)
                    ->orWhere('sku', 'ILIKE', $needle);
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('category_id')) {
            $query->where('category_id', (int) $request->query('category_id'));
        }

        if ($request->has('brand_id')) {
            $query->where('brand_id', (int) $request->query('brand_id'));
        }

        if ($request->has('store_id')) {
            $storeId = (int) $request->query('store_id');
            if (! in_array($storeId, $accessibleStoreIds, strict: true)) {
                abort(code: 403, message: 'No tenés acceso a esa tienda.');
            }
            $query->whereIn(column: 'id', values: \App\Models\Stores\ProductStore::query()
                ->where(column: 'store_id', operator: '=', value: $storeId)
                ->pluck(column: 'product_id')
                ->all(),
            );
        }

        $paginator = $query
            ->orderBy('name')
            ->paginate(perPage: $perPage);

        return response()->json(data: [
            'data' => AdminProductResource::collection(resource: $paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /admin/products/{product}
     */
    public function show(Product $product): JsonResponse
    {
        $this->assertProductInTenantScope(product: $product);
        $product->load(relations: ['category', 'brand']);
        $product->setRelation(
            'stores',
            \App\Models\Stores\ProductStore::query()
                ->where(column: 'product_id', operator: '=', value: $product->id)
                ->get(),
        );

        return response()->json(data: [
            'data' => (new AdminProductResource(resource: $product))->resolve(),
        ]);
    }

    /**
     * POST /admin/products
     */
    public function store(ProductStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $storeIds = $data['store_ids'] ?? [];

        $this->assertStoresAccessible(storeIds: $storeIds);

        $product = DB::transaction(function () use ($data, $storeIds): Product {
            $product = Product::query()->create(attributes: [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'sku' => $data['sku'],
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'description' => $data['description'] ?? null,
                'base_price_cents' => $data['base_price_cents'] ?? 0,
                'cost_cents' => $data['cost_cents'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'published_at' => $data['published_at'] ?? null,
            ]);

            if ($storeIds !== []) {
                $this->syncProductStores(productId: $product->id, storeIds: $storeIds);
            }

            return $product;
        });

        $product->load(relations: ['category', 'brand']);
        $product->setRelation(
            'stores',
            \App\Models\Stores\ProductStore::query()
                ->where(column: 'product_id', operator: '=', value: $product->id)
                ->get(),
        );

        return response()->json(data: [
            'data' => (new AdminProductResource(resource: $product))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/products/{product}
     */
    public function update(ProductUpdateRequest $request, Product $product): JsonResponse
    {
        $this->assertProductInTenantScope(product: $product);

        $data = $request->validated();

        $product = DB::transaction(function () use ($product, $data): Product {
            $product->update(attributes: $data);

            return $product;
        });

        $product->load(relations: ['category', 'brand']);
        $product->setRelation(
            'stores',
            \App\Models\Stores\ProductStore::query()
                ->where(column: 'product_id', operator: '=', value: $product->id)
                ->get(),
        );

        return response()->json(data: [
            'data' => (new AdminProductResource(resource: $product))->resolve(),
        ]);
    }

    /**
     * POST /admin/products/{product}/archive
     *
     * Marca is_active=false y limpia published_at.
     */
    public function archive(Product $product): JsonResponse
    {
        $this->assertProductInTenantScope(product: $product);

        $product->update(attributes: [
            'is_active' => false,
            'published_at' => null,
        ]);
        $product->load(relations: ['category', 'brand']);
        $product->setRelation(
            'stores',
            \App\Models\Stores\ProductStore::query()
                ->where(column: 'product_id', operator: '=', value: $product->id)
                ->get(),
        );

        return response()->json(data: [
            'data' => (new AdminProductResource(resource: $product))->resolve(),
        ]);
    }

    /**
     * POST /admin/products/{product}/publish
     *
     * Marca is_active=true. Si published_at no viene, lo setea a now().
     */
    public function publish(Product $product): JsonResponse
    {
        $this->assertProductInTenantScope(product: $product);

        $product->update(attributes: [
            'is_active' => true,
            'published_at' => $product->published_at ?? now(),
        ]);
        $product->load(relations: ['category', 'brand']);
        $product->setRelation(
            'stores',
            \App\Models\Stores\ProductStore::query()
                ->where(column: 'product_id', operator: '=', value: $product->id)
                ->get(),
        );

        return response()->json(data: [
            'data' => (new AdminProductResource(resource: $product))->resolve(),
        ]);
    }

    /**
     * PUT /admin/products/{product}/stores
     *
     * Sync atómico del set de tiendas donde el producto está visible.
     */
    public function syncStores(
        ProductStoresSyncRequest $request,
        Product $product,
    ): JsonResponse {
        $this->assertProductInTenantScope(product: $product);

        $storeIds = (array) $request->validated(key: 'store_ids');
        $this->assertStoresAccessible(storeIds: $storeIds);

        DB::transaction(function () use ($product, $storeIds): void {
            $this->syncProductStores(productId: $product->id, storeIds: $storeIds);
        });

        $product->load(relations: ['category', 'brand']);
        $product->setRelation(
            'stores',
            \App\Models\Stores\ProductStore::query()
                ->where(column: 'product_id', operator: '=', value: $product->id)
                ->get(),
        );

        return response()->json(data: [
            'data' => (new AdminProductResource(resource: $product))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/products/{product}
     *
     * Borrado duro. Antes limpia FKs que tengan restrict:
     *  - product_stores (pivot visibility)
     *  - product_variants
     *  - product_images
     *  - product_attribute_values
     *
     * NO se borra en orders/invoices (preservar trazabilidad fiscal).
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->assertProductInTenantScope(product: $product);

        DB::transaction(function () use ($product): void {
            \App\Models\Stores\ProductStore::query()
                ->where(column: 'product_id', operator: '=', value: $product->id)
                ->delete();
            $product->variants()->delete();
            $product->images()->delete();
            $product->attributeValues()->delete();
            $product->delete();
        });

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Helper: IDs de tiendas accesibles por el usuario actual.
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
     * Helper: aborta si el producto no está publicado en ninguna
     * tienda accesible por el admin actual.
     */
    private function assertProductInTenantScope(Product $product): void
    {
        $accessible = $this->accessibleStoreIds();

        $shared = \App\Models\Stores\ProductStore::query()
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->whereIn(column: 'store_id', values: $accessible)
            ->exists();

        if (! $shared) {
            abort(code: 404, message: 'Producto no encontrado en tu tenant.');
        }
    }

    /**
     * Helper: valida que todos los store_ids pertenezcan a las tiendas
     * accesibles por el admin.
     *
     * @param  array<int, int>  $storeIds
     */
    private function assertStoresAccessible(array $storeIds): void
    {
        if ($storeIds === []) {
            return;
        }

        $accessible = $this->accessibleStoreIds();

        $invalid = array_values(array_diff($storeIds, $accessible));
        if ($invalid !== []) {
            abort(
                code: 403,
                message: 'No podés asignar productos a tiendas que no administrás: '.implode(', ', $invalid),
            );
        }
    }

    /**
     * Helper: sincroniza el set de tiendas donde el producto está
     * publicado. Borrar y re-crear (más simple que sync parcial dado
     * que la relación `Product::stores()` está rota en este repo —
     * ver memoria sobre belongsToMany mal modelado).
     *
     * @param  array<int, int>  $storeIds
     */
    private function syncProductStores(int $productId, array $storeIds): void
    {
        \App\Models\Stores\ProductStore::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->delete();

        foreach ($storeIds as $storeId) {
            \App\Models\Stores\ProductStore::query()->create(attributes: [
                'product_id' => $productId,
                'store_id' => $storeId,
                'is_visible' => true,
            ]);
        }
    }
}
