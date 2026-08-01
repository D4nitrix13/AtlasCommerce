<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\RecoveryCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar códigos de recuperación de 2FA.
 *
 * Permite consultar el estado de los códigos (total, usados, restantes)
 * y regenerar un nuevo lote de códigos.
 *
 * Los códigos de recuperación se usan cuando el usuario no tiene acceso
 * a su método de 2FA principal (por ejemplo, perdió acceso al correo).
 */
class RecoveryCodeController extends Controller
{
    /**
     * Muestra el resumen de códigos de recuperación del usuario.
     *
     * Retorna el total de códigos, cuántos han sido usados y cuántos
     * quedan disponibles.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $total = $user->recoveryCodes()->count();

        $used = $user->recoveryCodes()
            ->whereNotNull('used_at')
            ->count();

        return response()->json([
            'total' => $total,
            'used' => $used,
            'remaining' => $total - $used,
        ], status: 200);
    }

    /**
     * Regenera un nuevo lote de códigos de recuperación.
     *
     * Elimina todos los códigos anteriores del usuario y genera un nuevo
     * lote con un UUID de lote nuevo. Retorna los códigos en texto plano
     * para que el usuario los guarde de forma segura.
     */
    public function regenerate(Request $request, RecoveryCodeService $recoveryCodeService): JsonResponse
    {
        $codes = $recoveryCodeService->regenerate(user: $request->user());

        return response()->json([
            'message' => 'Recovery codes regenerated successfully.',
            'codes' => $codes,
        ], status: 200);
    }
}
