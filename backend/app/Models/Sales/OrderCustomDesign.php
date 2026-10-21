<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Auth\User;
use App\Models\Customization\CustomDesign;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
 *
 * @property int $id
 * @property int $order_id
 * @property int $order_item_id
 * @property int|null $custom_design_id
 * @property array $design_snapshot
 * @property string|null $production_file_url
 * @property string $production_status
 * @property string|null $production_notes
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property Carbon|null $production_started_at
 * @property Carbon|null $production_completed_at
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
        return $this->belongsTo(
            related: User::class,
            foreignKey: 'reviewed_by',
        );
    }

    /**
     * Filtra diseños pendientes de revisión.
     *
     * @param  Builder<OrderCustomDesign>  $query  Consulta del modelo.
     * @return Builder<OrderCustomDesign>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where(column: 'production_status', operator: '=', value: 'pending');
    }

    /**
     * Filtra diseños aprobados para producción.
     *
     * @param  Builder<OrderCustomDesign>  $query  Consulta del modelo.
     * @return Builder<OrderCustomDesign>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where(column: 'production_status', operator: '=', value: 'approved');
    }

    /**
     * Filtra diseños rechazados.
     *
     * @param  Builder<OrderCustomDesign>  $query  Consulta del modelo.
     * @return Builder<OrderCustomDesign>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where(column: 'production_status', operator: '=', value: 'rejected');
    }

    /**
     * Filtra diseños en proceso de producción.
     *
     * @param  Builder<OrderCustomDesign>  $query  Consulta del modelo.
     * @return Builder<OrderCustomDesign>
     */
    public function scopeInProduction(Builder $query): Builder
    {
        return $query->where(column: 'production_status', operator: '=', value: 'in_production');
    }

    /**
     * Filtra diseños con producción completada.
     *
     * @param  Builder<OrderCustomDesign>  $query  Consulta del modelo.
     * @return Builder<OrderCustomDesign>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where(column: 'production_status', operator: '=', value: 'completed');
    }
}
