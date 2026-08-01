<?php

namespace App\Services\Auth;

use App\Models\Auth\RecoveryCode;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Hash;

/**
 * Servicio de códigos de recuperación para 2FA.
 *
 * Gestiona el ciclo de vida completo de los recovery codes:
 *
 * 1. **Generación**: códigos con formato PREFIX-XXXX-XXXX usando un alfabeto reducido
 *    que excluye caracteres ambiguos (I, O, 0, 1) para facilitar la escritura manual.
 * 2. **Almacenamiento**: cada código se guarda como hash bcrypt en `recovery_codes.code_hash`.
 *    El texto plano NUNCA se persiste en la BD.
 * 3. **Visualización**: los códigos en texto plano se devuelven al usuario UNA SOLA VEZ
 *    al momento de la regeneración.
 * 4. **Verificación**: se itera sobre los códigos no usados y se compara con `Hash::check()`.
 *    Si hay coincidencia, se marca como usado (de un solo uso).
 *
 * Seguridad:
 * - La regeneración elimina TODOS los códigos anteriores antes de crear nuevos.
 * - Cada código solo puede usarse una vez (campo `used`).
 * - El hash bcrypt protege los códigos incluso si la BD es comprometida.
 */
class RecoveryCodeService
{
    /**
     * Regenera los recovery codes de un usuario.
     *
     * Flujo:
     * 1. Lee la cantidad configurada de `config/two_factor.recovery_codes.count`.
     * 2. Elimina todos los recovery codes anteriores del usuario.
     * 3. Genera nuevos códigos y los almacena como hash bcrypt.
     * 4. Retorna los códigos en texto plano (solo esta vez se muestran).
     *
     * **Importante**: el caller es responsable de mostrar estos códigos al usuario
     * solo una vez. No hay forma de recuperarlos después de esta llamada.
     *
     * @param  User  $user  Usuario cuyos recovery codes se regeneran.
     * @return array<int, string> Lista de códigos en texto plano.
     */
    public function regenerate(User $user): array
    {
        $count = (int) config(key: 'two_factor.recovery_codes.count', default: 8);

        // Se eliminan los códigos anteriores antes de generar nuevos.
        // Esto invalida cualquier código que el usuario pudiera tener guardado.
        RecoveryCode::query()
            ->where(column: 'user_id', operator: '=', value: $user->id, boolean: 'and')
            ->delete();

        $plainCodes = [];

        for ($index = 0; $index < $count; $index++) {
            $plainCode = $this->generateCode();

            // Cada código se guarda como hash bcrypt; el texto plano solo se retorna en la respuesta.
            RecoveryCode::create([
                'user_id' => $user->id,
                'code_hash' => Hash::make(value: $plainCode),
                'used' => false,
            ]);

            $plainCodes[] = $plainCode;
        }

        return $plainCodes;
    }

    /**
     * Verifica un código de recuperación ingresado por el usuario.
     *
     * Flujo de verificación:
     * 1. Carga todos los recovery codes NO usados del usuario.
     * 2. Itera cada código y compara con `Hash::check()`.
     * 3. Si hay coincidencia, marca el código como usado y retorna true.
     * 4. Si ninguno coincide, retorna false.
     *
     * Nota: esta verificación es O(n) donde n es la cantidad de códigos no usados.
     * Con 8 códigos máximo, el impacto es despreciable.
     *
     * @param  User  $user  Usuario que intenta verificar el código.
     * @param  string  $submittedCode  Código de recuperación ingresado.
     * @return bool true si el código es válido, false en caso contrario.
     */
    public function verify(User $user, string $submittedCode): bool
    {
        $codes = RecoveryCode::query()
            ->where('user_id', $user->id)
            ->where('used', false)
            ->get();

        foreach ($codes as $code) {
            // Hash::check compara el código ingresado contra el hash bcrypt almacenado.
            if (! Hash::check(value: $submittedCode, hashedValue: $code->code_hash)) {
                continue;
            }

            // Se marca como usado inmediatamente para prevenir reutilización.
            $code->update([
                'used' => true,
                'used_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Genera un código con formato PREFIX-XXXX-XXXX.
     *
     * El prefijo se lee de `config/two_factor.recovery_codes.prefix` (default: 'APP').
     * Los grupos de 4 caracteres se generan con el alfabeto reducido.
     */
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

    /**
     * Genera un grupo de caracteres alfanuméricos del alfabeto reducido.
     *
     * El alfabeto excluye caracteres ambiguos:
     * - I (parece 1 o l)
     * - O (parece 0)
     * - 0 (parece O)
     * - 1 (parece I o l)
     *
     * Esto facilita la lectura y escritura manual de los códigos.
     */
    private function randomGroup(int $length = 4): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $value = '';

        for ($index = 0; $index < $length; $index++) {
            $value .= $alphabet[random_int(min: 0, max: strlen(string: $alphabet) - 1)];
        }

        return $value;
    }
}
