<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use App\Services\Auth\RecoveryCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de recovery codes.
 *
 * Flujo:
 *   1. GET /auth/recovery-codes — devuelve la cantidad disponible
 *      (NO devuelve los codes por seguridad; solo el count).
 *   2. POST /auth/recovery-codes/regenerate — borra los actuales y
 *      crea N nuevos. Retorna los codes EN TEXTO PLANO (solo se
 *      muestran al user UNA vez, en el setup).
 *
 * Si el user tiene 2FA habilitada, regenerar implica que los
 * codes anteriores quedan invalidados (rotación de seguridad).
 * Si NO tiene 2FA y su método preferido es 'recovery_codes',
 * regenerar crea los codes sin habilitar 2FA.
 */
class RecoveryCodeController extends Controller
{
    public function __construct(
        private readonly RecoveryCodeService $service,
    ) {}

    /**
     * Devuelve cuántos recovery codes disponibles tiene el user.
     * NO devuelve los codes en sí (eso sería un riesgo de seguridad).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json(data: [
            'unused_count' => $this->service->availableCount($user),
        ], status: 200);
    }

    /**
     * Regenera los codes: borra los anteriores y crea N nuevos.
     * Retorna los codes en texto plano UNA vez.
     */
    public function regenerate(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate(rules: [
            'count' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $count = (int) $request->input(key: 'count', default: RecoveryCodeService::DEFAULT_COUNT);
        $codes = $this->service->generateFor($user, count: $count);

        return response()->json(data: [
            'message' => 'Códigos regenerados. Los anteriores quedaron invalidados.',
            'codes' => $codes,
            'message_warning' => 'Guarda estos códigos en un lugar seguro. Son la única forma de recuperar tu cuenta si pierdes acceso.',
        ], status: 200);
    }
}
