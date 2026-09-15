<?php

declare(strict_types=1);

namespace Database\Factories\Support;

use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Support\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'store_id' => function (): int {
                $currency = Currency::query()->where(column: 'is_primary', operator: '=', value: true)->first()
                    ?? Currency::factory()->primary()->create();

                return Store::factory()->for($currency, 'currency')->create()->id;
            },
            'subject' => fake()->sentence(nbWords: 6),
            'description' => fake()->paragraph(),
            'status' => 'open',
            'priority' => 'normal',
        ];
    }

    public function open(): self
    {
        return $this->state(fn () => ['status' => 'open']);
    }

    public function inProgress(): self
    {
        return $this->state(fn () => ['status' => 'in_progress']);
    }

    public function closed(): self
    {
        return $this->state(fn () => ['status' => 'closed']);
    }

    public function forStore(Store $store): self
    {
        return $this->state(fn () => ['store_id' => $store->id]);
    }
}
