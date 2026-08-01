<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de códigos de recuperación para 2FA.
 *
 * Tabla: `recovery_codes` — relación N:1 con `users`.
 *
 * Almacena hashes bcrypt de recovery codes de un solo uso con formato
 * PREFIX-XXXX-XXXX (ej. `APP-X7K2-M9P4`). El alfabeto excluye caracteres
 * ambiguos (I, O, 0, 1) para facilitar la lectura manual.
 *
 * Flujo de vida:
 * 1. Se generan en lote (default: 8) al activar 2FA o al regenerar.
 * 2. Los códigos en texto plano se muestran al usuario UNA SOLA VEZ.
 * 3. Solo permanecen los hashes en la BD.
 * 4. Al usar un código, se marca `used: true` y `used_at: now()`.
 * 5. La regeneración elimina todos los códigos anteriores y crea nuevos.
 *
 * Los recovery codes son un mecanismo de respaldo para cuando el usuario
 * no tiene acceso a su método 2FA principal (ej. perdió acceso al correo).
 */
class RecoveryCode extends Model
{
    protected $fillable = [
        'user_id',
        'code_hash',
        'used',
        'used_at',
    ];

    protected $casts = [
        'used' => 'boolean',
        'used_at' => 'datetime',
    ];

    /**
     * Usuario propietario de este código de recuperación.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
