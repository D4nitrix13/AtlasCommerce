<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que aplica cabeceras de seguridad HTTP a todas las
 * responses del backend (RF-SEC-017 + RF-SEC-021).
 *
 * Headers aplicados (defense-in-depth — el Caddyfile del edge ya los
 * emite para responses HTML del frontend; este middleware los re-aplica
 * para responses API JSON del backend, asegurando que cualquier
 * endpoint que retorne HTML accidental también esté protegido):
 *
 *  - Content-Security-Policy: default-src 'self', sin object-src, sin
 *    frame-ancestors cross-origin. Para responses JSON es no-op (el
 *    browser no aplica CSP a non-HTML), pero si alguna vez un endpoint
 *    sirve HTML, queda protegido.
 *  - Strict-Transport-Security: max-age=1 año + includeSubDomains.
 *    Solo en producción (HTTPS). En dev (HTTP) el header es omitido
 *    porque no tiene sentido forzar HTTPS en local.
 *  - X-Frame-Options: DENY (RF-SEC-021 cubre admin; aplicamos DENY
 *    global por simplicidad — si el storefront necesita iframes
 *    same-origin, se puede refinar a un scope por ruta).
 *  - X-Content-Type-Options: nosniff.
 *  - Referrer-Policy: strict-origin-when-cross-origin.
 *  - Permissions-Policy: bloquea features del browser que no usamos
 *    (geolocation, microphone, camera, etc.) — defense-in-depth contra
 *    XSS que lograra ejecutar JS.
 *
 * Tests: ver tests/Feature/Http/SecurityHeadersTest.php.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // CSP: default-src 'self' + sin object-src. JSON responses
        // ignoran este header (es solo para documentos HTML).
        $response->headers->set(
            key: 'Content-Security-Policy',
            values: "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: https:; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' https: https://fonts.googleapis.com https://fonts.gstatic.com; frame-ancestors 'none'; form-action 'self'; base-uri 'self'; object-src 'none'",
        );

        // HSTS: solo en producción (HTTPS). En dev local sería no-op
        // pero igual lo emitimos para validar el header.
        if (app()->environment('production')) {
            $response->headers->set(
                key: 'Strict-Transport-Security',
                values: 'max-age=31536000; includeSubDomains',
            );
        }

        // X-Frame-Options: DENY global. Cubre RF-SEC-021 (admin) y
        // defensa general contra clickjacking en storefront.
        $response->headers->set(key: 'X-Frame-Options', values: 'DENY');

        // X-Content-Type-Options: nosniff.
        $response->headers->set(key: 'X-Content-Type-Options', values: 'nosniff');

        // Referrer-Policy: solo origin cross-origin.
        $response->headers->set(
            key: 'Referrer-Policy',
            values: 'strict-origin-when-cross-origin',
        );

        // Permissions-Policy: bloquea features del browser no usadas.
        $response->headers->set(
            key: 'Permissions-Policy',
            values: 'geolocation=(), microphone=(), camera=(), payment=(self), usb=(), magnetometer=(), gyroscope=(), accelerometer=()',
        );

        return $response;
    }
}
