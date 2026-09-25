<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Metrics;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Endpoint de métricas: productos con stock bajo o critico.
 *
 * Alimenta LowStockTable del dashboard admin (frontend/src/components
 * /admin/dashboard/LowStockTable.astro).
 *
 * Query real cuando la tabla `product_stocks` existe (depende del
 * modulo de inventory instalado). Si no existe, devuelve shape vacio
 * (`data: []`) y el frontend hace fallback a MOCK data.
 */
class LowStockController extends Controller
{
    /**
     * Lista de productos con stock total <= threshold (default 10).
     *
     * Importante: usa Schema::hasTable() ANTES de ejecutar query —
     * si la query falla sobre una tabla inexistente Postgres deja la
     * transaccion en estado aborted (SQLSTATE 25P02), rompiendo
     * queries subsecuentes dentro del mismo transaction.
     *
     * @return JsonResponse { data: [ { id, name, sku, stock, status, status_color } ] }
     */
    public function __invoke(Request $request): JsonResponse
    {
        $threshold = (int) $request->query('threshold', 10);

        if (! Schema::hasTable('product_stocks')) {
            return response()->json(data: ['data' => []]);
        }

        $rows = DB::table('products')
            ->leftJoin('product_stocks', 'products.id', '=', 'product_stocks.product_id')
            ->selectRaw('
                products.id,
                products.name,
                products.sku,
                COALESCE(SUM(product_stocks.quantity), 0)::int as total_stock
            ')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->havingRaw('COALESCE(SUM(product_stocks.quantity), 0) <= ?', [$threshold])
            ->orderBy('total_stock', 'asc')
            ->limit(50)
            ->get();

        $alerts = $rows->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'sku' => (string) $row->sku,
            'stock' => (int) $row->total_stock,
            'status' => match (true) {
                $row->total_stock <= 3 => 'Crítico',
                $row->total_stock <= 5 => 'Bajo',
                default => 'Bajo',
            },
            'status_color' => match (true) {
                $row->total_stock <= 3 => 'bg-rose-100 text-rose-700',
                default => 'bg-amber-100 text-amber-700',
            },
        ])->all();

        return response()->json(data: [
            'data' => $alerts,
        ]);
    }
}
