<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de ticket de soporte (RF-SUP-160).
 *
 * @mixin \App\Models\Support\SupportTicket
 */
class TicketResource extends JsonResource
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
            'messages' => $this->whenLoaded('messages', fn () => $this->resource->messages->map(fn ($m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'message' => $m->message,
                'messaged_at' => $m->messaged_at?->toIso8601String(),
            ])->all()),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
