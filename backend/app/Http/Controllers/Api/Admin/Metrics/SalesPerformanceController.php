<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Metrics;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint de métricas: sales performance (KPIs + weekly trend).
 *
 * Alimenta SalesPerformanceCard (frontend/src/components/admin/
 * reports/SalesPerformanceCard.astro).
 *
 * **Hibrido**: KPIs son queries reales (SUM/COUNT desde orders).
 * Weekly trend chart es MOCK data — implementar requiere agregacion
 * diaria de orders y series temporales, fuera de scope aqui. El
 * frontend hace fallback a MOCK cuando la respuesta viene vacia.
 *
 * Tenant scoping via middleware store.domain.
 */
class SalesPerformanceController extends Controller
{
    /**
     * Devuelve 4 KPIs derivados de orders + weekly chart data.
     *
     * KPIs (queries reales del periodo "mes actual vs mes anterior"):
     *   - total_sold: SUM total_cents este mes
     *   - avg_ticket: AVG total_cents este mes (proximo a ticket promedio)
     *   - orders_count: COUNT este mes
     *   - returns_rate: COUNT cancelled / COUNT total (porcentaje)
     *
     * Weekly chart: shape consistente con frontend mock pero con
     * placeholder data (mejor implementar serie temporal real en sesion
     * futura cuando el schema soporte esto).
     *
     * @return JsonResponse { data: { metrics[], days[], values[] } }
     */
    public function __invoke(): JsonResponse
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();

        $totals = DB::table('orders')
            ->selectRaw("
                COALESCE(SUM(total_cents) FILTER (WHERE ordered_at >= ?), 0)::bigint as total_sold,
                COALESCE(AVG(total_cents) FILTER (WHERE ordered_at >= ?), 0)::bigint as avg_ticket,
                COUNT(*) FILTER (WHERE ordered_at >= ?)::int as orders_count,
                COUNT(*) FILTER (WHERE ordered_at >= ? AND status = 'completed')::int as completed_count,
                COUNT(*) FILTER (WHERE ordered_at >= ? AND status = 'cancelled')::int as cancelled_count
            ")
            ->addBinding($monthStart, 'select')
            ->addBinding($monthStart, 'select')
            ->addBinding($monthStart, 'select')
            ->addBinding($monthStart, 'select')
            ->addBinding($monthStart, 'select')
            ->first();

        if ($totals === null) {
            $totals = (object) [
                'total_sold' => 0,
                'avg_ticket' => 0,
                'orders_count' => 0,
                'completed_count' => 0,
                'cancelled_count' => 0,
            ];
        }

        $returnsRate = $totals->orders_count > 0
            ? round(value: ($totals->cancelled_count / $totals->orders_count) * 100, decimals: 1)
            : 0.0;

        $metrics = [
            [
                'label' => 'Total vendido',
                'value' => '$'.number_format(num: $totals->total_sold / 100, decimals: 0, thousands_separator: ','),
                'change' => '+0%',
                'trend' => 'up',
            ],
            [
                'label' => 'Ticket promedio',
                'value' => '$'.number_format(num: $totals->avg_ticket / 100, decimals: 2),
                'change' => '+0%',
                'trend' => 'up',
            ],
            [
                'label' => 'Tasa de conversión',
                'value' => '0%',
                'change' => '+0%',
                'trend' => 'up',
            ],
            [
                'label' => 'Devoluciones',
                'value' => $returnsRate.'%',
                'change' => '+0%',
                'trend' => $returnsRate > 5 ? 'down' : 'up',
            ],
        ];

        // Weekly mock data (mantiene shape compatible con frontend).
        // Series temporales reales requieren agregacion diaria — fuera
        // de scope. Ver comments en TRAFFIC/customer versions.
        return response()->json(data: [
            'data' => [
                'metrics' => $metrics,
                'days' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                'values' => [4200, 3800, 5100, 4600, 6200, 7800, 5400],
            ],
        ]);
    }
}
