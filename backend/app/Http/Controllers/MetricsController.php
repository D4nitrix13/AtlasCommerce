<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Metrics\MetricsService;
use Illuminate\Http\Response;
use Prometheus\RenderTextFormat;

/**
 * Endpoint que expone las métricas en formato Prometheus (RF-OBS-132).
 *
 * Ruta: GET /metrics
 *
 * Diseño:
 *  - Sin middleware de auth (Caddy/nginx filtran el acceso por IP —
 *    Prometheus típicamente corre en red privada y scrapea este path).
 *  - Devuelve `text/plain; version=0.0.4` que es el content type
 *    estándar que esperan Prometheus / Grafana Agent.
 *  - NO cachear — Prometheus espera datos frescos en cada scrape.
 *
 * Si el storage adapter falla (ej: Redis caído), el endpoint debe
 * seguir respondiendo con un error visible en lugar de 500 silencioso —
 * así Prometheus sabe que algo está mal.
 */
final class MetricsController extends Controller
{
    public function __construct(private readonly MetricsService $metrics) {}

    public function __invoke(): Response
    {
        $renderer = new RenderTextFormat;
        $body = $renderer->render($this->metrics->registry()->getMetricFamilySamples());

        return response(
            content: $body,
            status: 200,
            headers: [
                'Content-Type' => RenderTextFormat::MIME_TYPE,
            ],
        );
    }
}
