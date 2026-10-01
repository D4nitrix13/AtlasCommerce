<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de respuesta a una solicitud de cotización (RFQ).
 *
 * Devuelve el identificador público `public_id` tipo `RFQ-{timestamp36}`
 * que el cliente ve en su email y el equipo de ventas referencia en su
 * pipeline.
 *
 * @mixin \App\Models\Storefront\RfqRequest
 */
class RfqResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->resource->public_id,
            'status' => $this->resource->status,
            'subject' => $this->resource->subject,
            'contact_name' => $this->resource->contact_name,
            'contact_email' => $this->resource->contact_email,
            'company_name' => $this->resource->company_name,
            'items_count' => is_array($this->resource->items) ? count($this->resource->items) : 0,
            'estimated_total_cents' => $this->resource->estimated_total_cents,
            'currency_code' => $this->resource->currency_code,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
