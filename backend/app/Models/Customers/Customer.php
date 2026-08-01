<?php

namespace App\Models\Customers;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cliente del sistema.
 *
 * Tabla: `customers` — relación N:1 con `users` (opcional) y 1:N
 * con `customer_addresses`.
 *
 * Un cliente puede estar vinculado a un usuario o existir como
 * cliente independiente (compras como invitado). El campo
 * `customer_type` permite diferenciar entre retail, wholesale y vip.
 */
class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'customer_type',
        'identity_document',
        'first_name',
        'last_name',
        'email',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'registered_at' => 'datetime',
    ];

    /**
     * Usuario vinculado al cliente (opcional).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Direcciones del cliente.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * Nombre completo del cliente.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('customer_type', $type);
    }

    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }
}
