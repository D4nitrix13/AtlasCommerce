<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\CursorPaginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryStoreRequest;
use App\Http\Requests\Admin\CategoryUpdateRequest;
use App\Http\Resources\Catalog\AdminCategoryResource;
use App\Models\Catalog\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de categorías (RF-CAT-025).
 *
 * Las categorías son globales (no tienen tenant — el `category_id`
 * en `products` es el que se filtra por tenant via `product_stores`).
 *
 * Reglas:
 *  - Slug único global (kebab-case).
 *  - parent_id NO puede ser self ni descendiente (anti-ciclo).
 *  - No se puede borrar una categoría con productos (FK restrict en
 *    products.category_id). El cliente debe reasignar o archivar
 *    los productos primero.
 *
 * Permiso: `catalog.manage` (admin bypasea).
 */
class CategoryController extends Controller
{
    use CursorPaginates;
    /**
     * GET /admin/categories
     *
     * Query params:
     *  - page, per_page (default 20, max 100).
     *  - q: filtro ILIKE por name o slug.
     *  - parent_id: filtro por parent (null = solo raíces).
     *  - is_active: bool.
     *  - with_trashed: 1 = incluye soft-deleted.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $query = Category::query();

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

        if ($request->has('parent_id')) {
            $parentIdRaw = $request->query('parent_id');
            if ($parentIdRaw === 'null' || $parentIdRaw === null) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', (int) $parentIdRaw);
            }
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $paginator = $query
            ->orderBy('name')
            ->cursorPaginate($perPage);

        return $this->cursorPaginatedResponse(
            request: $request, query: $query, resourceClass: AdminCategoryResource::class,
        );
    }

    /**
     * GET /admin/categories/{category}
     */
    public function show(Category $category): JsonResponse
    {
        return response()->json(data: [
            'data' => (new AdminCategoryResource(resource: $category))->resolve(),
        ]);
    }

    /**
     * POST /admin/categories
     */
    public function store(CategoryStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $category = Category::query()->create(attributes: [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'parent_id' => $data['parent_id'] ?? null,
            'description' => $data['description'] ?? null,
            'image_path' => $data['image_path'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(data: [
            'data' => (new AdminCategoryResource(resource: $category))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/categories/{category}
     */
    public function update(CategoryUpdateRequest $request, Category $category): JsonResponse
    {
        $data = $request->validated();

        $category = DB::transaction(function () use ($category, $data): Category {
            $category->update(attributes: $data);

            return $category;
        });

        return response()->json(data: [
            'data' => (new AdminCategoryResource(resource: $category))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/categories/{category}
     *
     * Bloqueado si la categoría tiene productos (FK restrict en
     * products.category_id). El cliente debe reasignar los productos
     * primero.
     */
    public function destroy(Category $category): JsonResponse
    {
        $hasProducts = DB::table('products')
            ->where(column: 'category_id', operator: '=', value: $category->id)
            ->exists();

        if ($hasProducts) {
            throw new RuntimeException(
                message: 'No podés eliminar la categoría porque tiene productos asociados. Reasigná los productos primero.',
            );
        }

        $category->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }
}
