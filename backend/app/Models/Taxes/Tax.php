<?php

declare(strict_types=1);

namespace App\Models\Taxes;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Definición de un impuesto del sistema.
 *
 * Tabla: `taxes` — relación N:N con `products` a través de `product_tax`.
 *
 * Los impuestos se aplican a productos individuales y se calculan
 * sobre el subtotal de la orden durante el checkout.
 *
 * @property int $id
 * @property string $name
 * @property string $percentage
 * @property bool $is_active
 */
class Tax extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'percentage',
        'is_active',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Productos con este impuesto aplicado.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Product::class,
            table: 'product_tax',
        );
    }

    /**
     * Filtra impuestos activos.
     *
     * @param  Builder<Tax>  $query  Consulta del modelo.
     * @return Builder<Tax>
     */
    public function scopeActive(Builder $query): Builder
    {
        // Args posicionales: ver note en Promotion::scopeActive sobre
        // named-arg `value: true` en columnas boolean con PostgreSQL.
        return $query->where('is_active', true);
    }

    /**
     * Filtra impuestos por nombre específico.
     *
     * @param  Builder<Tax>  $query  Consulta del modelo.
     * @param  string  $name  Nombre del impuesto.
     * @return Builder<Tax>
     */
    public function scopeByName(Builder $query, string $name): Builder
    {
        return $query->where(column: 'name', value: $name);
    }
}
