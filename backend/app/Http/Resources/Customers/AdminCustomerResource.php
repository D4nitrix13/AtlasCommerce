<?php

declare(strict_types=1);

namespace App\Http\Resources\Customers;

use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para el panel admin de clientes (RF-CUST-040).
 *
 * Shape controlado: a diferencia de `CustomerResource` (que delega al
 * parent y expone todos los atributos), este resource excluye campos
 * sensibles del modelo:
 *  - `deletion_token_hash`: nunca debe filtrarse.
 *  - `deletion_token_expires_at`: metadata del flujo GDPR (RF-CUST-042).
 *
 * Incluye el conteo de órdenes y el lifetime value cuando la relación
 * `orders` viene eager-loaded.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $customer_type
 * @property string|null $identity_document
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property bool $is_active
 * @property \Carbon\Carbon $registered_at
 * @property \Carbon\Carbon|null $anonymized_at
 * @property \Carbon\Carbon|null $deletion_requested_at
 */
class AdminCustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ordersLoaded = $this->resource->relationLoaded('orders');

        $orderCount = $ordersLoaded ? $this->resource->orders->count() : null;
        $lifetimeValueCents = $ordersLoaded
            ? (int) $this->resource->orders
                ->where('status', '!=', OrderStatus::Cancelled)
                ->sum('total_cents')
            : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'customer_type' => $this->customer_type,
            'identity_document' => $this->identity_document,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => "{$this->first_name} {$this->last_name}",
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'registered_at' => $this->registered_at?->toIso8601String(),
            'anonymized_at' => $this->anonymized_at?->toIso8601String(),
            'deletion_requested_at' => $this->deletion_requested_at?->toIso8601String(),
            'is_anonymized' => $this->anonymized_at !== null,
            'order_count' => $orderCount,
            'lifetime_value_cents' => $lifetimeValueCents,
        ];
    }
}
