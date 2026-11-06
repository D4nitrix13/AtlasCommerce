<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\Auth\RegisterRequest;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\Customers\CustomerResource;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Customers\Customer;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * CA.2 (customer-auth sprint, commit 2 de 5) — register de Customer
 * desde el storefront.
 *
 * Flujo:
 *  1. Crear User (role='customer', is_active=true) con el email +
 *     password del form.
 *  2. Crear Customer linked via customer.user_id = user.id. Se
 *     guardan first_name, last_name, phone, customer_type y
 *     registered_at.
 *  3. Vincular el Customer a la tienda activa (customer_stores
 *     pivot) para que el order by customer_id resuelva a este
 *     Customer cuando haga checkout.
 *  4. Emitir un Sanctum PAT (Bearer) para que el frontend lo
 *     guarde en su customer auth store y pueda llamar a /me.
 *  5. Devolver { token, user, customer } con el mismo shape que
 *     AuthController::login.
 *
 * Notas de seguridad:
 *  - Rate limiting via throttle:storefront.register (10/min por IP)
 *    aplicado a la ruta. Defense-in-depth contra account enumeration.
 *  - Password hashed con Laravel Hash facade (bcrypt, via User
 *    model cast 'hashed').
 *  - email único en users (validation rule).
 *  - Customer no tiene permisos administrativos attached (validado
 *    en PermissionsCatalogSeederTest).
 *  - Store activo: si la ruta resuelve una tienda inactiva o el
 *    StoreContext es null, el register falla con 503 (el form
 *    público no puede crear customers en una tienda offline).
 */
class StorefrontAuthController extends Controller
{
    /**
     * POST /api/v1/storefront/auth/register
     *
     * Body (RegisterRequest):
     *  - email (required, email rfc, max 255, unique users.email)
     *  - password (required, min 8)
     *  - password_confirmation (required, same:password)
     *  - first_name (required, string, 2-200)
     *  - last_name (required, string, 2-200)
     *  - phone (optional, string, max 50)
     *  - customer_type (optional, enum retail|wholesale|vip, default retail)
     *
     * Response 201: { token, user, customer }.
     * Response 422: validation errors (email duplicate, weak password, etc).
     * Response 503: no active store in context (defense: storefront no
     *   puede crear customers si la tienda está caída).
     */
    public function register(
        RegisterRequest $request,
        StoreContextService $storeContext,
    ): JsonResponse {
        $store = $storeContext->getStore();

        if ($store === null) {
            return response()->json(
                data: ['message' => 'No se puede registrar clientes: la tienda no está activa.'],
                status: 503,
            );
        }

        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated, $store) {
            $customerRoleId = Role::query()
                ->where(column: 'slug', operator: '=', value: 'customer')
                ->value(column: 'id');

            $user = User::query()->create(attributes: [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'email_verified_at' => now(),
                'password' => $validated['password'],
                'phone' => $validated['phone'] ?? null,
                'role_id' => $customerRoleId,
                'is_active' => true,
                'registered_at' => now(),
            ]);

            $customer = Customer::query()->create(attributes: [
                'user_id' => $user->id,
                'customer_type' => $validated['customer_type'] ?? 'retail',
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => true,
                'registered_at' => now(),
            ]);

            // Vincula el Customer a la tienda activa para que el checkout
            // y los orders-by-customer funcionen desde ya.
            $customer->stores()->attach($store->id);

            return $user->load(['role', 'stores']);
        });

        // Emitir un Sanctum PAT. El frontend lo guarda en
        // `stores/customer-auth.ts` (CA.4) y lo manda como Bearer en
        // /me. Mismo patrón que AuthController::login.
        $token = $user->createToken(name: 'storefront-register', expiresAt: now()->addDays(7));

        // Sanctum stateful (mismo patrón que AuthController::login):
        // además del PAT, iniciar la sesión web para que el browser
        // quede logueado via cookie httpOnly y /me responda sin
        // re-login. Guard con hasSession() porque los tests Bearer-only
        // corren sin sesión (driver=array, sin request stateful).
        if ($request->hasSession()) {
            auth('web')->login($user);
        }

        // Cargamos el customer para la response (relación inversa
        // user->customer no existe en el modelo, pero podemos
        // obtenerlo via el user_id).
        $customer = Customer::query()->where(column: 'user_id', operator: '=', value: $user->id)->firstOrFail();

        return response()->json(
            data: [
                'token' => $token->plainTextToken,
                'user' => new UserResource(resource: $user),
                'customer' => new CustomerResource(resource: $customer),
            ],
            status: 201,
        );
    }
}
