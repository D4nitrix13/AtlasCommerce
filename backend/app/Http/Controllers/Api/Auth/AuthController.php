<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\Auth\User;
use App\Services\Stores\StoreContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Endpoints de autenticación API (Sanctum tokens).
 *
 * - POST /auth/login   → crea token + devuelve user resource
 * - POST /auth/logout  → revoca el token actual
 * - GET  /auth/me      → devuelve el user autenticado (valida sesión)
 *
 * Multi-tenancy: las rutas auth están envueltas en `store.domain`
 * middleware (ver `routes/api.php`), por lo que `StoreContextService`
 * siempre tiene la tienda activa resuelta en login/register/me/logout.
 * El login valida que el usuario esté asociado a la tienda activa
 * via tabla pivot `user_stores`.
 */
class AuthController extends Controller
{
    /**
     * Autentica al usuario y crea un Sanctum token.
     *
     * Validaciones:
     *  - Email + password correctos.
     *  - Cuenta activa (`is_active = true`).
     *  - Usuario asociado a la tienda activa via `user_stores`.
     *
     * Body:
     *   - email (required)
     *   - password (required)
     *   - device_name (optional) — etiqueta para identificar el token
     */
    public function login(LoginRequest $request, StoreContextService $storeContext): JsonResponse
    {
        $credentials = $request->validated();

        /** @var User|null $user */
        $user = User::query()
            ->where(column: 'email', operator: '=', value: $credentials['email'])
            ->first();

        if ($user === null || ! Hash::check(value: $credentials['password'], hashedValue: $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son válidas.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['La cuenta está desactivada.'],
            ]);
        }

        $currentStoreId = $storeContext->getStoreId();

        if ($currentStoreId !== null && ! $user->hasAccessToStore(storeId: $currentStoreId)) {
            throw ValidationException::withMessages([
                'email' => ['No tienes acceso a esta tienda.'],
            ]);
        }

        $deviceName = $credentials['device_name'] ?? 'web-admin';

        $token = $user->createToken(name: $deviceName);

        return response()->json(data: [
            'token' => $token->plainTextToken,
            'user' => new UserResource(
                resource: $user->load(['role', 'stores']),
            ),
        ]);
    }

    /**
     * Registra un nuevo usuario y le devuelve un token Sanctum.
     *
     * Body:
     *   - first_name (required)
     *   - last_name  (required)
     *   - email      (required, unique en users)
     *   - password   (required, min 8)
     *   - device_name (optional)
     *
     * El user se crea con:
     *   - is_active = true (puede loguearse inmediatamente)
     *   - role_id   = null (sin permisos hasta que admin le asigne uno)
     *   - Se asocia automaticamente a la tienda activa via `user_stores`.
     *
     * Response 201 con { token, user } igual que login para que el
     * frontend pueda usar el mismo flujo post-auth.
     */
    public function register(RegisterRequest $request, StoreContextService $storeContext): JsonResponse
    {
        $data = $request->validated();
        $currentStoreId = $storeContext->getStoreId();

        $user = DB::transaction(function () use ($data, $currentStoreId): User {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
                'role_id' => null,
            ]);

            if ($currentStoreId !== null) {
                $user->stores()->attach($currentStoreId);
            }

            return $user;
        });

        $deviceName = $data['device_name'] ?? 'web-admin';

        $token = $user->createToken(name: $deviceName);

        return response()->json(
            data: [
                'token' => $token->plainTextToken,
                'user' => new UserResource(
                    resource: $user->load(['role', 'stores']),
                ),
            ],
            status: 201,
        );
    }

    /**
     * Revoca el token Sanctum usado en la request actual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(data: ['message' => 'Sesión cerrada.']);
    }

    /**
     * Devuelve el user autenticado. Útil para que el frontend
     * valide que el token guardado sigue siendo válido al recargar.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(data: ['message' => 'No autenticado.'], status: 401);
        }

        return response()->json(data: [
            'user' => new UserResource(
                resource: $user->load(['role', 'stores']),
            ),
        ]);
    }
}
