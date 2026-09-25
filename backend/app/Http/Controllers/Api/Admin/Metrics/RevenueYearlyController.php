<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Metrics;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint de métricas: revenue anual (current vs previous year).
 *
 * Alimenta RevenueOverviewCard (frontend/src/components/admin/
 * reports/RevenueOverviewCard.astro). Query SQL real — no MOCK.
 *
 * Tenant scoping: automatico via middleware store.domain + global
 * scope `BelongsToStore` del modelo Order. Las orders de otros
 * tenants NO se incluyen.
 *
 * Performance:
 *   - Sin eager loads (solo necesitamos total_cents + ordered_at)
 *   - WHERE ordered_at BETWEEN ... AND ... + GROUP BY mes
 *   - Indice recomendado: orders(store_id, ordered_at, status)
 */
class RevenueYearlyController extends Controller
{
    /**
     * Devuelve revenue mensual del año actual y anterior comparados.
     *
     * Query: SUM(total_cents) por mes, dos años consecutivos (current y previous),
     * solo orders con status in (pending, processing, completed) — `cancelled`
     * se excluye porque no son revenue real.
     *
     * Si el tenant no tiene orders, devuelve shape vacio (months con 0s).
     *
     * @return JsonResponse { data: { year, previous_year, labels[],
     *                          current_year_values[], previous_year_values[] } }
     */
    public function __invoke(): JsonResponse
    {
        $now = now();
        $currentYear = (int) $now->format('Y');
        $previousYear = $currentYear - 1;

        $start = now()->create($previousYear, 1, 1)->startOfDay();
        $end = $now->copy()->endOfDay();

        $revenueByMonth = DB::table('orders')
            ->selectRaw("
                EXTRACT(YEAR FROM ordered_at)::int as year,
                EXTRACT(MONTH FROM ordered_at)::int as month,
                COALESCE(SUM(total_cents), 0)::bigint as total_cents
            ")
            ->whereIn('status', ['pending', 'processing', 'completed'])
            ->whereBetween('ordered_at', [$start, $end])
            ->groupBy('year', 'month')
            ->get();

        $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $currentValues = array_fill(0, 12, 0);
        $previousValues = array_fill(0, 12, 0);

        foreach ($revenueByMonth as $row) {
            $idx = (int) $row->month - 1;
            if ($idx < 0 || $idx > 11) {
                continue;
            }
            if ((int) $row->year === $currentYear) {
                $currentValues[$idx] = (int) $row->total_cents;
            } elseif ((int) $row->year === $previousYear) {
                $previousValues[$idx] = (int) $row->total_cents;
            }
        }

        return response()->json(data: [
            'data' => [
                'year' => $currentYear,
                'previous_year' => $previousYear,
                'labels' => $labels,
                'current_year_values' => $currentValues,
                'previous_year_values' => $previousValues,
            ],
        ]);
    }
}
