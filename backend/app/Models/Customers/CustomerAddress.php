<?php

namespace App\Models\Customers;

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
 */
class CustomerAddress extends Model
{
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

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('address_type', $type);
    }

    public function scopeByCountry($query, string $country)
    {
        return $query->where('country', $country);
    }
}
