<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\AccountDeletionConfirmRequest;
use App\Http\Requests\Customers\AccountDeletionRequestRequest;
use App\Models\Auth\User as UserModel;
use App\Models\Customers\Customer;
use App\Services\Customers\CustomerDataExportService;
use App\Services\Customers\CustomerDeletionService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Endpoints GDPR para la cuenta del cliente.
 *
 * RF-CUST-042 — Exportar y borrar datos del cliente (derechos de
 * acceso y olvido, GDPR art. 15/17 + Ley 896 Nicaragua).
 *
 * Rutas:
 *   - GET    /api/account/data-export     → payload con toda la PII
 *   - POST   /api/account/deletion-request → inicia flujo, devuelve token
 *   - DELETE /api/account                 → confirma borrado con token + password
 *   - POST   /api/account/deletion-cancel → cancela solicitud pendiente
 *
 * Multi-tenancy:
 *   Las rutas están envueltas en `store.domain` middleware, por lo que
 *   la tienda activa está resuelta antes de llegar acá. El cliente se
 *   resuelve desde el User autenticado (uno a uno opcional).
 *
 * Si el User autenticado no tiene Customer vinculado (ej. un admin que
 * nunca compró), los endpoints devuelven 404. Esto NO es un leak: el
 * admin sigue siendo admin y tiene su propia gestión de cuenta en
 *   /auth/*.
 */
class AccountDataController extends Controller
{
    public function __construct(
        private readonly CustomerDataExportService $exporter,
        private readonly CustomerDeletionService $deletion,
    ) {}

    /**
     * GET /api/account/data-export
     *
     * Devuelve TODA la información personal asociada al cliente
     * autenticado, en un payload JSON estable.
     */
    public function export(): JsonResponse
    {
        $customer = $this->resolveCustomer();

        return response()->json(data: $this->exporter->export(customer: $customer));
    }

    /**
     * POST /api/account/deletion-request
     *
     * Inicia el flujo de borrado. Devuelve el token de un solo uso
     * en claro (es la única vez que se ve). En producción este token
     * se debería enviar por email; acá se devuelve en el response
     * para que el cliente pueda continuar el flujo sin fricción.
     */
    public function requestDeletion(
        AccountDeletionRequestRequest $request,
    ): JsonResponse {
        $customer = $this->resolveCustomer();
        $token = $this->deletion->requestDeletion(customer: $customer);

        return response()->json(data: [
            'message' => 'Solicitud registrada. Confirmá el borrado enviando este token a DELETE /api/account junto con tu contraseña.',
            'token' => $token,
            'expires_at' => $customer->fresh()?->deletion_token_expires_at?->toIso8601String(),
        ], status: 202);
    }

    /**
     * DELETE /api/account
     *
     * Confirma el borrado. Verifica token + re-auth con password,
     * luego anonimiza + soft-delete del Customer y del User vinculado.
     */
    public function destroy(
        AccountDeletionConfirmRequest $request,
    ): JsonResponse {
        $customer = $this->resolveCustomer();
        $token = (string) $request->validated(key: 'token');
        $password = (string) $request->validated(key: 'password');

        try {
            $this->deletion->confirmDeletion(
                customer: $customer,
                plainToken: $token,
                password: $password,
            );
        } catch (RuntimeException $exception) {
            return response()->json(data: [
                'message' => $exception->getMessage(),
            ], status: 422);
        }

        // Tras la anonimización el Customer queda soft-deleted, por
        // lo que un `fresh()` lo ocultaría. Leemos con `withTrashed()`
        // para poder devolver el timestamp real al cliente.
        $anonymizedAt = Customer::withTrashed()
            ->find(id: $customer->id)
            ?->anonymized_at
            ?->toIso8601String();

        return response()->json(data: [
            'message' => 'Tu cuenta fue anonimizada y marcada para borrado. Los datos de facturación/fiscales se conservan por ley.',
            'anonymized_at' => $anonymizedAt,
        ], status: 200);
    }

    /**
     * POST /api/account/deletion-cancel
     *
     * Cancela una solicitud pendiente (token aún no usado).
     * Útil si el cliente se arrepiente.
     */
    public function cancelDeletion(): JsonResponse
    {
        $customer = $this->resolveCustomer();
        $this->deletion->cancelDeletion(customer: $customer);

        return response()->json(data: [
            'message' => 'Solicitud de borrado cancelada.',
        ]);
    }

    /**
     * Resuelve el Customer vinculado al User autenticado.
     */
    private function resolveCustomer(): Customer
    {
        $user = auth()->user();

        if (! $user instanceof UserModel) {
            abort(code: 401, message: 'No autenticado.');
        }

        $customer = Customer::query()
            ->where(column: 'user_id', operator: '=', value: $user->id)
            ->first();

        if ($customer === null) {
            abort(code: 404, message: 'No hay cuenta de cliente asociada a este usuario.');
        }

        return $customer;
    }
}
