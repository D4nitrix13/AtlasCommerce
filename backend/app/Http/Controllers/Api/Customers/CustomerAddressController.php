<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\CustomerAddressStoreRequest;
use App\Http\Requests\Customers\CustomerAddressUpdateRequest;
use App\Http\Resources\Customers\CustomerAddressResource;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD del libro de direcciones del cliente autenticado (RF-CUST-041).
 *
 * Rutas:
 *   - GET    /api/account/addresses
 *   - POST   /api/account/addresses
 *   - GET    /api/account/addresses/{address}
 *   - PUT    /api/account/addresses/{address}
 *   - DELETE /api/account/addresses/{address}
 *   - POST   /api/account/addresses/{address}/primary
 *
 * Reglas:
 *  - El cliente sólo puede ver/editar sus propias direcciones.
 *  - Multi-tenant: las rutas están envueltas en `store.domain` — la
 *    tienda activa se valida vía middleware. No hay scope de tenant
 *    adicional acá porque las direcciones pertenecen al Customer (que
 *    es cross-tenant).
 *  - `is_primary` se gestiona de forma exclusiva: marcar una dirección
 *    como principal desmarca las demás del mismo cliente (transaccional).
 */
class CustomerAddressController extends Controller
{
    /**
     * GET /api/account/addresses
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $addresses = $customer->addresses()
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();

        return response()->json(data: [
            'data' => CustomerAddressResource::collection(resource: $addresses)->resolve(),
        ]);
    }

    /**
     * POST /api/account/addresses
     */
    public function store(CustomerAddressStoreRequest $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $data = $request->validated();

        $address = DB::transaction(function () use ($customer, $data): CustomerAddress {
            if (($data['is_primary'] ?? false) === true) {
                $customer->addresses()->update(['is_primary' => false]);
            }

            return $customer->addresses()->create([
                'address_type' => $data['address_type'],
                'country' => $data['country'],
                'department' => $data['department'] ?? null,
                'municipality' => $data['municipality'] ?? null,
                'detailed_address' => $data['detailed_address'],
                'reference' => $data['reference'] ?? null,
                'is_primary' => $data['is_primary'] ?? false,
            ]);
        });

        return response()->json(data: [
            'data' => (new CustomerAddressResource(resource: $address))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * GET /api/account/addresses/{address}
     */
    public function show(Request $request, CustomerAddress $address): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $this->assertOwnedBy(customer: $customer, address: $address);

        return response()->json(data: [
            'data' => (new CustomerAddressResource(resource: $address))->resolve(),
        ]);
    }

    /**
     * PUT /api/account/addresses/{address}
     */
    public function update(
        CustomerAddressUpdateRequest $request,
        CustomerAddress $address,
    ): JsonResponse {
        $customer = $this->resolveCustomer($request);

        $this->assertOwnedBy(customer: $customer, address: $address);

        $data = $request->validated();

        $address = DB::transaction(function () use ($customer, $address, $data): CustomerAddress {
            if (array_key_exists('is_primary', $data) && $data['is_primary'] === true) {
                $customer->addresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_primary' => false]);
            }

            $address->update($data);

            return $address->fresh();
        });

        return response()->json(data: [
            'data' => (new CustomerAddressResource(resource: $address))->resolve(),
        ]);
    }

    /**
     * DELETE /api/account/addresses/{address}
     *
     * Soft-delete. Si era la dirección principal, NO auto-asigna otra
     * — el cliente debe marcar explícitamente cuál queda como principal.
     */
    public function destroy(Request $request, CustomerAddress $address): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $this->assertOwnedBy(customer: $customer, address: $address);

        $address->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/account/addresses/{address}/primary
     *
     * Marca la dirección como principal y desmarca las demás del
     * mismo cliente.
     */
    public function setPrimary(Request $request, CustomerAddress $address): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $this->assertOwnedBy(customer: $customer, address: $address);

        $address = DB::transaction(function () use ($customer, $address): CustomerAddress {
            $customer->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_primary' => false]);

            $address->update(['is_primary' => true]);

            return $address->fresh();
        });

        return response()->json(data: [
            'data' => (new CustomerAddressResource(resource: $address))->resolve(),
        ]);
    }

    /**
     * Helper: resuelve el Customer asociado al User autenticado.
     */
    private function resolveCustomer(Request $request): Customer
    {
        $user = $request->user();
        abort_if($user === null, 401, 'Autenticación requerida.');

        $customer = Customer::query()->where('user_id', $user->id)->first();
        abort_if($customer === null, 404, 'No existe perfil de cliente para este usuario.');

        return $customer;
    }

    /**
     * Helper: aborta si la dirección no pertenece al cliente autenticado.
     * Devuelve 404 (no 403) para no leakear existencia de IDs ajenos.
     */
    private function assertOwnedBy(Customer $customer, CustomerAddress $address): void
    {
        abort_if(
            $address->customer_id !== $customer->id,
            404,
            'Dirección no encontrada.',
        );
    }
}
