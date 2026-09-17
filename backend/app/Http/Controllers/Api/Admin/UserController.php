<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\CursorPaginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\Auth\Role;
use App\Models\Auth\User as UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de usuarios internos (RF-AUTH-009).
 *
 * Reglas:
 *  - Sólo accesible por usuarios con `auth.users.manage`
 *    (los admins con slug `admin` bypasean el check).
 *  - Aislamiento por tenant: el listado sólo muestra usuarios
 *    vinculados a alguna las las del admin actual
 *    (sus `user_stores`).
 *  - No podés borrarte a vos mismo.
 *  - No podés degradar tu propio rol (auto-demotion).
 *  - No podés borrar al ÚLTIMO admin del tenant activo (lockout).
 *
 * Stores que se asignan al crear/editar deben pertenecer a las
 * tiendas del admin actual (no se puede asignar users a tiendas
 *   que no administrás vos).
 */
class UserController extends Controller
{
    use CursorPaginates;
    /**
     * GET /admin/users
     *
     * Query params:
     *  - page, per_page (default 20, max 100).
     *  - q: filtro ILIKE por email, first_name o last_name.
     *  - role_id: filtro por rol.
     *  - store_id: filtro por tienda (debe estar en las tiendas del admin).
     *  - is_active: bool.
     *  - with_trashed: 1 = incluye soft-deleted (futuro: hoy User no usa SoftDeletes).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $accessibleStoreIds = $this->accessibleStoreIds();

        $query = UserModel::query()
            ->whereHas('stores', function ($q) use ($accessibleStoreIds): void {
                $q->whereIn('stores.id', $accessibleStoreIds);
            });

        if ($search = trim((string) $request->query('q', ''))) {
            $needle = '%'.$search.'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('email', 'ILIKE', $needle)
                    ->orWhere('first_name', 'ILIKE', $needle)
                    ->orWhere('last_name', 'ILIKE', $needle);
            });
        }

        if ($request->has('role_id')) {
            $query->where('role_id', (int) $request->query('role_id'));
        }

        if ($request->has('store_id')) {
            $storeId = (int) $request->query('store_id');
            if (! in_array($storeId, $accessibleStoreIds, strict: true)) {
                abort(code: 403, message: 'No tenés acceso a esa tienda.');
            }
            $query->whereHas('stores', function ($q) use ($storeId): void {
                $q->where('stores.id', $storeId);
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $paginator = $query
            ->orderBy('email')
            ->cursorPaginate($perPage);

        return $this->cursorPaginatedResponse(
            request: $request, query: $query, resourceClass: UserResource::class,
        );
    }

    /**
     * GET /admin/users/{user}
     */
    public function show(UserModel $user): JsonResponse
    {
        $this->assertUserInTenantScope(user: $user);

        $user->load(relations: ['role', 'stores']);

        return response()->json(data: [
            'data' => (new UserResource(resource: $user))->resolve(),
        ]);
    }

