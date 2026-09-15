<?php

declare(strict_types=1);

namespace App\Http\Resources\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de mensaje de ticket (RF-SUP-160).
 *
 * @mixin \App\Models\Support\SupportTicketMessage
 */
class AdminSupportTicketMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'support_ticket_id' => $this->resource->support_ticket_id,
            'user_id' => $this->resource->user_id,
            'message' => $this->resource->message,
            'messaged_at' => $this->resource->messaged_at?->toIso8601String(),
        ];
    }
}
