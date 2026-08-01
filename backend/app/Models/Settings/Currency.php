<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Moneda soportada por el sistema.
 *
 * Tabla: `currencies` — relación 1:N con `exchange_rates`.
 */
class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Tipos de cambio de esta moneda.
     */
    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class);
    }

    /**
     * Tipo de cambio más reciente.
     */
    public function latestRate(): ?ExchangeRate
    {
        return $this->exchangeRates()
            ->orderBy('rate_date', 'desc')
            ->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}
