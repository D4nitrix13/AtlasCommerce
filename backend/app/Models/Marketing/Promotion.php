<?php

namespace App\Models\Marketing;

use App\Models\Catalog\Product;
use App\Models\Stores\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Promoción o campaña de marketing activa.
 *
 * Tabla: `promotions` — relación N:N con `products` a través de `product_promotion`.
 *
 * Las promociones pueden ser globales o asociadas a una tienda.
 * Soportan ventanas de vigencia y tipos de descuento variables.
 *
 * @property int $id
 * @property int|null $store_id
 * @property string $name
 * @property string|null $description
 * @property string $discount_type
 * @property string $value
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 */
class Promotion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_id',
        'name',
        'description',
        'discount_type',
        'value',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Tienda asociada a la promoción.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Productos incluidos en la promoción.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Product::class,
            table: 'product_promotion',
        );
    }

    /**
     * Determina si la promoción está vigente.
     *
     * @return bool True cuando la promoción puede aplicarse.
     */
    public function isActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Filtra promociones activas.
     *
     * @param  Builder<Promotion>  $query  Consulta del modelo.
     * @return Builder<Promotion>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(column: 'is_active', value: true);
    }

    /**
     * Filtra promociones vigentes (activas y dentro de la ventana temporal).
     *
     * @param  Builder<Promotion>  $query  Consulta del modelo.
     * @return Builder<Promotion>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where(column: 'is_active', value: true)
            ->where(function ($q) {
                $q->whereNull(columns: 'starts_at')
                    ->orWhere(column: 'starts_at', operator: '<=', value: now());
            })
            ->where(function ($q) {
                $q->whereNull(columns: 'ends_at')
                    ->orWhere(column: 'ends_at', operator: '>=', value: now());
            });
    }

    /**
     * Filtra promociones por tienda específica.
     *
     * @param  Builder<Promotion>  $query  Consulta del modelo.
     * @param  int  $storeId  Identificador de la tienda.
     * @return Builder<Promotion>
     */
    public function scopeByStore(Builder $query, int $storeId): Builder
    {
        return $query->where(column: 'store_id', value: $storeId);
    }
}
