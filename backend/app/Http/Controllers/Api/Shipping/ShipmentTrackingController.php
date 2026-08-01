<?php

namespace App\Http\Controllers\Api\Shipping;

use App\Http\Controllers\Controller;
use App\Models\ShipmentTracking;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar seguimiento de envíos.
 */
class ShipmentTrackingController extends Controller
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
     * Muestra el seguimiento de envío especificado.
     */
    public function show(ShipmentTracking $shipmentTracking)
    {
        //
    }

    /**
     * Actualiza el seguimiento de envío especificado en la base de datos.
     */
    public function update(Request $request, ShipmentTracking $shipmentTracking)
    {
        //
    }

    /**
     * Elimina el seguimiento de envío especificado de la base de datos.
     */
    public function destroy(ShipmentTracking $shipmentTracking)
    {
        //
    }
}
