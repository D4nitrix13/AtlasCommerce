<?php

namespace App\Models\Customers;

use App\Models\Auth\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

/**
 * Cliente del sistema.
 *
 * Tabla: `customers` — relación N:1 con `users` (opcional) y 1:N
 * con `customer_addresses`.
 *
 * Un cliente puede estar vinculado a un usuario o existir como
 * cliente independiente (compras como invitado). El campo
 * `customer_type` permite diferenciar entre retail, wholesale y vip.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $customer_type
 * @property string|null $identity_document
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property bool $is_active
 * @property Carbon $registered_at
 */
class Customer extends Model
{
    use HasApiTokens;
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
     *
     * @return string Nombre concatenado.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Filtra clientes activos.
     *
     * @param  Builder<Customer>  $query  Consulta del modelo.
     * @return Builder<Customer>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(column: 'is_active', value: true);
    }

    /**
     * Filtra clientes por tipo específico (retail, wholesale, vip).
     *
     * @param  Builder<Customer>  $query  Consulta del modelo.
     * @param  string  $type  Tipo de cliente.
     * @return Builder<Customer>
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where(column: 'customer_type', value: $type);
    }

    /**
     * Filtra clientes por correo electrónico específico.
     *
     * @param  Builder<Customer>  $query  Consulta del modelo.
     * @param  string  $email  Correo electrónico del cliente.
     * @return Builder<Customer>
     */
    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where(column: 'email', value: $email);
    }
}
