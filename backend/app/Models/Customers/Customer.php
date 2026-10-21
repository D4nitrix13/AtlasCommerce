<?php

declare(strict_types=1);

namespace App\Models\Customers;

use App\Models\Auth\User;
use App\Models\Carts\Wishlist;
use App\Models\Sales\Order;
use App\Models\Stores\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property Carbon|null $anonymized_at
 * @property Carbon|null $deletion_requested_at
 * @property Carbon|null $deletion_token_expires_at
 */
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model
{
    use HasApiTokens;
    use HasFactory;
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
        'anonymized_at',
        'deletion_requested_at',
        'deletion_token_hash',
        'deletion_token_expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'registered_at' => 'datetime',
        'anonymized_at' => 'datetime',
        'deletion_requested_at' => 'datetime',
        'deletion_token_expires_at' => 'datetime',
    ];

    protected $hidden = [
        'deletion_token_hash',
        'deletion_token_expires_at',
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
     * Tiendas en las que el cliente tiene historial (pivot `customer_stores`).
     *
     * Un cliente puede estar registrado en varias tiendas — la afiliación
     * se crea al asociar la primera orden. Usado por el admin para
     * aislamiento por tenant.
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(related: Store::class, table: 'customer_stores')
            ->withTimestamps();
    }

    /**
     * Órdenes del cliente (en cualquier tienda — el admin filtra por
     * tenant vía `stores` si necesita limitar).
     */
    public function orders(): HasMany
    {
        return $this->hasMany(related: Order::class);
    }

    /**
     * Listas de deseos del cliente.
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(related: Wishlist::class);
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
        // Args posicionales: ver note en Promotion::scopeActive.
        return $query->where('is_active', true);
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
        return $query->where(column: 'customer_type', operator: '=', value: $type);
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
        return $query->where(column: 'email', operator: '=', value: $email);
    }

    /**
     * Filtra clientes que ya fueron anonimizados (PII reemplazada).
     *
     * @param  Builder<Customer>  $query  Consulta del modelo.
     * @return Builder<Customer>
     */
    public function scopeAnonymized(Builder $query): Builder
    {
        return $query->whereNotNull('anonymized_at');
    }

    /**
     * ¿La cuenta ya pasó por el flujo GDPR de borrado y fue anonimizada?
     */
    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    /**
     * ¿Hay un token de borrado activo (no expirado) pendiente de confirmar?
     */
    public function hasPendingDeletionToken(): bool
    {
        return $this->deletion_token_hash !== null
            && $this->deletion_token_expires_at !== null
            && $this->deletion_token_expires_at->isFuture();
    }
}
