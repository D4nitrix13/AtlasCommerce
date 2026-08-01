<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
/**
 * Moneda soportada por el sistema.
 *
 * Tabla: `currencies` — relación 1:N con `exchange_rates`.
 *
 * Cada tienda puede configurar su moneda principal. El sistema soporta
 * múltiples monedas con tasas de cambio para conversión en tiempo real.
 *
 * @property int $id
 * @property string $code Código ISO 4217 (3 caracteres).
 * @property string $name
 * @property string $symbol
 * @property int $decimal_places
 * @property bool $is_primary
 * @property bool $is_active
 */
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency_id');
    }

    public function inverseExchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'to_currency_id');
    }

    public function latestRate(): ?ExchangeRate
    {
        return $this->exchangeRates()
            ->orderBy('rate_date', 'desc')
            ->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }
}
