<?php

namespace Database\Factories\Shipping;

use App\Models\Sales\Order;
use App\Models\Settings\Currency;
use App\Models\Shipping\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'shipping_company_id' => null,
            'shipping_zone_id' => null,
            'currency_id' => Currency::factory(),
            'tracking_code' => 'SHP-'.strtoupper(bin2hex(random_bytes(3))),
            'shipping_cost_cents' => 0,
            'status' => 'pending',
        ];
    }
}
