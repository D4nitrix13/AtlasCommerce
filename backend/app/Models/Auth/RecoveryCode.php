<?php

declare(strict_types=1);

namespace App\Models\Auth;

use Database\Factories\Auth\RecoveryCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Código de recuperación de un solo uso.
 *
 * Cada usuario con 2FA habilitada o con método de recovery basado
 * en codes tiene entre 8 y 10 códigos alfanuméricos de 10 caracteres
 * cada uno (ej. 'a3f8b91c-2d'). El código plain-text SOLO se
 * muestra al user una vez, en el momento de generación. Después solo
 * se almacena el hash bcrypt.
 *
 * Cuando el user consume un code (login o password reset), el row
 * se marca con `used_at`. Una vez consumidos todos los codes, el
 * user puede regenerar codes nuevos (lo que invalida los anteriores).
 *
 * @property int $id
 * @property int $user_id
 * @property string $code_hash
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\Auth\User $user
 */
class RecoveryCode extends Model
{
    /** @use HasFactory<RecoveryCodeFactory> */
    use HasFactory;

    protected $table = 'recovery_codes';

    protected $fillable = [
        'user_id',
        'code_hash',
        'used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    /**
     * Relación al usuario dueño de estos recovery codes.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(related: User::class);
    }

    /**
     * ¿Este code ya fue usado?
     */
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
