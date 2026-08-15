<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\Auth\User;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        // Si el user tiene 2FA habilitada, NO emitimos token todavía.
        // Devolvemos un 200 con 'status: 2fa_required' y un challenge
        // token que el frontend envía en /auth/2fa/verify-totp-login
        // (endpoint que se implementa en un commit posterior). Esto
        // evita emitir tokens si el user no completa 2FA.
        if ($user->hasTwoFactorEnabled()) {
            $challenge = \App\Models\Auth\OtpChallenge::create([
                'user_id' => $user->id,
                'type' => \App\Models\Auth\OtpChallenge::TYPE_TOTP,
                // bin2hex(random_bytes(...)) garantiza string sin null bytes
                // (PHP 8+ rechaza null chars en password_hash).
                'code_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                'context' => ['action' => 'login_2fa', 'device_name' => $deviceName],
                'expires_at' => now()->addMinutes(value: 5),
            ]);
            return response()->json(data: [
                'status' => '2fa_required',
                'challenge_token' => $challenge->id,
                'method' => 'totp',
                'expires_at' => $challenge->expires_at,
            ]);
        }

        $token = $user->createToken(name: $deviceName);

        return response()->json(data: [
            'token' => $token->plainTextToken,
            'user' => new UserResource(
                resource: $user->load(['role', 'stores']),
            ),
        ]);
    }

    /**
     * Verifica el TOTP code durante el login cuando el user tiene 2FA.
     * Valida el challenge token y el code, emite el Sanctum token.
     */
    public function verifyTotpLogin(Request $request): JsonResponse
    {
        $request->validate(rules: [
            'challenge_token' => ['required', 'integer'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $challenge = \App\Models\Auth\OtpChallenge::query()
            ->where(column: 'id', operator: '=', value: $request->integer(key: 'challenge_token'))
            ->where(column: 'type', operator: '=', value: \App\Models\Auth\OtpChallenge::TYPE_TOTP)
            ->whereNull(columns: 'consumed_at')
            ->first();

        if ($challenge === null || ! $challenge->isUsable()) {
            return response()->json(data: ['message' => 'Challenge inválido o expirado.'], status: 422);
        }

        $user = $challenge->user;
        $totp = app(\App\Services\Auth\TotpService::class);
        if (! $totp->verify($user, $request->input(key: 'code'))) {
            $challenge->attempts++;
            $challenge->save();
            return response()->json(data: ['message' => 'Código inválido.'], status: 422);
        }

        // Challenge consumido
        $challenge->consumed_at = now();
        $challenge->save();

        $deviceName = data_get($challenge->context, 'device_name', 'web-admin') ?? 'web-admin';
        $token = $user->createToken(name: $deviceName);

        return response()->json(data: [
            'token' => $token->plainTextToken,
            'user' => new UserResource(
                resource: $user->load(['role', 'stores']),
            ),
        ]);
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
