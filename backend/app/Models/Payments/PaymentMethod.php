<?php

declare(strict_types=1);

namespace App\Models\Payments;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
/**
 * Método de pago configurado en el sistema (tarjeta, transferencia,
 * efectivo, pasarela externa, etc.).
 *
 * Tabla: `payment_methods` — relación 1:N con `payments`, `refunds`.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $provider
 * @property bool $is_active
 */
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'provider',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
