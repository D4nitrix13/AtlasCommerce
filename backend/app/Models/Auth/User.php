<?php

namespace App\Models\Auth;

use Database\Factories\Auth\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Modelo de usuario principal.
 *
 * Representa la tabla `users` y actúa como modelo base para todo el sistema
 * de autenticación. Incluye relaciones con roles, intentos de login,
 * eventos de seguridad y configuración de 2FA.
 *
 * La autenticación de API se gestiona mediante Laravel Sanctum (trait HasApiTokens).
 * El cast automático `hashed` en `password` garantiza que la contraseña nunca se
 * almacene en texto plano.
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
     * Intentos de login registrados.
     */
    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class);
    }

    /**
     * Eventos de seguridad registrados.
     */
    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    /**
     * Tokens de restablecimiento de contraseña.
     */
    public function passwordResetTokens(): HasMany
    {
        return $this->hasMany(PasswordResetToken::class);
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
