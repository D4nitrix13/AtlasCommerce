<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Almacén físico o virtual donde se almacenan los productos.
 *
 * Tabla: `warehouses` — relación 1:N con `inventories`.
 */
class Warehouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Registros de inventario del almacén.
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
