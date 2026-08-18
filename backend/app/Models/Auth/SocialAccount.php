<?php

declare(strict_types=1);

namespace App\Models\Auth;

use Database\Factories\Auth\SocialAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Identidad social vinculada a un usuario interno.
 *
 * Tabla: `social_accounts`. Una fila por par `(provider, provider_user_id)`
 * — la unicidad la enforce la base vía índice único compuesto. Una
 * `User` puede tener múltiples `SocialAccount` (uno por provider que
 * usó para registrarse).
 *
 * Decisión de diseño: NO persiste el avatar (R10 del proposal). Si el
 * provider rota la foto o el user revoca el acceso, la fila sigue
 * siendo válida porque el binding es por `provider_user_id` (estable).
 */
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    protected $table = 'social_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
    ];

    /**
     * Usuario interno al que pertenece esta identidad social.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
