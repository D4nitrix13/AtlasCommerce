<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Exceptions\SocialAuth\OAuthAdminSignupDisabledException;
use App\Exceptions\SocialAuth\OAuthEmailConflictException;
use App\Exceptions\SocialAuth\OAuthEmailUnverifiedException;
use App\Exceptions\SocialAuth\OAuthException;
use App\Exceptions\SocialAuth\OAuthProviderNotConfiguredException;
use App\Exceptions\SocialAuth\OAuthStateMismatchException;
use App\Exceptions\SocialAuth\OAuthStateMissingException;
use App\Http\Resources\Auth\OAuthErrorResource;
use App\Models\Auth\SocialAccount;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Cubre la capa de foundation del SDD oauth-google-facebook-login:
 *
 *   - 6 excepciones + base abstracta (códigos estables + extras)
 *   - `OAuthErrorResource::from()` (envelope JSON uniforme)
 *   - `SocialAccount` model + factory + `User::socialAccounts()`
 *
 * Las migraciones tienen su propio test (SocialAccountsMigrationTest).
 * El controller + service tienen tests separados en este mismo PR.
 */
#[Group('social-auth')]
#[Group('foundation')]
final class OAuthFoundationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // Excepciones
    // ---------------------------------------------------------------------

    #[Test]
    public function oauth_email_conflict_exception_carries_code_and_email(): void
    {
        $e = new OAuthEmailConflictException(email: 'carlos@example.com');

        $this->assertSame(expected: 'oauth_email_conflict', actual: $e->oauthCode());
        $this->assertSame(expected: 'carlos@example.com', actual: $e->extras()['email']);
        $this->assertStringContainsString('carlos@example.com', $e->getMessage());
        $this->assertInstanceOf(OAuthException::class, $e);
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    #[Test]
    public function oauth_email_unverified_exception_carries_code_and_provider(): void
    {
        $e = new OAuthEmailUnverifiedException(provider: 'google');

        $this->assertSame(expected: 'oauth_email_unverified', actual: $e->oauthCode());
        $this->assertSame(expected: 'google', actual: $e->extras()['provider']);
    }

    #[Test]
    public function oauth_admin_signup_disabled_exception_carries_code_and_provider(): void
    {
        $e = new OAuthAdminSignupDisabledException(provider: 'facebook');

        $this->assertSame(expected: 'oauth_admin_signup_disabled', actual: $e->oauthCode());
        $this->assertSame(expected: 'facebook', actual: $e->extras()['provider']);
    }

    #[Test]
    public function oauth_state_mismatch_exception_carries_code(): void
    {
        $e = new OAuthStateMismatchException(provider: 'google');

        $this->assertSame(expected: 'oauth_state_mismatch', actual: $e->oauthCode());
        $this->assertSame(expected: 'google', actual: $e->extras()['provider']);
    }

    #[Test]
    public function oauth_state_missing_exception_uses_required_code(): void
    {
        // Nota: el código es `oauth_state_required` (no `_missing`) —
        // matchea el i18n key del frontend (design open Q #6).
        $e = new OAuthStateMissingException(provider: 'google');

        $this->assertSame(expected: 'oauth_state_required', actual: $e->oauthCode());
        $this->assertSame(expected: 'google', actual: $e->extras()['provider']);
    }

    #[Test]
    public function oauth_provider_not_configured_exception_carries_code_and_provider(): void
    {
        $e = new OAuthProviderNotConfiguredException(provider: 'google');

        $this->assertSame(expected: 'oauth_provider_not_configured', actual: $e->oauthCode());
        $this->assertSame(expected: 'google', actual: $e->extras()['provider']);
    }

    // ---------------------------------------------------------------------
    // OAuthErrorResource
    // ---------------------------------------------------------------------

    #[Test]
    public function oauth_error_resource_serializes_known_exception_with_code_and_extras(): void
    {
        $e = new OAuthEmailConflictException(email: 'carlos@example.com');
        $resource = OAuthErrorResource::from($e);

        $payload = $resource->toArray(request: $this->createRequest());

        $this->assertSame(expected: 'oauth_email_conflict', actual: $payload['code']);
        $this->assertSame(expected: 'carlos@example.com', actual: $payload['email']);
        $this->assertIsString($payload['message']);
        $this->assertNotEmpty($payload['message']);
    }

    #[Test]
    public function oauth_error_resource_falls_back_to_generic_for_unknown_throwable(): void
    {
        $e = new RuntimeException(message: 'something went wrong internally with sensitive details');
        $resource = OAuthErrorResource::from($e);

        $payload = $resource->toArray(request: $this->createRequest());

        $this->assertSame(expected: 'oauth_error', actual: $payload['code']);
        // Mensaje sanitizado — NO debe leakear el detalle original.
        $this->assertStringNotContainsString('sensitive details', $payload['message']);
    }

    private function createRequest(): \Illuminate\Http\Request
    {
        return \Illuminate\Http\Request::create(uri: '/api/auth/social/google/redirect');
    }

    // ---------------------------------------------------------------------
    // SocialAccount model + relation
    // ---------------------------------------------------------------------

    #[Test]
    public function social_account_factory_creates_a_row_bound_to_a_user(): void
    {
        $user = User::factory()->create();
        $account = SocialAccount::factory()
            ->forProvider(provider: 'google', providerUserId: 'g-12345')
            ->for($user)
            ->create();

        $this->assertSame(expected: 'google', actual: $account->provider);
        $this->assertSame(expected: 'g-12345', actual: $account->provider_user_id);
        $this->assertSame(expected: $user->id, actual: $account->user_id);
        $this->assertTrue($user->is($account->user));
    }

    #[Test]
    public function user_social_accounts_relation_returns_has_many(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory()->forProvider('google', 'g-a')->for($user)->create();
        SocialAccount::factory()->forProvider('facebook', 'f-b')->for($user)->create();

        $providers = $user->socialAccounts()->pluck('provider')->sort()->values()->all();

        $this->assertSame(expected: ['facebook', 'google'], actual: $providers);
    }
}
