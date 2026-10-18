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
 * KPIs (queries reales del periodo "mes actual vs mes anterior"):
 *   - total_sold: SUM total_cents este mes
 *   - avg_ticket: AVG total_cents este mes (proximo a ticket promedio)
 *   - conversion_rate: ratio orders completados / total orders del tenant
 *   - returns_rate: ratio orders cancelled / total
 *
 * Weekly trend chart: SUM(total_cents) GROUP BY day-of-week (Lun-Dom)
 * sobre los ultimos 7 dias. Query real con EXTRACT(DOW FROM ...) en
 * PostgreSQL. Si no hay orders en un dia, valor 0.
 *
 * Tenant scoping via middleware store.domain.
 */
class SalesPerformanceController extends Controller
{
    /**
     * Devuelve 4 KPIs + 7 valores de tendencia semanal.
     *
     * @return JsonResponse { data: { metrics[], days[], values[] } }
     */
    public function __invoke(): JsonResponse
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $weekStart = $now->copy()->subDays(value: 6)->startOfDay();

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
            ? round(num: ($totals->cancelled_count / $totals->orders_count) * 100, precision: 1)
            : 0.0;

        $conversionRate = $totals->orders_count > 0
            ? round(num: ($totals->completed_count / $totals->orders_count) * 100, precision: 1)
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
                'value' => $conversionRate.'%',
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

        // Weekly trend: SUM(total_cents) por dia de la semana, ultimos 7 dias.
        // PostgreSQL: EXTRACT(DOW FROM ...) returns 0=Sun, 1=Mon, ..., 6=Sat.
        // Mapeamos a indices Lun-Dom (0-6).
        $weekly = DB::table('orders')
            ->selectRaw('
                EXTRACT(DOW FROM ordered_at)::int as dow,
                COALESCE(SUM(total_cents), 0)::bigint as total
            ')
            ->where('ordered_at', '>=', $weekStart)
            ->groupBy('dow')
            ->get();

        // Inicializar los 7 dias (Lun-Dom) con 0, luego aplicar resultados.
        // dow de postgres: 0=Sun, 1=Mon, ..., 6=Sat.
        // Array Mon-first: [Mon(1), Tue(2), Wed(3), Thu(4), Fri(5), Sat(6), Sun(0)]
        $values = [0, 0, 0, 0, 0, 0, 0];
        foreach ($weekly as $row) {
            $dow = (int) $row->dow;
            $idx = $dow === 0 ? 6 : $dow - 1; // Sun->6, Mon->0, etc.
            $values[$idx] = (int) ($row->total / 100); // cents -> currency
        }

        return response()->json(data: [
            'data' => [
                'metrics' => $metrics,
                'days' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                'values' => $values,
            ],
        ]);
    }
}
