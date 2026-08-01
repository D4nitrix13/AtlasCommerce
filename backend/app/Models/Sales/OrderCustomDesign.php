<?php

namespace App\Models\Sales;

use App\Models\Auth\User;
use App\Models\Customization\CustomDesign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Referencia de un diseño personalizado asociado a un pedido.
 *
 * Tabla: `order_custom_designs` — relación N:1 con `orders`, `order_items` y `custom_designs`.
 *
 * Captura un snapshot del diseño al momento del pedido,
 * incluyendo archivo de producción, estado de revisión
 * y seguimiento del proceso productivo.
 */
class OrderCustomDesign extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'custom_design_id',
        'design_snapshot',
        'production_file_url',
        'production_status',
        'production_notes',
        'reviewed_by',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'production_started_at',
        'production_completed_at',
    ];

    protected $casts = [
        'design_snapshot' => 'array',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'production_started_at' => 'datetime',
        'production_completed_at' => 'datetime',
    ];

    /**
     * Pedido al que pertenece el diseño.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Artículo del pedido asociado al diseño.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Diseño personalizado original.
     */
    public function customDesign(): BelongsTo
    {
        return $this->belongsTo(CustomDesign::class);
    }

    /**
     * Usuario que revisó el diseño.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(related: User::class, foreignKey: 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('production_status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('production_status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('production_status', 'rejected');
    }

    public function scopeInProduction($query)
    {
        return $query->where('production_status', 'in_production');
    }

    public function scopeCompleted($query)
    {
        return $query->where('production_status', 'completed');
    }
}
