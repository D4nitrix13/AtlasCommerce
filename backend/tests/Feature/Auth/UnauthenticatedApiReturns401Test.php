<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cubre el bugfix F0.2 — `bootstrap/app.php::redirectGuestsTo(null)`.
 *
 * Síntoma original (audit 2026-10-05):
 *   GET /api/v1/admin/orders/recent SIN header Accept → 500 con stack
 *   trace de `Symfony\Component\Routing\Exception\RouteNotFoundException`:
 *   "Route [login] not defined."
 *
 * Causa:
 *   Laravel 11+ registra por defecto
 *     `redirectGuestsTo(fn () => route('login'))`
 *   en `withMiddleware`. Cuando una ruta API recibe un request sin
 *   `Accept: application/json`, `Authenticate::unauthenticated()`
 *   (vendor) llama a `$this->redirectTo($request)` ANTES de tirar
 *   `AuthenticationException`. Ese callback resuelve `route('login')`,
 *   que NO existe en este proyecto (los endpoints reales son
 *   `/api/v1/auth/login` vía POST, sin nombre `login`), y lanza
 *   `RouteNotFoundException` → 500.
 *
 * Fix:
 *   En `bootstrap/app.php` sobreescribimos el callback:
 *     `$middleware->redirectGuestsTo(static fn (): ?string => null);`
 *   Con redirectTo=null, el handler de excepciones devuelve JSON 401 vía
 *   `shouldRenderJsonWhen` (que ya está configurado para `api/*`).
 *
 * Acceptance (de odd/tasks/dashboards-redesign-and-auth.md F0.2):
 *   "cualquier GET a `/api/v1/admin/*` sin auth → 401 Unauthorized"
 */
#[Group('auth')]
#[Group('bugfix')]
final class UnauthenticatedApiReturns401Test extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_route_returns_401_json_without_accept_header(): void
    {
        // Sin header Accept y sin cookies de sesión. Antes del bugfix era 500.
        $response = $this->get(uri: '/api/v1/admin/orders/recent');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonStructure(['message']);
    }

    #[Test]
    public function admin_route_returns_401_json_with_text_html_accept(): void
    {
        // Algunos proxies / browsers legacy mandan Accept: text/html.
        // Antes del bugfix también era 500.
        $response = $this->get(
            uri: '/api/v1/admin/orders/recent',
            headers: ['Accept' => 'text/html'],
        );

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonStructure(['message']);
    }

    #[Test]
    public function admin_route_returns_401_json_with_application_json_accept(): void
    {
        // Caso que ya funcionaba antes del bugfix, lo cubrimos también
        // para evitar regresión silenciosa si alguien revierte el fix.
        $response = $this->getJson(uri: '/api/v1/admin/orders/recent');

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    }

    #[Test]
    public function account_route_returns_401_json_without_accept_header(): void
    {
        // El fix es global — cubre TODA ruta bajo auth:sanctum, no
        // sólo admin. Las rutas /api/v1/account/* (account/addresses)
        // también devolvían 500 con el bug.
        $response = $this->get(uri: '/api/v1/account/addresses');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/json');
    }

    #[Test]
    public function storefront_route_under_store_context_returns_401_json(): void
    {
        // Tracking es público, pero /account/data-export requiere auth
        // y vivía bajo el mismo bug.
        $response = $this->get(uri: '/api/v1/account/data-export');

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    }
}
