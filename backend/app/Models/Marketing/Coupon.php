<?php

namespace App\Models\Marketing;

use App\Models\Stores\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cupón de descuento aplicable a pedidos.
 *
 * Tabla: `coupons` — relación 1:N con `coupon_usages`.
 *
 * Los cupones pueden ser globales o asociados a una tienda específica.
 * Soportan límites de uso, ventanas de vigencia y montos mínimos.
 *
 * @property int $id
 * @property int|null $store_id
 * @property string $code
 * @property string $discount_type
 * @property string $value
 * @property string $minimum_amount
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $usage_limit
 * @property int $used_count
 * @property bool $is_active
 */
class Coupon extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'store_id',
        'code',
        'discount_type',
        'value',
        'minimum_amount',
        'starts_at',
        'ends_at',
        'usage_limit',
        'used_count',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Tienda asociada al cupón.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Registros de uso del cupón.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Determina si el cupón es válido actualmente.
     *
     * Verifica que esté activo, dentro de la ventana de vigencia
     * y que no se haya agotado el límite de uso.
     *
     * @return bool True cuando el cupón puede aplicarse.
     */
    public function isValid(): bool
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

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Filtra cupones activos.
     *
     * @param  Builder<Coupon>  $query  Consulta del modelo.
     * @return Builder<Coupon>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(column: 'is_active', value: true);
    }

    /**
     * Filtra cupones vigentes (activos, no expirados, con usos disponibles).
     *
     * @param  Builder<Coupon>  $query  Consulta del modelo.
     * @return Builder<Coupon>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where(column: 'is_active', value: true)
            ->where(function ($q) {
                $q->whereNull(column: 'starts_at')
                    ->orWhere(column: 'starts_at', operator: '<=', value: now());
            })
            ->where(function ($q) {
                $q->whereNull(column: 'ends_at')
                    ->orWhere(column: 'ends_at', operator: '>=', value: now());
            });
    }

    /**
     * Filtra cupones por código específico.
     *
     * @param  Builder<Coupon>  $query  Consulta del modelo.
     * @param  string  $code  Código del cupón.
     * @return Builder<Coupon>
     */
    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where(column: 'code', value: $code);
    }

    /**
     * Filtra cupones por tienda específica.
     *
     * @param  Builder<Coupon>  $query  Consulta del modelo.
     * @param  int  $storeId  Identificador de la tienda.
     * @return Builder<Coupon>
     */
    public function scopeByStore(Builder $query, int $storeId): Builder
    {
        return $query->where(column: 'store_id', value: $storeId);
    }
}
