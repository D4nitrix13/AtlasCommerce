<?php

declare(strict_types=1);

namespace App\Http\Resources\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de ticket de soporte (RF-SUP-160).
 *
 * @mixin \App\Models\Support\SupportTicket
 */
class AdminSupportTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'customer_id' => $this->resource->customer_id,
            'store_id' => $this->resource->store_id,
            'subject' => $this->resource->subject,
            'description' => $this->resource->description,
            'status' => $this->resource->status,
            'priority' => $this->resource->priority,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            'messages' => $this->resource->relationLoaded('messages')
                ? AdminSupportTicketMessageResource::collection(resource: $this->resource->messages)->resolve()
                : null,
        ];
    }
}
