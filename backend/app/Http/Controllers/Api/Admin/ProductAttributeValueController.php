<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductAttributeValueStoreRequest;
use App\Http\Requests\Admin\ProductAttributeValueUpdateRequest;
use App\Http\Resources\Catalog\AdminProductAttributeValueResource;
use App\Models\Catalog\ProductAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de asignaciones atributo↔producto (RF-CAT-027 parte 2).
 *
 * Las asignaciones son scoped al product (y por extensión al tenant):
 * solo se ven/modifican valores cuyos productos están en las tiendas
 * accesibles del admin actual.
 *
 * Permiso: `catalog.manage`.
 *
 * Restricción DB: UNIQUE(product_id, attribute_id) — un atributo solo
 * puede asignarse una vez por producto. El controller captura el
 * UniqueConstraintViolationException y lo traduce a un mensaje claro.
 */
class ProductAttributeValueController extends Controller
{
    /**
     * GET /admin/attribute-values
     *
     * Query params:
     *  - product_id: filtro por producto (recomendado).
     *  - attribute_id: filtro por atributo.
     *  - page, per_page (default 20, max 100).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $accessibleProductIds = $this->accessibleProductIds();

        $query = ProductAttributeValue::query()
            ->whereIn(column: 'product_id', values: $accessibleProductIds);

        if ($request->has('product_id')) {
            $productId = (int) $request->query('product_id');
            if (! in_array($productId, $accessibleProductIds, strict: true)) {
                abort(code: 403, message: 'No tenés acceso a ese producto.');
            }
            $query->where(column: 'product_id', operator: '=', value: $productId);
        }

        if ($request->has('attribute_id')) {
            $query->where(column: 'attribute_id', operator: '=', value: (int) $request->query('attribute_id'));
        }

        $paginator = $query
            ->orderBy('id')
            ->paginate(perPage: $perPage);

        return response()->json(data: [
            'data' => AdminProductAttributeValueResource::collection(resource: $paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /admin/attribute-values/{attributeValue}
     */
    public function show(ProductAttributeValue $attributeValue): JsonResponse
    {
        $this->assertProductInTenantScope(productId: (int) $attributeValue->product_id);

        return response()->json(data: [
            'data' => (new AdminProductAttributeValueResource(resource: $attributeValue))->resolve(),
        ]);
    }

    /**
     * POST /admin/attribute-values
     */
    public function store(ProductAttributeValueStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->assertProductInTenantScope(productId: (int) $data['product_id']);

        try {
            $value = DB::transaction(function () use ($data): ProductAttributeValue {
                return ProductAttributeValue::query()->create(attributes: [
                    'product_id' => $data['product_id'],
                    'attribute_id' => $data['attribute_id'],
                    'value' => $data['value'],
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            abort(code: 409, message: 'Este atributo ya está asignado a este producto.');
        }

        return response()->json(data: [
            'data' => (new AdminProductAttributeValueResource(resource: $value))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/attribute-values/{attributeValue}
     */
    public function update(ProductAttributeValueUpdateRequest $request, ProductAttributeValue $attributeValue): JsonResponse
    {
        $this->assertProductInTenantScope(productId: (int) $attributeValue->product_id);

        $attributeValue = DB::transaction(function () use ($request, $attributeValue): ProductAttributeValue {
            $attributeValue->update(attributes: $request->validated());

            return $attributeValue;
        });

        return response()->json(data: [
            'data' => (new AdminProductAttributeValueResource(resource: $attributeValue))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/attribute-values/{attributeValue}
     */
    public function destroy(ProductAttributeValue $attributeValue): JsonResponse
    {
        $this->assertProductInTenantScope(productId: (int) $attributeValue->product_id);
        $attributeValue->delete();

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
