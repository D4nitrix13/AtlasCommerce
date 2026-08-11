<?php

declare(strict_types=1);

namespace App\Models\Auth;

use Database\Factories\Auth\TwoFactorSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Configuración 2FA / TOTP por usuario.
 *
 * Una fila por usuario que tenga 2FA habilitada. La ausencia de fila
 * significa 2FA no habilitada (más rápido que un bool null).
 *
 * El `secret` se almacena encryptado con Laravel Crypt facade (AES-256
 * + HMAC) para que aunque la DB sea comprometida, el secret TOTP no
 * sea usable sin la APP_KEY del server.
 *
 * El `enabled_at` es null = 2FA deshabilitado. Cuando es timestamp
 * no-null, indica cuándo se habilitó por última vez. (Re-disable + re-enable
 * actualiza el timestamp.)
 *
 * @property int $id
 * @property int $user_id
 * @property string $secret
 * @property \Illuminate\Support\Carbon|null $enabled_at
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $recovery_codes_generated_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\Auth\User $user
 */
class TwoFactorSetting extends Model
{
    /** @use HasFactory<TwoFactorSettingFactory> */
    use HasFactory;

    protected $table = 'two_factor_settings';

    protected $fillable = [
        'user_id',
        'secret',
        'enabled_at',
        'last_used_at',
        'recovery_codes_generated_at',
    ];

    /**
     * Cast automático del secret.
     *
     * `secret` se encrypta con Laravel Crypt facade al asignar. Esto
     * hace que el secret nunca se persista en texto plano a la DB.
     * Para acceder al valor plain (ej. al calcular TOTP), usar
     * `$model->secret` que automáticamente desencrypta.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'enabled_at' => 'datetime',
            'last_used_at' => 'datetime',
            'recovery_codes_generated_at' => 'datetime',
        ];
    }

    /**
     * Relación al usuario dueño de esta config 2FA.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(related: User::class);
    }

    /**
     * Helper: ¿está habilitada 2FA para este user?
     */
    public function isEnabled(): bool
    {
        return $this->enabled_at !== null;
    }
}
