<?php

namespace App\Models\Purchases;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Proveedor de mercancía.
 *
 * Tabla: `suppliers` — relación 1:N con `purchases`.
 *
 * @property int $id
 * @property string $name
 * @property string|null $ruc
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property bool $is_active
 */
class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'ruc',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Órdenes de compra realizadas al proveedor.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Filtra proveedores activos.
     *
     * @param  Builder<Supplier>  $query  Consulta del modelo.
     * @return Builder<Supplier>
     */
    public function scopeActive($query)
    {
        return $query->where(column: 'is_active', value: true);
    }

    /**
     * Filtra proveedores por nombre (búsqueda parcial).
     *
     * @param  Builder<Supplier>  $query  Consulta del modelo.
     * @param  string  $name  Texto a buscar en el nombre.
     * @return Builder<Supplier>
     */
    public function scopeByName($query, string $name)
    {
        return $query->where(column: 'name', operator: 'like', value: "%{$name}%");
    }
}
