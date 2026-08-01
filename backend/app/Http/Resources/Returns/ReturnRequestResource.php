<?php

declare(strict_types=1);

namespace App\Http\Resources\Returns;

use App\Http\Resources\Customers\CustomerResource;
use App\Http\Resources\Sales\OrderResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una solicitud de devolución.
 *
 * @property int $id
 * @property int $store_id
 * @property int $order_id
 * @property int $customer_id
 * @property string $reason
 * @property string $status
 * @property Carbon $requested_at
 * @property Carbon|null $resolved_at
 */
class ReturnRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'reason' => $this->reason,
            'status' => $this->status,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'order' => new OrderResource($this->whenLoaded('order')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'items' => ReturnRequestItemResource::collection($this->whenLoaded('items') ?? collect()),
            'refunds' => RefundResource::collection($this->whenLoaded('refunds') ?? collect()),
        ];
    }
}
