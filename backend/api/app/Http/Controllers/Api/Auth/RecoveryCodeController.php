<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\RecoveryCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecoveryCodeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $total = $user->recoveryCodes()->count();

        $used = $user->recoveryCodes()
            ->where('used', true)
            ->count();

        return response()->json([
            'total' => $total,
            'used' => $used,
            'remaining' => $total - $used,
        ]);
    }

    public function regenerate(Request $request, RecoveryCodeService $recoveryCodeService): JsonResponse
    {
        $codes = $recoveryCodeService->regenerate($request->user());

        return response()->json([
            'message' => 'Recovery codes regenerated successfully.',
            'codes' => $codes,
        ]);
    }
}
