<?php

namespace App\Services\Auth;

use App\Models\Auth\RecoveryCode;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Servicio para gestionar códigos de recuperación de 2FA.
 *
 * Los códigos de recuperación permiten al usuario acceder a su cuenta
 * cuando no tiene acceso a su método de 2FA principal.
 *
 * Cada código se almacena hasheado con SHA-256. Se agrupan por lote
 * (batch_uuid) para facilitar la auditoría y regeneración.
 *
 * Formato del código: `PREFIX-XXXX-XXXX` donde PREFIX es configurable
 * (default: APP) y XXXX son grupos de 4 caracteres alfanuméricos.
 *
 * Configuración (desde config/two_factor.php):
 * - `count`: número de códigos por lote (default: 8).
 * - `prefix`: prefijo de los códigos (default: APP).
 */
class RecoveryCodeService
{
    /**
     * Regenera un nuevo lote de códigos de recuperación para el usuario.
     *
     * Elimina todos los códigos anteriores del usuario y genera un nuevo
     * lote con un UUID único. Retorna los códigos en texto plano para
     * que el usuario los guarde de forma segura.
     *
     * @param  User  $user  Usuario para el que se regeneran los códigos.
     * @return array<int, string> Lista de códigos en texto plano.
     */
    public function regenerate(User $user): array
    {
        $count = (int) config('two_factor.recovery_codes.count', default: 8);

        RecoveryCode::query()
            ->where('user_id', $user->id)
            ->delete();

        $plainCodes = [];
        $batchUuid = Str::uuid();

        for ($index = 0; $index < $count; $index++) {
            $plainCode = $this->generateCode();

            RecoveryCode::create([
                'user_id' => $user->id,
                'batch_uuid' => $batchUuid,
                'code_hash' => Hash::make($plainCode),
            ]);

            $plainCodes[] = $plainCode;
        }

        return $plainCodes;
    }

    /**
     * Verifica un código de recuperación enviado por el usuario.
     *
     * Busca entre los códigos no usados del usuario y compara el hash.
     * Si coincide, marca el código como usado y retorna true.
     *
     * @param  User  $user  Usuario que envía el código.
     * @param  string  $submittedCode  Código en texto plano enviado por el usuario.
     * @return bool true si el código es válido, false si no.
     */
    public function verify(User $user, string $submittedCode): bool
    {
        $codes = RecoveryCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->get();

        foreach ($codes as $code) {
            if (! Hash::check($submittedCode, $code->code_hash)) {
                continue;
            }

            $code->update([
                'used_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Genera un código de recuperación con el formato `PREFIX-XXXX-XXXX`.
     *
     * El prefijo se lee de la configuración. Los grupos son 4 caracteres
     * alfanuméricos sin caracteres ambiguos (sin I, O, 0, 1).
     */
    private function generateCode(): string
    {
        $prefix = config('two_factor.recovery_codes.prefix', default: 'APP');

        return sprintf(
            '%s-%s-%s',
            $prefix,
            $this->randomGroup(),
            $this->randomGroup(),
        );
    }

    /**
     * Genera un grupo aleatorio de caracteres alfanuméricos.
     *
     * Usa un alfabeto sin caracteres ambiguos para mejorar la legibilidad.
     * Cada carácter se selecciona de forma criptográficamente segura.
     *
     * @param  int  $length  Longitud del grupo (default: 4).
     */
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
