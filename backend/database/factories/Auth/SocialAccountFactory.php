<?php

declare(strict_types=1);

namespace Database\Factories\Auth;

use App\Models\Auth\SocialAccount;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'google',
            'provider_user_id' => $this->faker->unique()->numerify('##########'),
        ];
    }

    /**
     * Fija el provider + provider_user_id para que el binding
     * refleje un par real (evita colisiones con la unique composite).
     */
    public function forProvider(string $provider, string $providerUserId): static
    {
        return $this->state(state: fn (array $attributes): array => [
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
        ]);
    }
}
