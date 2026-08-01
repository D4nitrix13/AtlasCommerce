<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar almacenes.
 */
class WarehouseController extends Controller
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
     * Muestra el almacén especificado.
     */
    public function show(Warehouse $warehouse): JsonResponse
    {
        //
    }

    /**
     * Actualiza el almacén especificado en la base de datos.
     */
    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        //
    }

    /**
     * Elimina el almacén especificado de la base de datos.
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        //
    }
}
