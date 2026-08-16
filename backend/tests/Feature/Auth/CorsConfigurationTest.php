<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Middleware\HandleCors;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Integration test del comportamiento CORS para Sanctum stateful cookies.
 *
 * Valida el contrato publicado en `config/cors.php`:
 *   1. Preflight desde origin whitelisted → `Allow-Credentials: true`.
 *   2. Preflight desde origin NO whitelisted → `Allow-Credentials` ausente.
 *   3. `X-XSRF-TOKEN` está en `allowed-headers` (CR2: Sanctum necesita
 *      enviar este header en POST stateful para CSRF check).
 *
 * Instancia `HandleCors` directamente para evitar que el test framework
 * filtre las requests OPTIONS (Laravel testing no rutea OPTIONS por
 * defecto). En producción el middleware corre vía global pipeline.
 */
#[Group('auth')]
#[Group('cors')]
final class CorsConfigurationTest extends TestCase
{
    /**
     * Construye un preflight CORS request con los headers necesarios.
     *
     * @param  array<string, string>  $extraServer
     */
    private function makePreflightRequest(
        string $origin,
        string $path = '/api/auth/me',
        string $requestHeaders = 'content-type,x-xsrf-token',
        array $extraServer = [],
    ): Request {
        return Request::create(
            uri: $origin . $path,
            method: 'OPTIONS',
            server: array_merge([
                'HTTP_ORIGIN' => $origin,
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => $requestHeaders,
            ], $extraServer),
        );
    }

    /**
     * Ejecuta HandleCors contra una request y devuelve el response con
     * headers CORS aplicados.
     */
    private function runCors(Request $request): Response
    {
        $cors = app(abstract: HandleCors::class);
        $passthrough = fn (Request $req): Response => new Response(content: '', status: 200);

        return $cors->handle(request: $request, next: $passthrough);
    }

    #[Test]
    public function preflight_from_allowed_origin_returns_allow_credentials(): void
    {
        $origin = 'http://panda-estampados.test';

        $request = $this->makePreflightRequest(origin: $origin);

        $response = $this->runCors(request: $request);

        // Allow-Credentials debe estar presente y valer true (CR2: las
        // cookies httpOnly requieren credentials=true para que el browser
        // las incluya en responses cross-origin).
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    #[Test]
    public function preflight_from_non_allowed_origin_omits_allow_credentials(): void
    {
        // Host NO está en config/cors.php allowed_origins.
        $origin = 'http://evil.example.com';

        $request = $this->makePreflightRequest(origin: $origin);

        $response = $this->runCors(request: $request);

        // Allow-Credentials NUNCA debe estar presente para un origin no
        // whitelisted (CORS spec + browser reject wildcard+credentials).
        $this->assertNotSame(
            'true',
            $response->headers->get('Access-Control-Allow-Credentials'),
            'Origin no whitelisted NUNCA debe recibir Allow-Credentials: true.',
        );
    }

    #[Test]
    public function cors_config_allows_xsrf_token_request_header(): void
    {
        $origin = 'http://panda-estampados.test';

        // Preflight con X-XSRF-TOKEN en Access-Control-Request-Headers.
        $request = $this->makePreflightRequest(
            origin: $origin,
            requestHeaders: 'content-type,x-xsrf-token',
        );

        $response = $this->runCors(request: $request);

        // CR2: X-XSRF-TOKEN debe estar en allowed_headers para que el
        // browser lo envíe en POST stateful.
        $allowedHeaders = $response->headers->get('Access-Control-Allow-Headers');

        $this->assertNotNull($allowedHeaders);
        $this->assertStringContainsString('x-xsrf-token', strtolower($allowedHeaders));
    }
}