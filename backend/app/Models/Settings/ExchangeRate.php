<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tipo de cambio entre dos monedas.
 *
 * Tabla: `exchange_rates` — relación N:1 con `currencies`.
 */
class ExchangeRate extends Model
{
    protected $fillable = [
        'currency_id',
        'value',
        'rate_date',
    ];

    protected $casts = [
        'value' => 'decimal:6',
        'rate_date' => 'date',
    ];

    /**
     * Moneda asociada.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function scopeByCurrency($query, int $currencyId)
    {
        return $query->where('currency_id', $currencyId);
    }

    public function scopeByDate($query, $date)
    {
        return $query->where('rate_date', $date);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('rate_date', 'desc');
    }
}
