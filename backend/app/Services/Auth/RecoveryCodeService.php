<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Auth\RecoveryCode;
use App\Models\Auth\User;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de recovery codes de un solo uso.
 *
 * Genera códigos alfanuméricos de 10 chars, hashea con bcrypt y
 * los guarda. El user los usa UNA vez cada uno (ej. para reset de
 * password si no tiene acceso a su authenticator app o al email).
 *
 * Al regenerar (después de consumir todos o por seguridad), se
 * invalidan los anteriores (cascade via delete + insert atómico).
 *
 * Configuración:
 *  - default 10 codes por user (suficiente para la mayoría; 1 por mes
 *    cubre un año de recuperaciones).
 *  - longitud 10 chars: alfanumérico sin ambigüedades (sin 0/O, 1/I/L).
 */
class RecoveryCodeService
{
    /** Cantidad de codes que se generan por default. */
    public const DEFAULT_COUNT = 10;

    /** Largo de cada code (10 chars alfanuméricos sin ambigüedades). */
    public const CODE_LENGTH = 10;

    /**
     * Genera un batch de recovery codes para un usuario.
     * Borra los codes anteriores y crea N nuevos en una transacción.
     * Retorna los codes EN TEXTO PLANO (solo se muestran al user una
     * vez, en el setup).
     *
     * @return array<int, string> Lista de codes plain-text.
     */
    public function generateFor(User $user, int $count = self::DEFAULT_COUNT): array
    {
        if ($count < 1 || $count > 50) {
            throw new \InvalidArgumentException(
                "count debe estar entre 1 y 50, recibido: {$count}",
            );
        }

        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->generatePlainCode();
        }

        DB::transaction(function () use ($user, $codes): void {
            $user->recoveryCodes()->delete();
            foreach ($codes as $code) {
                $user->recoveryCodes()->create([
                    'code_hash' => bcrypt($code),
                ]);
            }
        });

        return $codes;
    }

    /**
     * Verifica un code y lo marca como usado si es válido.
     * Retorna true si el code es válido (y por lo tanto consumido).
     */
    public function verifyAndConsume(User $user, string $code): bool
    {
        $code = $this->normalize($code);
        if ($code === '') {
            return false;
        }

        $match = $user->recoveryCodes()
            ->whereNull(column: 'used_at')
            ->get()
            ->first(fn (RecoveryCode $row): bool => password_verify($code, $row->code_hash));

        if ($match === null) {
            return false;
        }

        $match->used_at = now();
        $match->save();
        return true;
    }

    /**
     * Cuenta cuántos recovery codes disponibles (no usados) tiene el user.
     */
    public function availableCount(User $user): int
    {
        return $user->recoveryCodes()
            ->whereNull(column: 'used_at')
            ->count();
    }

    /**
     * Genera un code alfanumérico de 10 chars sin ambigüedades.
     *
     * Caracteres excluidos: 0/O, 1/I/L para evitar errores de transcripción
     * manual. El set es: ABCDEFGHJKMNPQRSTUVWXYZ23456789 (30 chars, sin
     * 0, 1, I, L, O).
     */
    private function generatePlainCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }
        return $code;
    }

    /**
     * Normaliza el input del user: uppercase, trim, sin espacios.
     */
    private function normalize(string $code): string
    {
        return strtoupper(trim(preg_replace('/\s+/', '', $code) ?? ''));
    }
}
