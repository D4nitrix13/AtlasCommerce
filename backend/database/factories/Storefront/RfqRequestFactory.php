<?php

declare(strict_types=1);

namespace Database\Factories\Storefront;

use App\Models\Customers\Customer;
use App\Models\Storefront\RfqRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfqRequest>
 */
class RfqRequestFactory extends Factory
{
    protected $model = RfqRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => 'RFQ-'.strtoupper(base_convert(num: (string) fake()->unique()->numberBetween(1000000000, 9999999999), from_base: 10, to_base: 36)),
            'customer_id' => Customer::factory(),
            'store_id' => null,
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => fake()->e164PhoneNumber(),
            'company_name' => fake()->company(),
            'subject' => 'Cotización '.fake()->words(nb: 3, asText: true),
            'message' => fake()->paragraph(),
            'items' => [],
            'status' => 'pending',
            'estimated_total_cents' => fake()->numberBetween(10000, 500000),
            'currency_code' => 'NIO',
        ];
    }

    public function forCustomer(Customer $customer): self
    {
        return $this->state(fn () => [
            'customer_id' => $customer->id,
            'contact_name' => $customer->first_name.' '.$customer->last_name,
            'contact_email' => $customer->email,
        ]);
    }
}
