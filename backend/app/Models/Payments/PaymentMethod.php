<?php

namespace App\Models\Payments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Método de pago configurado en el sistema.
 *
 * Tabla: `payment_methods` — relación 1:N con `payments`.
 *
 * Define los métodos aceptados (efectivo, tarjeta, transferencia, etc.).
 */
class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Pagos realizados con este método.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
