<?php

namespace App\Http\Controllers\Api\Shipping;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar zonas de envío.
 */
class ShippingZoneController extends Controller
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
     * Muestra la zona de envío especificada.
     */
    public function show(ShippingZone $shippingZone): JsonResponse
    {
        //
    }

    /**
     * Actualiza la zona de envío especificada en la base de datos.
     */
    public function update(Request $request, ShippingZone $shippingZone): JsonResponse
    {
        //
    }

    /**
     * Elimina la zona de envío especificada de la base de datos.
     */
    public function destroy(ShippingZone $shippingZone): JsonResponse
    {
        //
    }
}
