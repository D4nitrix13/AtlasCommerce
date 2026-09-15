<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\Admin\CustomerStoreRequest;
use App\Http\Requests\Customers\Admin\CustomerUpdateRequest;
use App\Http\Resources\Customers\AdminCustomerResource;
use App\Models\Auth\User as UserModel;
use App\Models\Customers\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de clientes (RF-CUST-040).
 *
 * Reglas:
 *  - Listado / show requieren `customers.view`.
 *  - Store / update / suspend / reactivate / destroy requieren `customers.write`.
 *  - Aislamiento por tenant: el listado y las búsquedas sólo muestran
 *    clientes afiliados a alguna de las tiendas accesibles del admin
 *    (`stores()` via pivot `customer_stores`).
 *  - Soft-delete: el `destroy` aplica `delete()` (SoftDeletes trait).
 *  - NO toca flujos de anonimización / GDPR: eso vive en RF-CUST-042
 *    (`AccountDataController` + `CustomerDeletionService`). El admin
 *    aquí sólo gestiona el perfil y la membresía.
 */
class CustomerController extends Controller
{
    /**
     * GET /admin/customers
     *
     * Query params:
     *  - page, per_page (default 20, max 100).
     *  - q: filtro ILIKE por email, first_name, last_name, identity_document, phone.
     *  - customer_type: retail | wholesale | vip.
     *  - is_active: bool.
     *  - store_id: filtro por tienda (debe estar en las tiendas del admin).
     *  - with_trashed: 1 = incluye soft-deleted.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $accessibleStoreIds = $this->accessibleStoreIds();

        $query = Customer::query()
            ->whereHas('stores', function ($q) use ($accessibleStoreIds): void {
                $q->whereIn('stores.id', $accessibleStoreIds);
            });

        if ($search = trim((string) $request->query('q', ''))) {
            $needle = '%'.$search.'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('email', 'ILIKE', $needle)
                    ->orWhere('first_name', 'ILIKE', $needle)
                    ->orWhere('last_name', 'ILIKE', $needle)
                    ->orWhere('identity_document', 'ILIKE', $needle)
                    ->orWhere('phone', 'ILIKE', $needle);
            });
        }

        if ($request->has('customer_type')) {
            $query->where('customer_type', $request->query('customer_type'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
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

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $paginator = $query
            ->orderBy('email')
            ->paginate(perPage: $perPage);

        return response()->json(data: [
            'data' => AdminCustomerResource::collection(resource: $paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /admin/customers/{customer}
     *
     * Devuelve el detalle con sus tiendas (tenant) y el historial de
     * órdenes para calcular `order_count` + `lifetime_value_cents`.
     */
    public function show(Customer $customer): JsonResponse
    {
        $this->assertCustomerInTenantScope(customer: $customer);

        $customer->load(relations: ['stores', 'orders']);

        return response()->json(data: [
            'data' => (new AdminCustomerResource(resource: $customer))->resolve(),
        ]);
    }

    /**
     * POST /admin/customers
     *
     * Crea un cliente manualmente. El cliente se afilia a una o más
     * tiendas accesibles del admin (vía pivot `customer_stores`).
     */
    public function store(CustomerStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $storeIds = $data['store_ids'] ?? [];

        $this->assertStoresAccessible(storeIds: $storeIds);

        $customer = DB::transaction(function () use ($data, $storeIds): Customer {
            $customer = Customer::query()->create(attributes: [
                'user_id' => $data['user_id'] ?? null,
                'customer_type' => $data['customer_type'],
                'identity_document' => $data['identity_document'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'registered_at' => now(),
            ]);

            $customer->stores()->sync(ids: $storeIds);

            return $customer;
        });

        $customer->load(relations: ['stores', 'orders']);

        return response()->json(data: [
            'data' => (new AdminCustomerResource(resource: $customer))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/customers/{customer}
     */
    public function update(CustomerUpdateRequest $request, Customer $customer): JsonResponse
    {
        $this->assertCustomerInTenantScope(customer: $customer);

        $data = $request->validated();

        $customer = DB::transaction(function () use ($customer, $data): Customer {
            $customer->update(attributes: $data);

            return $customer;
        });

        $customer->load(relations: ['stores', 'orders']);

        return response()->json(data: [
            'data' => (new AdminCustomerResource(resource: $customer))->resolve(),
        ]);
    }

    /**
     * POST /admin/customers/{customer}/suspend
     */
    public function suspend(Customer $customer): JsonResponse
    {
        $this->assertCustomerInTenantScope(customer: $customer);

        $customer->update(attributes: ['is_active' => false]);
        $customer->load(relations: ['stores', 'orders']);

        return response()->json(data: [
            'data' => (new AdminCustomerResource(resource: $customer))->resolve(),
        ]);
    }

    /**
     * POST /admin/customers/{customer}/reactivate
     */
    public function reactivate(Customer $customer): JsonResponse
    {
        $this->assertCustomerInTenantScope(customer: $customer);

        $customer->update(attributes: ['is_active' => true]);
        $customer->load(relations: ['stores', 'orders']);

        return response()->json(data: [
            'data' => (new AdminCustomerResource(resource: $customer))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/customers/{customer}
     *
     * Soft-delete: preserva auditoría (orders, invoices, etc).
     * NO anonimiza — eso lo maneja RF-CUST-042 con un flujo explícito.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $this->assertCustomerInTenantScope(customer: $customer);

        $customer->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Helper: IDs de tiendas accesibles por el admin actual.
     *
     * Reutiliza `auth()->user()->stores()` — mismo criterio que el
     * resto del panel admin (UserController, RoleController).
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
    private function assertCustomerInTenantScope(Customer $customer): void
    {
        $accessible = $this->accessibleStoreIds();

        $shared = $customer->stores()
            ->whereIn('stores.id', $accessible)
            ->exists();

        if (! $shared) {
            abort(code: 404, message: 'Cliente no encontrado en tu tenant.');
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
                message: 'No podés asociar clientes a tiendas que no administrás: '.implode(', ', $invalid),
            );
        }
    }
}
