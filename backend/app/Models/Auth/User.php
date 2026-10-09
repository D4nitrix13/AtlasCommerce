<?php

declare(strict_types=1);

namespace App\Models\Auth;

use App\Models\Stores\Store;
use Database\Factories\Auth\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Modelo de usuario principal.
 *
 * Representa la tabla `users` y actúa como modelo base para todo el sistema
 * de autenticación. Incluye relaciones con roles, configuración de 2FA,
 * recovery codes y challenges OTP.
 *
 * La autenticación de API se gestiona mediante Laravel Sanctum (trait HasApiTokens).
 * El cast automático `hashed` en `password` garantiza que la contraseña nunca se
 * almacene en texto plano.
 *
 * Multi-tenancy: la asociación usuario↔tienda es N:N vía tabla pivot
 * `user_stores`. Esto permite que un mismo admin gestione múltiples
 * tiendas con un rol distinto en cada una. La validación de acceso a
 * la tienda activa se hace en `AuthController::login` chequeando
 * contra `StoreContextService`.
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'role_id',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'is_active',
        'email_verified_at',
        'registered_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Atributos con cast automático.
     *
     * `email_verified_at` se trata como datetime para facilitar comparaciones.
     * `password` usa el cast `hashed` de Laravel que aplica Hash::make() automáticamente
     * al asignar un valor, evitando que la contraseña en texto plano se persista.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'registered_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    /**
     * Rol asignado al usuario (opcional).
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Tiendas a las que el usuario tiene acceso.
     *
     * Relación N:N vía tabla pivot `user_stores`. Permite que un mismo
     * usuario sea admin de Panda Y de Kitsune simultáneamente, con
     * roles distintos por tienda.
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Store::class,
            table: 'user_stores',
        )->withPivot(columns: 'role_id');
    }

    /**
     * Determina si el usuario tiene acceso a la tienda indicada.
     *
     * Usado por `AuthController::login` para validar que el usuario
     * puede autenticarse en la tienda activa resuelta por host.
     */
    public function hasAccessToStore(int $storeId): bool
    {
        return $this->stores()
            ->where(column: 'stores.id', operator: '=', value: $storeId)
            ->exists();
    }

    /**
     * Tokens de restablecimiento de contraseña.
     */
    public function passwordResetTokens(): HasMany
    {
        return $this->hasMany(PasswordResetToken::class);
    }

    /**
     * Identidades sociales vinculadas (Google, Facebook, etc.).
     *
     * Un user puede tener múltiples `SocialAccount` — uno por provider
     * con el que se autenticó. El binding vive en `social_accounts`
     * (FK con `cascadeOnDelete`).
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Configuración 2FA / TOTP del usuario (one-to-one).
     *
     * Si la relación no existe, 2FA no está habilitada. Si existe,
     * TwoFactorSetting::isEnabled() indica si está activa.
     */
    public function twoFactorSetting(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TwoFactorSetting::class);
    }

    /**
     * Recovery codes del usuario (one-to-many).
     *
     * Solo los codes no usados (used_at IS NULL) están disponibles
     * para recovery. Usar el scope `unused()` del modelo.
     */
    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(RecoveryCode::class);
    }

    /**
     * Challenges OTP activos del usuario (one-to-many).
     *
     * Solo los vigentes (consumed_at IS NULL, expires_at > now(),
     * attempts < max). Usar el scope `usable()` del modelo.
     */
    public function otpChallenges(): HasMany
    {
        return $this->hasMany(OtpChallenge::class);
    }

    /**
     * ¿Tiene 2FA habilitada?
     */
    public function hasTwoFactorEnabled(): bool
    {
        $setting = $this->twoFactorSetting;

        return $setting !== null && $setting->isEnabled();
    }

    /**
     * ¿Tiene al menos un recovery code disponible?
     */
    public function hasAvailableRecoveryCode(): bool
    {
        return $this->recoveryCodes()
            ->whereNull(columns: 'used_at')
            ->exists();
    }

    /**
     * Cuenta de recovery codes no usados.
     */
    public function unusedRecoveryCodesCount(): int
    {
        return $this->recoveryCodes()
            ->whereNull(columns: 'used_at')
            ->count();
    }

    /**
     * Nombre completo del usuario.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Determina si el usuario tiene el permiso identificado por slug.
     *
     * Reglas:
     *  - Si el usuario no tiene rol → false.
     *  - Si el rol es `admin` (slug) → bypasea el chequeo (siempre true).
     *  - Si el rol está inactivo → false aunque tenga el permiso.
     *  - Si no, consulta `role.permissions` y compara por slug.
     */
    public function hasPermission(string $slug): bool
    {
        $role = $this->role;

        if ($role === null) {
            return false;
        }

        if ($role->slug === 'admin') {
            return true;
        }

        if (! $role->is_active) {
            return false;
        }

        return $role->permissions()
            ->where(column: 'slug', operator: '=', value: $slug)
            ->exists();
    }
}
