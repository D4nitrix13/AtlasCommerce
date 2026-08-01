<?php

namespace App\Services\Auth;

use App\Models\Auth\RecoveryCode;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Hash;

class RecoveryCodeService
{
    public function regenerate(User $user): array
    {
        $count = (int) config('two_factor.recovery_codes.count', 8);

        RecoveryCode::query()
            ->where('user_id', $user->id)
            ->delete();

        $plainCodes = [];

        for ($index = 0; $index < $count; $index++) {
            $plainCode = $this->generateCode();

            RecoveryCode::create([
                'user_id' => $user->id,
                'code_hash' => Hash::make($plainCode),
                'used' => false,
            ]);

            $plainCodes[] = $plainCode;
        }

        return $plainCodes;
    }

    public function verify(User $user, string $submittedCode): bool
    {
        $codes = RecoveryCode::query()
            ->where('user_id', $user->id)
            ->where('used', false)
            ->get();

        foreach ($codes as $code) {
            if (! Hash::check($submittedCode, $code->code_hash)) {
                continue;
            }

            $code->update([
                'used' => true,
                'used_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    private function generateCode(): string
    {
        $prefix = config('two_factor.recovery_codes.prefix', 'APP');

        return sprintf(
            '%s-%s-%s',
            $prefix,
            $this->randomGroup(),
            $this->randomGroup(),
        );
    }

    private function randomGroup(int $length = 4): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $value = '';

        for ($index = 0; $index < $length; $index++) {
            $value .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $value;
    }
}
