<?php

declare(strict_types=1);

namespace App\Http\Resources\Carts;

use App\Support\MoneyFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un carrito de compras.
 *
 * El payload expone totales agregados (`items_count`, `subtotal_cents`,
 * `subtotal`) y los items bajo `items`. El currency formatea los montos
 * con `MoneyFormatter` para que el cliente no haga división por 100.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $store_id
 * @property int $currency_id
 * @property string $status
 * @property Carbon|null $expires_at
 */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->resource->relationLoaded('items') ? $this->resource->items : null;
        $itemsCount = $items?->count() ?? 0;
        $subtotalCents = $items ? (int) $items->sum('subtotal_cents') : 0;
        $currency = $this->resource->relationLoaded('currency') ? $this->resource->currency : null;

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'store_id' => $this->store_id,
            'currency_id' => $this->currency_id,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'items_count' => $itemsCount,
            'subtotal_cents' => $subtotalCents,
            'subtotal' => MoneyFormatter::payload(
                cents: $subtotalCents,
                currency: $currency,
            ),
            'items' => CartItemResource::collection($items ?? []),
        ];
    }
}
