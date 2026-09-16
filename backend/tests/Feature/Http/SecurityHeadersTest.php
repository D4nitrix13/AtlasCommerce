<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del middleware `SecurityHeaders` (RF-SEC-017 + RF-SEC-021).
 *
 * Cubre:
 *  - CSP presente y restrictiva (default-src 'self').
 *  - HSTS presente en producción, ausente en dev.
 *  - X-Frame-Options: DENY (cubre RF-SEC-021 admin).
 *  - X-Content-Type-Options: nosniff.
 *  - Referrer-Policy: strict-origin-when-cross-origin.
 *  - Permissions-Policy: bloquea features no usadas.
 *  - Headers presentes tanto en responses storefront como admin.
 */
#[Group('security')]
#[Group('headers')]
final class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($currency, 'currency')->create();

        $this->host = "{$this->store->slug}.test";
        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => $this->host,
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function storefront_response_has_all_security_headers(): void
    {
        $response = $this->getJson(uri: "http://{$this->host}/api/storefront/promotions");

        $response->assertOk();

        // CSP
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);

        // X-Frame-Options: DENY (RF-SEC-021)
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));

        // X-Content-Type-Options: nosniff
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        // Referrer-Policy
        $this->assertSame(
            'strict-origin-when-cross-origin',
            $response->headers->get('Referrer-Policy'),
        );

        // Permissions-Policy
        $permissions = $response->headers->get('Permissions-Policy');
        $this->assertNotNull($permissions);
        $this->assertStringContainsString('geolocation=()', $permissions);
        $this->assertStringContainsString('microphone=()', $permissions);
        $this->assertStringContainsString('camera=()', $permissions);
    }

    #[Test]
    public function hsts_header_absent_in_test_environment(): void
    {
        // APP_ENV=testing por defecto en phpunit.xml → no HSTS.
        $response = $this->getJson(uri: "http://{$this->host}/api/storefront/promotions");

        $response->assertOk();
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function hsts_header_present_in_production(): void
    {
        // Forzar production y verificar HSTS.
        $this->app->detectEnvironment(fn (): string => 'production');

        $response = $this->getJson(uri: "http://{$this->host}/api/storefront/promotions");

        $response->assertOk();
        $hsts = $response->headers->get('Strict-Transport-Security');
        $this->assertNotNull($hsts);
        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
    }

    #[Test]
    public function csp_does_not_allow_unsafe_object_sources(): void
    {
        $response = $this->getJson(uri: "http://{$this->host}/api/storefront/promotions");

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString("object-src *", $csp);
    }

    #[Test]
    public function x_frame_options_is_deny_for_admin_paths(): void
    {
        // Test con un path admin (404 porque host no existe, pero el
        // middleware debería ejecutarse antes del route binding y los
        // headers deberían estar en el response).
        $response = $this->getJson(uri: 'http://admin.test/api/admin/stores');

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
    }
}
