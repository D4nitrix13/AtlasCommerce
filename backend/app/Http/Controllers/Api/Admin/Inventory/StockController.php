<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\Admin\StockAdjustmentRequest;
use App\Http\Resources\Admin\Inventory\StockResource;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\InventoryMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stock de inventario (F5.5).
 *
 * Reglas:
 *  - index con filtro opcional `?low=1` (sólo registros con stock bajo).
 *  - update aplica un `adjustment` (delta) y registra un `InventoryMovement`.
 */
class StockController extends Controller
{
    /**
     * GET /admin/inventory/stock
     */
    public function index(Request $request): JsonResponse
    {
        $query = Inventory::query()->with(['product', 'warehouse']);

        if ($request->boolean('low')) {
            // NOTE: evitamos el scope `lowStock()` del modelo (bug pre-existente
            // que pasa `column2` inválido a `where()`); usamos `whereColumn()`.
            $query->whereColumn('current_stock', '<=', 'minimum_stock');
        }

        if ($productId = $request->query('product_id')) {
            $query->byProduct(productId: (int) $productId);
        }

        if ($warehouseId = $request->query('warehouse_id')) {
            $query->byWarehouse(warehouseId: (int) $warehouseId);
        }

        $stocks = $query->orderBy('id')->limit(200)->get();

        return response()->json(data: [
            'data' => StockResource::collection(resource: $stocks)->resolve(),
        ]);
    }

    /**
     * GET /admin/inventory/stock/{stock}
     */
    public function show(Inventory $stock): JsonResponse
    {
        $stock->load(['product', 'warehouse']);

        return response()->json(data: [
            'data' => (new StockResource(resource: $stock))->resolve(),
        ]);
    }

    /**
     * PUT /admin/inventory/stock/{stock} — aplica adjustment + registra movement.
     */
    public function update(StockAdjustmentRequest $request, Inventory $stock): JsonResponse
    {
        $adjustment = (int) $request->integer('adjustment');
        $previousStock = (int) $stock->current_stock;
        $newStock = $previousStock + $adjustment;

        abort_if($newStock < 0, 422, 'El stock no puede quedar negativo.');

        DB::transaction(function () use ($stock, $adjustment, $previousStock, $newStock, $request): void {
            $stock->update(['current_stock' => $newStock]);

            InventoryMovement::query()->create([
                'inventory_id' => $stock->id,
                'user_id' => $request->user()?->id,
                'movement_type' => 'adjustment',
                'quantity' => $adjustment,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reason' => $request->input('reason'),
                'movement_date' => now(),
            ]);
        });

        $stock->refresh()->load(['product', 'warehouse']);

        return response()->json(data: [
            'data' => (new StockResource(resource: $stock))->resolve(),
        ], status: Response::HTTP_OK);
    }
}
