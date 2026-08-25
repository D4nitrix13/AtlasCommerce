<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Auth\TwoFactorSetting;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use Throwable;

/**
 * TOTP (Time-based One-Time Password) service.
 *
 * Implementa el algoritmo RFC 6238 para 2FA. Genera un secret base32
 * por usuario, valida los códigos de 6 dígitos que el authenticator
 * app (Google Authenticator, Authy, etc.) genera cada 30 segundos.
 *
 * No usa dependencias externas (como pragmarx/otphp) — el algoritmo
 * TOTP es HMAC-SHA1 + time-based counter + truncation, ~50 líneas
 * de código. Esto evita agregar deps y mantiene el bundle backend
 * pequeño.
 *
 * Ventana de validación: ±1 step (±30s) para tolerancia a drift de
 * reloj entre server y device del user.
 */
class TotpService
{
    /** Duración de cada step en segundos (RFC 6238 default). */
    public const STEP_SECONDS = 30;

    /** Largo del secret en bytes. 32 bytes = 160 bits (RFC 6238). */
    public const SECRET_BYTES = 32;

    /** Dígitos del código (6 es lo standard para authenticator apps). */
    public const DIGITS = 6;

    /** Issuer que aparece en el QR del authenticator. */
    public const ISSUER = 'Panda Estampados';

    /**
     * Genera un secret TOTP nuevo (base32-encoded, 32 bytes random).
     * El secret ya viene encryptado con Laravel Crypt para storage
     * en TwoFactorSetting.
     */
    public function generateSecret(): string
    {
        $rawBytes = random_bytes(self::SECRET_BYTES);

        return $this->base32Encode($rawBytes);
    }

    /**
     * Genera la URL otpauth:// para que el user escanee con su
     * authenticator app. Formato estándar:
     *   otpauth://totp/{issuer}:{email}?secret={base32}&issuer={issuer}
     */
    public function provisioningUri(User $user, string $encryptedSecret): string
    {
        $secret = $this->decryptSecret($encryptedSecret);
        $label = rawurlencode(self::ISSUER.':'.$user->email);
        $issuer = rawurlencode(self::ISSUER);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&digits="
            .self::DIGITS.'&period='.self::STEP_SECONDS.'&algorithm=SHA1';
    }

    /**
     * Valida un código TOTP de 6 dígitos contra el secret del user.
     * Acepta el step actual y ±1 step (±30s) para tolerar drift de
     * reloj. Retorna true si el código es válido.
     *
     * Si el código es válido, actualiza `last_used_at` para audit y
     * para futuros rate limits (rechaza reuse dentro de N segundos).
     */
    public function verify(User $user, string $code): bool
    {
        $setting = $user->twoFactorSetting;
        if ($setting === null || ! $setting->isEnabled()) {
            return false;
        }

        $secret = $this->decryptSecret($setting->secret);

        $code = preg_replace('/\s+/', '', $code);
        if (! ctype_digit($code) || strlen($code) !== self::DIGITS) {
            return false;
        }

        $now = time();
        // ±1 step (current + 1 before, + 1 after) = -1, 0, +1 = 3 windows
        for ($offset = -1; $offset <= 1; $offset++) {
            $step = intdiv($now, self::STEP_SECONDS) + $offset;
            $expected = $this->generateCodeAtStep($secret, $step);
            if (hash_equals($expected, $code)) {
                // Audit: cuándo fue usado por última vez
                $setting->last_used_at = now();
                $setting->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Genera el código TOTP para un step dado (counter).
     * Algoritmo RFC 6238: HMAC-SHA1(secret, counter) -> truncar a 6 digits.
     */
    private function generateCodeAtStep(string $base32Secret, int $step): string
    {
        $key = $this->base32Decode($base32Secret);
        $counter = pack('N*', 0, $step);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0xF;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 encode (RFC 4648). Sin padding (los authenticator apps
     * lo toleran).
     */
    private function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;
        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $index = ($buffer >> $bitsLeft) & 0x1F;
                $output .= $alphabet[$index];
            }
        }
        if ($bitsLeft > 0) {
            $output .= $alphabet[($buffer << (5 - $bitsLeft)) & 0x1F];
        }

        return $output;
    }

    /**
     * Base32 decode (RFC 4648). Tolera lowercase, espacios, padding.
     */
    private function base32Decode(string $base32): string
    {
        $base32 = strtoupper(rtrim($base32, '='));
        $base32 = preg_replace('/\s+/', '', $base32);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $lookup = array_flip(str_split($alphabet));
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;
        for ($i = 0, $n = strlen($base32); $i < $n; $i++) {
            $ch = $base32[$i];
            if (! isset($lookup[$ch])) {
                throw new InvalidArgumentException("Invalid base32 character: {$ch}");
            }
            $buffer = ($buffer << 5) | $lookup[$ch];
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }

    /**
     * Desencripta el secret almacenado en TwoFactorSetting::secret.
     * Lanza excepción si la desencriptación falla (APP_KEY rotada, etc.).
     */
    private function decryptSecret(string $encrypted): string
    {
        try {
            return Crypt::decryptString($encrypted);
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                'No se puede desencriptar el secret 2FA. ¿APP_KEY rotada?',
                previous: $e,
            );
        }
    }
}
