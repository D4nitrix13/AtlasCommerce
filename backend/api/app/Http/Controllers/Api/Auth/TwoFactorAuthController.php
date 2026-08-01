<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\TwoFactorMethod;
use App\Models\Auth\TwoFactorSetting;
use App\Services\Auth\EmailOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorAuthController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $setting = $user->twoFactorSetting()->first();

        $methods = $user->twoFactorMethods()
            ->select([
                'id',
                'method',
                'display_name',
                'destination',
                'active',
                'verified',
                'verified_at',
                'created_at',
            ])
            ->orderBy('method')
            ->get();

        return response()->json([
            'enabled' => (bool) $setting?->enabled,
            'preferred_method' => $setting?->preferred_method,
            'required' => (bool) $setting?->required,
            'enabled_at' => $setting?->enabled_at,
            'methods' => $methods,
        ]);
    }

    public function sendEmailOtp(Request $request, EmailOtpService $emailOtpService): JsonResponse
    {
        $user = $request->user();

        $challenge = $emailOtpService->send(
            user: $user,
            purpose: 'enable_2fa',
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'message' => 'Security code sent.',
            'expires_at' => $challenge->expires_at,
            'destination' => $challenge->destination,
        ]);
    }

    public function verifyEmailOtp(Request $request, EmailOtpService $emailOtpService): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        $user = $request->user();

        $verified = $emailOtpService->verify(
            user: $user,
            code: $validated['code'],
            purpose: 'enable_2fa',
        );

        if (! $verified) {
            return response()->json([
                'message' => 'Invalid or expired security code.',
            ], 422);
        }

        TwoFactorSetting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'enabled' => true,
                'preferred_method' => 'email_otp',
                'enabled_at' => now(),
            ],
        );

        TwoFactorMethod::updateOrCreate(
            [
                'user_id' => $user->id,
                'method' => 'email_otp',
            ],
            [
                'display_name' => 'Main email',
                'destination' => $user->email,
                'active' => true,
                'verified' => true,
                'verified_at' => now(),
            ],
        );

        return response()->json([
            'message' => 'Email OTP enabled successfully.',
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->twoFactorSetting()->update([
            'enabled' => false,
            'preferred_method' => null,
            'enabled_at' => null,
        ]);

        $user->twoFactorMethods()->update([
            'active' => false,
        ]);

        return response()->json([
            'message' => 'Two-factor authentication disabled successfully.',
        ]);
    }
}
