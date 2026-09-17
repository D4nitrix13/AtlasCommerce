<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\CursorPaginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttributeStoreRequest;
use App\Http\Requests\Admin\AttributeUpdateRequest;
use App\Http\Resources\Catalog\AdminAttributeResource;
use App\Models\Catalog\ProductAttribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de atributos (RF-CAT-027 parte 1).
 *
 * Los atributos son globales (no tienen tenant — el filtrado por tenant
 * ocurre vía products + product_stores). Son las definiciones de
 * características variantes (color, talla, material) que después se
 * asignan a productos específicos via product_attribute_values.
 *
 * Permiso: `catalog.manage`.
 *
 * Out of scope (separado):
 * - Asignación de valores a productos (`ProductAttributeValue`)
 * - Asignación de valores a variantes (`VariantAttributeValue`)
 * - Asociación atributo↔categoría (no existe tabla en el schema)
 */
class AttributeController extends Controller
{
    use CursorPaginates;
    /**
     * GET /admin/attributes
     *
     * Query params:
     *  - page, per_page (default 20, max 100).
     *  - q: filtro ILIKE por name o slug.
     *  - type: filtro por type (text, select, color, etc.).
     *  - is_filterable: bool.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $query = ProductAttribute::query();

        if ($search = trim((string) $request->query('q', ''))) {
            $needle = '%'.$search.'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('name', 'ILIKE', $needle)
                    ->orWhere('slug', 'ILIKE', $needle);
            });
        }

        if ($type = trim((string) $request->query('type', ''))) {
            $query->where(column: 'type', operator: '=', value: $type);
        }

        if ($request->has('is_filterable')) {
            $query->where('is_filterable', $request->boolean('is_filterable'));
        }

        $paginator = $query
            ->orderBy('name')
            ->cursorPaginate($perPage);

        return $this->cursorPaginatedResponse(
            request: $request, query: $query, resourceClass: AdminAttributeResource::class,
        );
    }

    /**
     * GET /admin/attributes/{attribute}
     */
    public function show(ProductAttribute $attribute): JsonResponse
    {
        return response()->json(data: [
            'data' => (new AdminAttributeResource(resource: $attribute))->resolve(),
        ]);
    }

    /**
     * POST /admin/attributes
     */
    public function store(AttributeStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $attribute = ProductAttribute::query()->create(attributes: [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'is_filterable' => $data['is_filterable'] ?? false,
            'is_required' => $data['is_required'] ?? false,
        ]);

        return response()->json(data: [
            'data' => (new AdminAttributeResource(resource: $attribute))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/attributes/{attribute}
     */
    public function update(AttributeUpdateRequest $request, ProductAttribute $attribute): JsonResponse
    {
        $data = $request->validated();

        $attribute = DB::transaction(function () use ($attribute, $data): ProductAttribute {
            $attribute->update(attributes: $data);

            return $attribute;
        });

        return response()->json(data: [
            'data' => (new AdminAttributeResource(resource: $attribute))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/attributes/{attribute}
     *
     * Bloqueado si el atributo está asignado a algún producto
     * (atributo_product). El cliente debe reasignar primero.
     *
     * No usamos soft delete (el modelo no lo implementa); un delete
     * aquí dispara el cascade del FK attribute_id → limpia los
     * pivots automáticamente.
     */
    public function destroy(ProductAttribute $attribute): JsonResponse
    {
        $assignedCount = DB::table('attribute_product')
            ->where(column: 'attribute_id', operator: '=', value: $attribute->id)
            ->count();

        if ($assignedCount > 0) {
            throw new RuntimeException(
                message: 'No podés eliminar el atributo porque está asignado a productos. Reasigná o eliminá los valores primero.',
            );
        }

        $attribute->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }
}
