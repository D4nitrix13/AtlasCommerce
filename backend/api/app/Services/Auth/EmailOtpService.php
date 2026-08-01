<?php

namespace App\Services\Auth;

use App\Models\Auth\OtpChallenge;
use App\Models\Auth\User;
use App\Notifications\Auth\EmailOtpNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmailOtpService
{
    public function send(
        User $user,
        string $purpose = 'enable_2fa',
        ?string $ip = null,
        ?string $userAgent = null,
    ): OtpChallenge {
        $digits = (int) config('two_factor.email_otp.digits', 6);
        $expiresMinutes = (int) config('two_factor.email_otp.expires_minutes', 10);
        $maxAttempts = (int) config('two_factor.email_otp.max_attempts', 5);

        $code = $this->generateNumericCode($digits);

        $challenge = OtpChallenge::create([
            'user_id' => $user->id,
            'method' => 'email_otp',
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'destination' => $user->email,
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'used' => false,
            'expires_at' => now()->addMinutes($expiresMinutes),
            'request_ip' => $ip,
            'user_agent' => $userAgent,
        ]);

        $user->notify(new EmailOtpNotification($code, $expiresMinutes));

        return $challenge;
    }

    public function verify(User $user, string $code, string $purpose = 'enable_2fa'): bool
    {
        $challenge = OtpChallenge::query()
            ->where('user_id', $user->id)
            ->where('method', 'email_otp')
            ->where('purpose', $purpose)
            ->where('used', false)
            ->latest()
            ->first();

        if (! $challenge) {
            return false;
        }

        if ($challenge->isExpired() || ! $challenge->hasAttemptsAvailable()) {
            return false;
        }

        $challenge->increment('attempts');

        if (! Hash::check($code, $challenge->code_hash)) {
            return false;
        }

        $challenge->update([
            'used' => true,
            'used_at' => now(),
        ]);

        return true;
    }

    private function generateNumericCode(int $digits): string
    {
        $min = 10 ** ($digits - 1);
        $max = (10 ** $digits) - 1;

        return (string) random_int($min, $max);
    }
}
