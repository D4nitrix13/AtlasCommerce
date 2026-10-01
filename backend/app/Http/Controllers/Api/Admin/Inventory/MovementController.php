<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Inventory\MovementResource;
use App\Models\Inventory\InventoryMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Movimientos de inventario (F5.5).
 *
 * Endpoint de sólo lectura (read-only) sobre `inventory_movements`.
 * Filtros:
 *  - type (purchase|sale|adjustment|return)
 *  - product_id (a través del join con inventories)
 *  - from / to (fechas ISO sobre movement_date)
 */
class MovementController extends Controller
{
    /**
     * GET /admin/inventory/movements
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryMovement::query()
            ->with(['inventory.product', 'user']);

        if ($type = $request->query('type')) {
            $query->byType(type: (string) $type);
        }

        if ($productId = $request->query('product_id')) {
            $query->whereHas('inventory', function ($q) use ($productId): void {
                $q->where(column: 'product_id', operator: '=', value: (int) $productId);
            });
        }

        if ($from = $request->query('from')) {
            $query->where(column: 'movement_date', operator: '>=', value: (string) $from);
        }

        if ($to = $request->query('to')) {
            $query->where(column: 'movement_date', operator: '<=', value: (string) $to);
        }

        $movements = $query->latest()->limit(200)->get();

        return response()->json(data: [
            'data' => MovementResource::collection(resource: $movements)->resolve(),
        ]);
    }

    /**
     * GET /admin/inventory/movements/{movement}
     */
    public function show(InventoryMovement $movement): JsonResponse
    {
        $movement->load(['inventory.product', 'user']);

        return response()->json(data: [
            'data' => (new MovementResource(resource: $movement))->resolve(),
        ]);
    }
}
