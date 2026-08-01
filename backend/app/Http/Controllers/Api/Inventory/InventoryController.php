<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar inventario.
 */
class InventoryController extends Controller
{
    /**
     * Obtiene el listado de recursos.
     */
    public function index()
    {
        //
    }

    /**
     * Almacena un nuevo recurso en la base de datos.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Muestra el inventario especificado.
     */
    public function show(Inventory $inventory)
    {
        //
    }

    /**
     * Actualiza el inventario especificado en la base de datos.
     */
    public function update(Request $request, Inventory $inventory)
    {
        //
    }

    /**
     * Elimina el inventario especificado de la base de datos.
     */
    public function destroy(Inventory $inventory)
    {
        //
    }
}
