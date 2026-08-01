<?php

namespace Database\Factories\Sales;

use App\Models\Auth\User;
use App\Models\Sales\Order;
use App\Models\Sales\OrderCustomDesign;
use App\Models\Sales\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderCustomDesign>
 */
class OrderCustomDesignFactory extends Factory
{
    protected $model = OrderCustomDesign::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'order_item_id' => OrderItem::factory(),
            'custom_design_id' => null,
            'design_snapshot' => [
                'canvas' => ['width' => 1200, 'height' => 1200],
                'layers' => [],
                'generated_at' => now()->toIso8601String(),
            ],
            'production_file_url' => null,
            'production_status' => 'pending',
            'production_notes' => fake()->optional()->sentence(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'production_started_at' => null,
            'production_completed_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['production_status' => 'pending']);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'production_status' => 'approved',
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'production_status' => 'rejected',
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'rejected_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function inProduction(): static
    {
        return $this->state(fn () => [
            'production_status' => 'in_production',
            'production_started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'production_status' => 'completed',
            'production_started_at' => now()->subDays(3),
            'production_completed_at' => now(),
        ]);
    }
}
