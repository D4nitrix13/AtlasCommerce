<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\Admin\WarehouseStoreRequest;
use App\Http\Requests\Inventory\Admin\WarehouseUpdateRequest;
use App\Http\Resources\Admin\Inventory\WarehouseResource;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD de almacenes (F5.5).
 *
 * Resource CRUD completo. Los warehouses son globales (no tienen
 * `store_id`) por ADR-005 — el inventario se comparte entre tenants.
 */
class WarehouseController extends Controller
{
    /**
     * GET /admin/inventory/warehouses
     */
    public function index(): JsonResponse
    {
        $warehouses = Warehouse::query()->with('inventories')->orderBy('name')->get();

        return response()->json(data: [
            'data' => WarehouseResource::collection(resource: $warehouses)->resolve(),
        ]);
    }

    /**
     * GET /admin/inventory/warehouses/{warehouse}
     */
    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->load('inventories');

        return response()->json(data: [
            'data' => (new WarehouseResource(resource: $warehouse))->resolve(),
        ]);
    }

    /**
     * POST /admin/inventory/warehouses
     */
    public function store(WarehouseStoreRequest $request): JsonResponse
    {
        $warehouse = Warehouse::query()->create(attributes: $request->validated());

        return response()->json(data: [
            'data' => (new WarehouseResource(resource: $warehouse))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/inventory/warehouses/{warehouse}
     */
    public function update(WarehouseUpdateRequest $request, Warehouse $warehouse): JsonResponse
    {
        $warehouse->update(attributes: $request->validated());

        return response()->json(data: [
            'data' => (new WarehouseResource(resource: $warehouse))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/inventory/warehouses/{warehouse}
     *
     * Soft-delete para preservar el historial de inventarios asociados.
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $warehouse->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }
}
