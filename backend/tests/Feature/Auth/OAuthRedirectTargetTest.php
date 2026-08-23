<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Support\Auth\OAuthRedirectTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Helpers que arman la URL de retorno del callback OAuth.
 *
 * PR 2a los introduce porque el controller de PR 2b los usa para
 * construir el `?flash=` redirect path en el catch block.
 */
#[Group('social-auth')]
#[Group('helpers')]
#[CoversClass(OAuthRedirectTarget::class)]
final class OAuthRedirectTargetTest extends TestCase
{
    // ---------------------------------------------------------------------
    // extractLocaleFromPath
    // ---------------------------------------------------------------------

    #[Test]
    #[DataProvider('localeExtractCases')]
    public function extract_locale_from_path(string $input, ?string $expected): void
    {
        $this->assertSame(expected: $expected, actual: OAuthRedirectTarget::extractLocaleFromPath($input));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function localeExtractCases(): array
    {
        return [
            'es admin login' => ['/es/admin/auth/login', 'es'],
            'en account login' => ['/en/account/auth/login', 'en'],
            'no locale admin' => ['/admin/auth/login', null],
            'no locale account' => ['/account/auth/login', null],
            'root' => ['/', null],
            'deep path with locale' => ['/es/some/deep/path', 'es'],
            'deep path without locale' => ['/some/deep/path', null],
            'unknown locale segment' => ['/xx/admin/auth/login', null],
            'relative path' => ['relative/path', null],
        ];
    }

    // ---------------------------------------------------------------------
    // stripLocaleFromPath
    // ---------------------------------------------------------------------

    #[Test]
    #[DataProvider('localeStripCases')]
    public function strip_locale_from_path(string $input, string $expected): void
    {
        $this->assertSame(expected: $expected, actual: OAuthRedirectTarget::stripLocaleFromPath($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function localeStripCases(): array
    {
        return [
            'es admin login' => ['/es/admin/auth/login', '/admin/auth/login'],
            'en account login' => ['/en/account/auth/login', '/account/auth/login'],
            'no locale admin' => ['/admin/auth/login', '/admin/auth/login'],
            'no locale account' => ['/account/auth/login', '/account/auth/login'],
            'root' => ['/', '/'],
            'deep path with locale' => ['/es/some/deep/path', '/some/deep/path'],
            'deep path without locale' => ['/some/deep/path', '/some/deep/path'],
        ];
    }

    #[Test]
    public function strip_locale_from_path_preserves_query_string(): void
    {
        // El helper se enfoca en el path. Si viene con query string
        // el caller debe split-ear primero. Documentamos el contrato.
        $path = parse_url('/es/admin/auth/login?flash=foo', PHP_URL_PATH);
        $this->assertSame(
            expected: '/admin/auth/login',
            actual: OAuthRedirectTarget::stripLocaleFromPath($path),
        );
    }

    // ---------------------------------------------------------------------
    // isAdminHost
    // ---------------------------------------------------------------------

    #[Test]
    public function is_admin_host_matches_admin_subdomain(): void
    {
        $this->assertTrue(OAuthRedirectTarget::isAdminHost(host: 'admin.panda-estampados.com'));
        $this->assertTrue(OAuthRedirectTarget::isAdminHost(host: 'admin.kitsune.com'));
        $this->assertTrue(OAuthRedirectTarget::isAdminHost(host: 'admin.panda-estampados.test'));
    }

    #[Test]
    public function is_admin_host_returns_false_for_storefront(): void
    {
        $this->assertFalse(OAuthRedirectTarget::isAdminHost(host: 'panda-estampados.com'));
        $this->assertFalse(OAuthRedirectTarget::isAdminHost(host: 'kitsune.com'));
        $this->assertFalse(OAuthRedirectTarget::isAdminHost(host: 'www.panda-estampados.com'));
        $this->assertFalse(OAuthRedirectTarget::isAdminHost(host: 'localhost'));
    }

    #[Test]
    public function is_admin_host_is_case_insensitive(): void
    {
        $this->assertTrue(OAuthRedirectTarget::isAdminHost(host: 'Admin.Panda-Estampados.com'));
        $this->assertTrue(OAuthRedirectTarget::isAdminHost(host: 'ADMIN.PANDA-ESTAMPADOS.COM'));
    }

    // ---------------------------------------------------------------------
    // fromRequest
    // ---------------------------------------------------------------------

    #[Test]
    public function from_request_preserves_admin_login_path_with_locale(): void
    {
        $result = OAuthRedirectTarget::fromRequest(
            originalPath: '/es/admin/auth/login',
            host: 'admin.panda-estampados.com',
        );

        // El locale se preserva: si el browser estaba en /es/admin/auth/login,
        // el callback failure redirect vuelve a la misma URL (no a /admin/auth/login
        // que terminaría concatenado en /es/admin/admin/auth/login → 404).
        $this->assertSame(expected: '/es/admin/auth/login', actual: $result);
    }

    #[Test]
    public function from_request_preserves_account_login_path_with_locale(): void
    {
        $result = OAuthRedirectTarget::fromRequest(
            originalPath: '/en/account/auth/login',
            host: 'kitsune.com',
        );

        $this->assertSame(expected: '/en/account/auth/login', actual: $result);
    }

    #[Test]
    public function from_request_works_without_locale_prefix(): void
    {
        // Host sin locale en el routing (ej: ruta legacy `/admin/auth/login`).
        $result = OAuthRedirectTarget::fromRequest(
            originalPath: '/admin/auth/login',
            host: 'admin.panda-estampados.com',
        );

        $this->assertSame(expected: '/admin/auth/login', actual: $result);
    }

    #[Test]
    public function from_request_falls_back_to_admin_login_for_admin_subdomain(): void
    {
        // Si el path NO es un login URL (caso raro: alguien guardó
        // una URL arbitraria en session), caemos al login del host.
        $result = OAuthRedirectTarget::fromRequest(
            originalPath: '/es/admin/dashboard',
            host: 'admin.panda-estampados.com',
        );

        $this->assertSame(expected: '/es/admin/auth/login', actual: $result);
    }

    #[Test]
    public function from_request_falls_back_to_account_login_for_storefront(): void
    {
        $result = OAuthRedirectTarget::fromRequest(
            originalPath: '/es/',
            host: 'panda-estampados.com',
        );

        $this->assertSame(expected: '/es/account/auth/login', actual: $result);
    }
}
