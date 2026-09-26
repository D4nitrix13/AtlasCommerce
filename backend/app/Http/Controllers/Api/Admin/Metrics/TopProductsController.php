<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Metrics;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint de métricas: top productos por revenue (ultimos N dias).
 *
 * Alimenta widget opcional del dashboard admin (no existe hoy; placeholder
 * para cuando se agregue un widget de top sellers). Tambien util para
 * analytics en reports page.
 *
 * Query real: SUM(order_items.subtotal_cents) GROUP BY product_id,
 * limitado a los ultimos N dias. Filtra orders cancelled (no son revenue
 * real) y orders con status pending/processing/completed.
 */
class TopProductsController extends Controller
{
    private const DEFAULT_LIMIT = 5;

    private const MAX_LIMIT = 20;

    private const DEFAULT_PERIOD_DAYS = 30;

    /**
     * Top productos por revenue.
     *
     * Query params:
     *   - limit (int, default 5, max 20): cantidad de productos
     *   - period_days (int, default 30): ventana de tiempo hacia atras
     *
     * @return JsonResponse { data: [{ id, name, units_sold, revenue_cents }] }
     */
    public function __invoke(Request $request): JsonResponse
    {
        $limit = min(
            (int) $request->query(key: 'limit', default: self::DEFAULT_LIMIT),
            self::MAX_LIMIT,
        );

        $periodDays = (int) $request->query(
            key: 'period_days',
            default: self::DEFAULT_PERIOD_DAYS,
        );

        $start = now()->subDays(value: $periodDays)->startOfDay();

        $rows = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->selectRaw('
                products.id as product_id,
                products.name as product_name,
                SUM(order_items.quantity)::int as units_sold,
                SUM(order_items.subtotal_cents)::bigint as revenue_cents
            ')
            ->whereIn('orders.status', ['pending', 'processing', 'completed'])
            ->where('orders.ordered_at', '>=', $start)
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue_cents')
            ->limit($limit)
            ->get();

        $data = $rows->map(fn ($row) => [
            'id' => (int) $row->product_id,
            'name' => (string) $row->product_name,
            'units_sold' => (int) $row->units_sold,
            'revenue_cents' => (int) $row->revenue_cents,
            'revenue_label' => '$'.number_format(num: $row->revenue_cents / 100, decimals: 2),
        ])->all();

        return response()->json(data: [
            'data' => $data,
            'meta' => [
                'period_days' => $periodDays,
                'limit' => $limit,
            ],
        ]);
    }
}
