<?php

namespace App\Models\Settings;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tipo de cambio entre dos monedas.
 *
 * Tabla: `exchange_rates` — relación N:1 con `currencies` (origen y destino).
 *
 * Almacena la tasa de cambio de una moneda a otra en una fecha específica.
 * La restricción unique en (from_currency_id, to_currency_id, rate_date)
 * garantiza un solo registro por par y día.
 *
 * @property int $id
 * @property int $from_currency_id
 * @property int $to_currency_id
 * @property string $value
 * @property Carbon $rate_date
 */
class ExchangeRate extends Model
{
    protected $fillable = [
        'from_currency_id',
        'to_currency_id',
        'value',
        'rate_date',
    ];

    protected $casts = [
        'value' => 'decimal:8',
        'rate_date' => 'date',
    ];

    public function fromCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency_id');
    }

    public function toCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }

    public function scopeByFromCurrency(Builder $query, int $currencyId): Builder
    {
        return $query->where('from_currency_id', $currencyId);
    }

    public function scopeByDate(Builder $query, Carbon|string $date): Builder
    {
        return $query->where('rate_date', $date);
    }

    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('rate_date', 'desc');
    }
}
