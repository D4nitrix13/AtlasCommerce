<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Auth\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial de cambios de estado de una factura.
 *
 * Tabla: `invoice_status_histories` — relación N:1 con `invoices`.
 *
 * Registra cada transición de estado (emitida, pagada, anulada)
 * con timestamp y usuario responsable.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int|null $user_id
 * @property string|null $previous_status
 * @property string $new_status
 * @property string|null $observation
 * @property Carbon $changed_at
 */
class InvoiceStatusHistory extends Model
{
    protected $fillable = [
        'invoice_id',
        'user_id',
        'previous_status',
        'new_status',
        'observation',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    /**
     * Factura a la que pertenece el registro.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Usuario que realizó el cambio (opcional — cambios automáticos del sistema).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
