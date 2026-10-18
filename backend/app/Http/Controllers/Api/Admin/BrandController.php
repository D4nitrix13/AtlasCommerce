<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\CursorPaginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandStoreRequest;
use App\Http\Requests\Admin\BrandUpdateRequest;
use App\Http\Resources\Catalog\AdminBrandResource;
use App\Models\Catalog\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de marcas (RF-CAT-024).
 *
 * Las marcas son globales (no tienen tenant — el `brand_id` en
 * `products` se filtra por tenant via `product_stores.store_id`).
 *
 * Diferencia con categorías: `products.brand_id` es nullOnDelete,
 * así que borrar una marca con productos NO falla — sólo deja esos
 * productos con brand_id NULL (el admin puede reasignarlos después).
 *
 * Permiso: `catalog.manage` (admin bypasea).
 */
class BrandController extends Controller
{
    use CursorPaginates;

    /**
     * GET /admin/brands
     *
     * Query params:
     *  - page, per_page (default 20, max 100).
     *  - q: filtro ILIKE por name o slug.
     *  - is_active: bool.
     *  - with_trashed: 1 = incluye soft-deleted.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $query = Brand::query();

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $needle = '%'.$search.'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('name', 'ILIKE', $needle)
                    ->orWhere('slug', 'ILIKE', $needle);
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $paginator = $query
            ->orderBy('name')
            ->cursorPaginate($perPage);

        return $this->cursorPaginatedResponse(
            request: $request, query: $query, resourceClass: AdminBrandResource::class,
        );
    }

    /**
     * GET /admin/brands/{brand}
     */
    public function show(Brand $brand): JsonResponse
    {
        return response()->json(data: [
            'data' => (new AdminBrandResource(resource: $brand))->resolve(),
        ]);
    }

    /**
     * POST /admin/brands
     */
    public function store(BrandStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $brand = Brand::query()->create(attributes: [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'logo_url' => $data['logo_url'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(data: [
            'data' => (new AdminBrandResource(resource: $brand))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/brands/{brand}
     */
    public function update(BrandUpdateRequest $request, Brand $brand): JsonResponse
    {
        $data = $request->validated();

        $brand = DB::transaction(function () use ($brand, $data): Brand {
            $brand->update(attributes: $data);

            return $brand;
        });

        return response()->json(data: [
            'data' => (new AdminBrandResource(resource: $brand))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/brands/{brand}
     *
     * Soft delete. Los productos que tenían esta marca quedan con
     * brand_id NULL (nullOnDelete en el FK). No falla aunque haya
     * productos asociados.
     */
    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }
}
