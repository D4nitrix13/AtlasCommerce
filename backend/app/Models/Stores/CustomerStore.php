<?php

namespace App\Models\Stores;

use App\Models\Customers\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relación entre un cliente y una tienda.
 *
 * Tabla: `customer_stores` — relación N:1 con `customers` y `stores`.
 *
 * Vincula un cliente registrado con la tienda en la que realizó compras.
 */
class CustomerStore extends Model
{
    protected $fillable = [
        'customer_id',
        'store_id',
    ];

    /**
     * Tienda asociada.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Cliente asociado.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
