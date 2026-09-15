<?php

declare(strict_types=1);

namespace App\Http\Resources\Customers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de dirección para el cliente autenticado (RF-CUST-041).
 *
 * @mixin \App\Models\Customers\CustomerAddress
 */
class CustomerAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'customer_id' => $this->resource->customer_id,
            'address_type' => $this->resource->address_type,
            'country' => $this->resource->country,
            'department' => $this->resource->department,
            'municipality' => $this->resource->municipality,
            'detailed_address' => $this->resource->detailed_address,
            'reference' => $this->resource->reference,
            'is_primary' => (bool) $this->resource->is_primary,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
