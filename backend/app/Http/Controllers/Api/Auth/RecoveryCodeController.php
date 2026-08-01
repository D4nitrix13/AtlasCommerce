<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\RecoveryCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de códigos de recuperación para 2FA.
 *
 * Gestiona la consulta de estadísticas y la regeneración de recovery codes.
 *
 * Los recovery codes son códigos de respaldo de uso único que permiten
 * al usuario acceder a su cuenta cuando no tiene acceso a su método 2FA
 * principal (ej. perdió acceso al correo electrónico).
 *
 * Seguridad:
 * - Los códigos en texto plano solo se devuelven al momento de generarlos.
 * - La regeneración elimina todos los códigos anteriores.
 * - No se exponen los hashes en ninguna respuesta de la API.
 */
class RecoveryCodeController extends Controller
{
    /**
     * Muestra estadísticas de recovery codes del usuario.
     *
     * Endpoint: `GET /api/auth/2fa/recovery-codes`
     *
     * Retorna:
     * - `total`: cantidad total de recovery codes generados.
     * - `used`: cantidad de códigos ya utilizados.
     * - `remaining`: códigos disponibles para uso.
     *
     * No expone los códigos en texto plano ni sus hashes.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $total = $user->recoveryCodes()->count();

        $used = $user->recoveryCodes()
            ->whereNotNull('used_at')
            ->count();

        return response()->json(data: [
            'total' => $total,
            'used' => $used,
            'remaining' => $total - $used,
        ], status: 200);
    }

    /**
     * Regenera los recovery codes del usuario.
     *
     * Endpoint: `POST /api/auth/2fa/recovery-codes/regenerate`
     *
     * Flujo:
     * 1. Elimina todos los recovery codes anteriores.
     * 2. Genera nuevos códigos (default: 8) con formato PREFIX-XXXX-XXXX.
     * 3. Retorna los códigos en texto plano UNA SOLA VEZ.
     *
     * **El caller debe mostrar estos códigos al usuario y advertirle
     * que los guarde en un lugar seguro. No hay forma de recuperarlos
     * después de esta llamada.**
     */
    public function regenerate(Request $request, RecoveryCodeService $recoveryCodeService): JsonResponse
    {
        $codes = $recoveryCodeService->regenerate(user: $request->user());

        return response()->json(data: [
            'message' => 'Recovery codes regenerated successfully.',
            'codes' => $codes,
        ], status: 200);
    }
}
