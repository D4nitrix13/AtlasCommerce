<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Metrics;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint de métricas: traffic sources (referrers de visitas).
 *
 * Alimenta TrafficOverviewCard + TrafficSourcesCard del frontend.
 *
 * **STUB**: AtlasCommerce no tiene tracking de referrers integrado
 * todavia (no hay Plausible/Google Analytics/Matomo). Para tener datos
 * reales, prerequisito es cualquiera de:
 *   - Implementar in-house tracking via middleware + log de referrers
 *   - Integrar Plausible (self-hosted, privacy-friendly)
 *   - Integrar Google Analytics 4 via Measurement Protocol
 *
 * Mientras tanto, devuelve shape vacio pero con la estructura que
 * el frontend espera → el frontend hace fallback a MOCK data.
 *
 * Status: STUB_PENDING_TRACKING_INTEGRATION.
 */
class TrafficSourcesController extends Controller
{
    /**
     * Devuelve shape vacio con la estructura esperada.
     *
     * El frontend (TrafficOverviewCard, TrafficSourcesCard) detecta
     * lista vacia y hace fallback a MOCK data hardcoded.
     *
     * @return JsonResponse { data: { sources: [], top_pages: [] } }
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(data: [
            'data' => [
                'sources' => [],
                'top_pages' => [],
            ],
            // Flag explicito para debugging — frontend no usa este campo
            '_meta' => [
                'stub' => true,
                'reason' => 'Traffic tracking not integrated yet. '.
                    'See Plausible/GA4/Matomo PR backlog.',
            ],
        ]);
    }
}
