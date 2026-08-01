<?php

namespace App\Http\Controllers\Api\Shipping;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar envíos.
 */
class ShipmentController extends Controller
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
     * Muestra el envío especificado.
     */
    public function show(Shipment $shipment)
    {
        //
    }

    /**
     * Actualiza el envío especificado en la base de datos.
     */
    public function update(Request $request, Shipment $shipment)
    {
        //
    }

    /**
     * Elimina el envío especificado de la base de datos.
     */
    public function destroy(Shipment $shipment)
    {
        //
    }
}
