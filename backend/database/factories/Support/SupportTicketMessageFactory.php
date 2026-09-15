<?php

declare(strict_types=1);

namespace Database\Factories\Support;

use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketMessage>
 */
class SupportTicketMessageFactory extends Factory
{
    protected $model = SupportTicketMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_ticket_id' => SupportTicket::factory(),
            'user_id' => null,
            'message' => fake()->paragraph(),
            'messaged_at' => now(),
        ];
    }
}
