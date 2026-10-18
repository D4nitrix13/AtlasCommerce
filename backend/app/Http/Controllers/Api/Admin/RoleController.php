<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\CursorPaginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RolePermissionsSyncRequest;
use App\Http\Requests\Admin\RoleStoreRequest;
use App\Http\Requests\Admin\RoleUpdateRequest;
use App\Http\Resources\Auth\PermissionResource;
use App\Http\Resources\Auth\RoleResource;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de Roles + asignación de permisos.
 *
 * RF-AUTH-008 — Asignación de roles y permisos (admin):
 *  - Crear / listar / editar / desactivar / eliminar roles.
 *  - Componer qué permisos tiene cada rol (sync atómico).
 *
 * Multi-tenancy:
 *  Las rutas se montan bajo `store.domain` para tener la tienda activa
 *  resuelta. Los roles son cross-tenant (viven en el catálogo global),
 *  pero el log de auditoría debe ejecutarse en el contexto del tenant
 *  activo (lo hace el trait LogsActivity del modelo Role).
 *
 * Protecciones:
 *  - El rol con `slug = 'admin'` NO se puede eliminar ni renombrar
 *    (es crítico para el bypass de permisos en CheckPermission).
 *  - El sync de permisos sobre `admin` es válido pero es un no-op
 *    funcional (el middleware bypasea igual).
 *
 * Permiso requerido:
 *  - `auth.roles.manage` para todas las acciones.
 */
class RoleController extends Controller
{
    use CursorPaginates;

    /**
     * GET /admin/roles
     *
     * Lista paginada. Query params:
     *  - page, per_page (default 20, max 100).
     *  - q (filtro ILIKE por name o slug).
     *  - is_active (true/false).
     *  - with_trashed (1 = incluye soft-deleted).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $query = Role::query();

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
            request: $request, query: $query, resourceClass: RoleResource::class,
        );
    }

    /**
     * GET /admin/roles/{role}
     */
    public function show(Role $role): JsonResponse
    {
        $role->load(relations: 'permissions');

        return response()->json(data: [
            'data' => (new RoleResource(resource: $role))->resolve(),
        ]);
    }

    /**
     * POST /admin/roles
     *
     * Crea un rol nuevo. Si vienen `permissions` en el body, sincroniza
     * los permisos como parte de la creación (en transacción).
     */
    public function store(RoleStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $permissionSlugs = $data['permissions'] ?? [];

        $role = DB::transaction(function () use ($data, $permissionSlugs): Role {
            $role = Role::query()->create(attributes: [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if ($permissionSlugs !== []) {
                $this->syncPermissionsBySlug(role: $role, slugs: $permissionSlugs);
            }

            return $role;
        });

        $role->load(relations: 'permissions');

        return response()->json(data: [
            'data' => (new RoleResource(resource: $role))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/roles/{role}
     */
    public function update(RoleUpdateRequest $request, Role $role): JsonResponse
    {
        $data = $request->validated();

        $role = DB::transaction(function () use ($role, $data): Role {
            $role->update(attributes: $data);

            return $role;
        });

        $role->load(relations: 'permissions');

        return response()->json(data: [
            'data' => (new RoleResource(resource: $role))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/roles/{role}
     *
     * Soft-delete (Role usa SoftDeletes). El slug `admin` está protegido.
     */
    public function destroy(Role $role): JsonResponse
    {
        if ($role->slug === 'admin') {
            throw new RuntimeException(
                message: 'El rol "admin" no puede eliminarse (es crítico para el bypass de permisos).',
            );
        }

        DB::transaction(function () use ($role): void {
            // Rompemos la FK de usuarios antes del soft-delete para no
            // dejar `users.role_id` apuntando a un row borrado lógicamente.
            // (PostgreSQL no lo permite por la FK restrict si hay refs).
            \App\Models\Auth\User::query()
                ->where(column: 'role_id', operator: '=', value: $role->id)
                ->update(['role_id' => null]);

            $role->permissions()->detach();
            $role->delete();
        });

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }

    /**
     * PUT /admin/roles/{role}/permissions
     *
     * Sync atómico: reemplaza el set completo de permisos del rol.
     */
    public function syncPermissions(
        RolePermissionsSyncRequest $request,
        Role $role,
    ): JsonResponse {
        $slugs = (array) $request->validated(key: 'permissions');

        DB::transaction(function () use ($role, $slugs): void {
            $this->syncPermissionsBySlug(role: $role, slugs: $slugs);
        });

        $role->load(relations: 'permissions');

        return response()->json(data: [
            'data' => (new RoleResource(resource: $role))->resolve(),
        ]);
    }

    /**
     * GET /admin/permissions
     *
     * Lista el catálogo completo de permisos, agrupable por módulo.
     */
    public function listPermissions(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('module')
            ->orderBy('slug')
            ->get();

        return response()->json(data: [
            'data' => PermissionResource::collection(resource: $permissions)->resolve(),
            'meta' => [
                'total' => $permissions->count(),
                'modules' => $permissions->pluck('module')->unique()->values()->all(),
            ],
        ]);
    }

    /**
     * Helper: sincroniza el set de permisos del rol a partir de slugs.
     * Valida que todos los slugs existan antes de aplicar.
     *
     * @param  array<int, string>  $slugs
     */
    private function syncPermissionsBySlug(Role $role, array $slugs): void
    {
        $ids = Permission::query()
            ->whereIn(column: 'slug', values: $slugs)
            ->pluck(column: 'id')
            ->all();

        $found = count($ids);
        $expected = count($slugs);

        if ($found !== $expected) {
            $existing = Permission::query()
                ->whereIn(column: 'slug', values: $slugs)
                ->pluck(column: 'slug')
                ->all();
            $missing = array_values(array_diff($slugs, $existing));

            throw new RuntimeException(
                message: 'Permisos inexistentes: '.implode(separator: ', ', array: $missing),
            );
        }

        $role->permissions()->sync(ids: $ids);
    }
}
