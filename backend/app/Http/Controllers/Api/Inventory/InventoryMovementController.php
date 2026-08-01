<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar movimientos de inventario.
 */
class InventoryMovementController extends Controller
{
    /**
     * Obtiene el listado de recursos.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Almacena un nuevo recurso en la base de datos.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Muestra el movimiento de inventario especificado.
     */
    public function show(InventoryMovement $inventoryMovement): JsonResponse
    {
        //
    }

    /**
     * Actualiza el movimiento de inventario especificado en la base de datos.
     */
    public function update(Request $request, InventoryMovement $inventoryMovement): JsonResponse
    {
        //
    }

    /**
     * Elimina el movimiento de inventario especificado de la base de datos.
     */
    public function destroy(InventoryMovement $inventoryMovement): JsonResponse
    {
        //
    }
}
