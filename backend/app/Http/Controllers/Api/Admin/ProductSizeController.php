<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\CursorPaginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductSizeStoreRequest;
use App\Http\Requests\Admin\ProductSizeUpdateRequest;
use App\Http\Resources\Catalog\AdminProductSizeResource;
use App\Models\Catalog\ProductSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de tallas/capacidades de producto (`product_sizes`).
 *
 * Cada talla pertenece a un producto (y por extensión al tenant):
 * solo se ven/modifican tallas cuyos productos están en las tiendas
 * accesibles del admin actual.
 *
 * `value` + `unit` expresan la capacidad numérica (la taza de 11 oz es
 * `value: 11, unit: 'oz'`); las tallas de ropa (CH/M/G) dejan ambos en
 * null.
 *
 * Permiso: `catalog.manage`.
 *
 * Restricción DB: UNIQUE(product_id, slug) — un producto no puede tener
 * dos tallas con el mismo slug. El controller captura el
 * UniqueConstraintViolationException y lo traduce a un mensaje claro.
 *
 * Reordenamiento: `position` se guarda tal cual llega. NO se re-ordenan
 * las demás tallas del producto automáticamente — el admin que reordena
 * envía las posiciones nuevas de todas las tallas que cambian.
 */
class ProductSizeController extends Controller
{
    use CursorPaginates;

    /**
     * GET /admin/catalog/product-sizes
     *
     * Query params:
     *  - product_id: filtro por producto (recomendado).
     *  - page, per_page (default 20, max 100).
     *
     * Orden: position ascendente, con id como desempate.
     */
    public function index(Request $request): JsonResponse
    {
        $accessibleProductIds = $this->accessibleProductIds();

        $query = ProductSize::query()
            ->whereIn(column: 'product_id', values: $accessibleProductIds);

        if ($request->has('product_id')) {
            $productId = (int) $request->query('product_id');
            if (! in_array($productId, $accessibleProductIds, strict: true)) {
                abort(code: 403, message: 'No tenés acceso a ese producto.');
            }
            $query->where(column: 'product_id', operator: '=', value: $productId);
        }

        $query
            ->orderBy(column: 'position', direction: 'asc')
            ->orderBy(column: 'id', direction: 'asc');

        return $this->cursorPaginatedResponse(
            request: $request, query: $query, resourceClass: AdminProductSizeResource::class,
        );
    }

    /**
     * GET /admin/catalog/product-sizes/{productSize}
     */
    public function show(ProductSize $productSize): JsonResponse
    {
        $this->assertProductInTenantScope(productId: (int) $productSize->product_id);

        return response()->json(data: [
            'data' => (new AdminProductSizeResource(resource: $productSize))->resolve(),
        ]);
    }

    /**
     * POST /admin/catalog/product-sizes
     */
    public function store(ProductSizeStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->assertProductInTenantScope(productId: (int) $data['product_id']);

        try {
            $size = DB::transaction(function () use ($data): ProductSize {
                return ProductSize::query()->create(attributes: [
                    'product_id' => $data['product_id'],
                    'slug' => $data['slug'],
                    'label' => $data['label'],
                    'value' => $data['value'] ?? null,
                    'unit' => $data['unit'] ?? null,
                    'position' => $data['position'] ?? 0,
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            abort(code: 409, message: 'Ya existe una talla con este slug para este producto.');
        }

        return response()->json(data: [
            'data' => (new AdminProductSizeResource(resource: $size))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/catalog/product-sizes/{productSize}
     */
    public function update(ProductSizeUpdateRequest $request, ProductSize $productSize): JsonResponse
    {
        $this->assertProductInTenantScope(productId: (int) $productSize->product_id);

        try {
            $productSize = DB::transaction(function () use ($request, $productSize): ProductSize {
                $productSize->update(attributes: $request->validated());

                return $productSize;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            abort(code: 409, message: 'Ya existe una talla con este slug para este producto.');
        }

        return response()->json(data: [
            'data' => (new AdminProductSizeResource(resource: $productSize))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/catalog/product-sizes/{productSize}
     */
    public function destroy(ProductSize $productSize): JsonResponse
    {
        $this->assertProductInTenantScope(productId: (int) $productSize->product_id);
        $productSize->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Helper: IDs de productos accesibles por el admin actual.
     *
     * @return array<int, int>
     */
    private function accessibleProductIds(): array
    {
        $user = auth()->user();
        if (! $user instanceof \App\Models\Auth\User) {
            abort(code: 401);
        }

        return \App\Models\Stores\ProductStore::query()
            ->whereIn(column: 'store_id', values: $user->stores()->pluck('stores.id')->all())
            ->pluck(column: 'product_id')
            ->unique()
            ->all();
    }

    /**
     * Helper: valida que el producto esté en el scope del admin. 404 si no.
     */
    private function assertProductInTenantScope(int $productId): void
    {
        $inScope = in_array(
            needle: $productId,
            haystack: $this->accessibleProductIds(),
            strict: true,
        );

        if (! $inScope) {
            abort(code: 404, message: 'Producto no encontrado en tu tenant.');
        }
    }
}
