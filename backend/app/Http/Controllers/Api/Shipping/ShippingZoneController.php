<?php

namespace App\Http\Controllers\Api\Shipping;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar zonas de envío.
 */
class ShippingZoneController extends Controller
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
     * Muestra la zona de envío especificada.
     */
    public function show(ShippingZone $shippingZone)
    {
        //
    }

    /**
     * Actualiza la zona de envío especificada en la base de datos.
     */
    public function update(Request $request, ShippingZone $shippingZone)
    {
        //
    }

    /**
     * Elimina la zona de envío especificada de la base de datos.
     */
    public function destroy(ShippingZone $shippingZone)
    {
        //
    }
}