    /**
     * POST /admin/users
     */
    public function store(UserStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $storeIds = $data['store_ids'] ?? [];

        $this->assertStoresAccessible(storeIds: $storeIds);

        $user = DB::transaction(function () use ($data, $storeIds): UserModel {
            $user = UserModel::query()->create(attributes: [
                'email' => $data['email'],
                'password' => $data['password'], // cast `hashed` aplica Hash::make
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'role_id' => $data['role_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if ($storeIds !== []) {
                $user->stores()->sync(ids: $storeIds);
            }

            return $user;
        });

        $user->load(relations: ['role', 'stores']);

        return response()->json(data: [
            'data' => (new UserResource(resource: $user))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/users/{user}
     */
    public function update(UserUpdateRequest $request, UserModel $user): JsonResponse
    {
        $this->assertUserInTenantScope(user: $user);

        $data = $request->validated();

        if (array_key_exists('store_ids', $data)) {
            $this->assertStoresAccessible(storeIds: $data['store_ids']);
        }

        $user = DB::transaction(function () use ($user, $data): UserModel {
            $update = collect(value: $data)->except(keys: 'store_ids')->toArray();

            if ($update !== []) {
                $user->update(attributes: $update);
            }

            if (array_key_exists('store_ids', $data)) {
                $user->stores()->sync(ids: $data['store_ids']);
            }

            return $user;
        });

        $user->load(relations: ['role', 'stores']);

        return response()->json(data: [
            'data' => (new UserResource(resource: $user))->resolve(),
        ]);
    }

    /**
     * POST /admin/users/{user}/suspend
     */
    public function suspend(UserModel $user): JsonResponse
    {
        $this->assertUserInTenantScope(user: $user);
        $this->assertNotSelf(target: $user, action: 'suspenderte');

        $user->update(attributes: ['is_active' => false]);
        $user->load(relations: ['role', 'stores']);

        return response()->json(data: [
            'data' => (new UserResource(resource: $user))->resolve(),
        ]);
    }

    /**
     * POST /admin/users/{user}/reactivate
     */
    public function reactivate(UserModel $user): JsonResponse
    {
        $this->assertUserInTenantScope(user: $user);
        $this->assertNotSelf(target: $user, action: 'reactivarte');

        $user->update(attributes: ['is_active' => true]);
        $user->load(relations: ['role', 'stores']);

        return response()->json(data: [
            'data' => (new UserResource(resource: $user))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/users/{user}
     *
     * Borrado duro: el User y sus user_stores. NO se tocan orders/
     * invoices (preservar auditoría fiscal).
     *
     * Protecciones:
     *  - No podés borrarte a vos mismo.
     *  - No podés borrar al ÚLTIMO admin (slug `admin`) de cualquier
     *    tienda del tenant activo.
     */
    public function destroy(UserModel $user): JsonResponse
    {
        $this->assertUserInTenantScope(user: $user);
        // El check de "último admin del tenant" corre ANTES del check
        // de self: si el admin intenta borrarse y es el único admin,
        // el mensaje específico de lockout es más útil que el genérico
        // de self-destruct.
        $this->assertNotLastAdmin(target: $user);
        $this->assertNotSelf(target: $user, action: 'eliminarte');

        DB::transaction(function () use ($user): void {
            $user->stores()->detach();
            $user->tokens()->delete();
            $user->socialAccounts()->delete();
            $user->delete();
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
        if (! $user instanceof UserModel) {
            abort(code: 401);
        }

        return $user->stores()->pluck('stores.id')->all();
    }

    /**
     * Helper: aborta si el target no pertenece a ninguna tienda accesible.
     */
    private function assertUserInTenantScope(UserModel $user): void
    {
        $accessible = $this->accessibleStoreIds();

        $shared = $user->stores()
            ->whereIn('stores.id', $accessible)
            ->exists();

        if (! $shared) {
            abort(code: 404, message: 'Usuario no encontrado en tu tenant.');
        }
    }

    /**
     * Helper: aborta si el target es el usuario actual.
     */
    private function assertNotSelf(UserModel $target, string $action): void
    {
        $current = auth()->user();
        if ($current instanceof UserModel && $target->id === $current->id) {
            throw new RuntimeException(
                message: "No podés {$action} a vos mismo.",
            );
        }
    }

    /**
     * Helper: aborta si el target es el último admin del tenant.
     *
     * Cuenta cuántos users con rol.slug='admin' quedan VINCULADOS a
     * las tiendas del tenant. Si el target es admin y es el último,
     * bloquea el borrado (lockout del tenant).
     */
    private function assertNotLastAdmin(UserModel $target): void
    {
        $targetRole = $target->role;
        if ($targetRole === null || $targetRole->slug !== 'admin') {
            return;
        }

        $adminRoleId = Role::query()
            ->where('slug', 'admin')
            ->value('id');

        if ($adminRoleId === null) {
            // Sin rol admin en el catálogo → no podemos validar. Asumir ok.
            return;
        }

        $tenantStoreIds = $this->accessibleStoreIds();

        $adminCount = UserModel::query()
            ->where('role_id', $adminRoleId)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if ($adminCount === []) {
            return; // ya no hay admins activos → no es nuestro problema
        }

        $adminsInTenant = DB::table('user_stores')
            ->whereIn('store_id', $tenantStoreIds)
            ->whereIn('user_id', $adminCount)
            ->distinct('user_id')
            ->count('user_id');

        if ($adminsInTenant <= 1) {
            throw new RuntimeException(
                message: 'No podés borrar al ÚNICO admin del tenant.',
            );
        }
    }

    /**
     * Helper: valida que todos los store_ids pertenezcan a las tiendas
     * accesibles por el admin que ejecuta la acción.
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
                message: 'No podés asignar usuarios a tiendas que no administrás: '.implode(', ', $invalid),
            );
        }
    }
}
