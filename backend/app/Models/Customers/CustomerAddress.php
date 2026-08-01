<?php

namespace App\Models\Customers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dirección de un cliente.
 *
 * Tabla: `customer_addresses` — relación N:1 con `customers`.
 *
 * Almacena direcciones de envío y facturación. Un cliente puede
 * tener múltiples direcciones, marcando una como principal.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $address_type
 * @property string $country
 * @property string|null $department
 * @property string|null $municipality
 * @property string $detailed_address
 * @property string|null $reference
 * @property bool $is_primary
 */
class CustomerAddress extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'address_type',
        'country',
        'department',
        'municipality',
        'detailed_address',
        'reference',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Cliente al que pertenece la dirección.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Filtra la dirección principal del cliente.
     *
     * @param  Builder<CustomerAddress>  $query  Consulta del modelo.
     * @return Builder<CustomerAddress>
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where(column: 'is_primary', value: true);
    }

    /**
     * Filtra direcciones por tipo específico (billing, shipping).
     *
     * @param  Builder<CustomerAddress>  $query  Consulta del modelo.
     * @param  string  $type  Tipo de dirección.
     * @return Builder<CustomerAddress>
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where(column: 'address_type', value: $type);
    }

    /**
     * Filtra direcciones por país específico.
     *
     * @param  Builder<CustomerAddress>  $query  Consulta del modelo.
     * @param  string  $country  País de la dirección.
     * @return Builder<CustomerAddress>
     */
    public function scopeByCountry(Builder $query, string $country): Builder
    {
        return $query->where(column: 'country', value: $country);
    }
}
