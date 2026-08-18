<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\SocialAccount;
use App\Models\Auth\User;
use App\Services\Auth\SocialAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cubre el skeleton del `SocialAccountService` introducido en PR 1.
 *
 * PR 1 solo expone `findByProviderId` (lectura). Las operaciones de
 * escritura (`createSocialAccount`, `findOrCreateFromProvider`) se
 * completan en PR 2 con cobertura completa de los 7 escenarios del
 * spec (A3-A8, A12).
 */
#[Group('social-auth')]
#[Group('service')]
#[CoversClass(SocialAccountService::class)]
final class SocialAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function find_by_provider_id_returns_null_when_missing(): void
    {
        $service = $this->app->make(SocialAccountService::class);

        $result = $service->findByProviderId(provider: 'google', providerUserId: 'g-does-not-exist');

        $this->assertNull($result);
    }

    #[Test]
    public function find_by_provider_id_returns_bound_social_account_when_exists(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory()
            ->forProvider(provider: 'google', providerUserId: 'g-real')
            ->for($user)
            ->create();

        $service = $this->app->make(SocialAccountService::class);

        $result = $service->findByProviderId(provider: 'google', providerUserId: 'g-real');

        $this->assertNotNull($result);
        $this->assertSame(expected: 'google', actual: $result->provider);
        $this->assertSame(expected: 'g-real', actual: $result->provider_user_id);
        $this->assertTrue($user->is($result->user));
    }

    #[Test]
    public function find_by_provider_id_does_not_match_different_provider(): void
    {
        // Un SocialAccount para Facebook no debe matchear una búsqueda
        // por 'google' con el mismo provider_user_id (defense-in-depth
        // contra colisiones cross-provider).
        $user = User::factory()->create();
        SocialAccount::factory()
            ->forProvider(provider: 'facebook', providerUserId: 'shared-id')
            ->for($user)
            ->create();

        $service = $this->app->make(SocialAccountService::class);

        $result = $service->findByProviderId(provider: 'google', providerUserId: 'shared-id');

        $this->assertNull($result);
    }
}
