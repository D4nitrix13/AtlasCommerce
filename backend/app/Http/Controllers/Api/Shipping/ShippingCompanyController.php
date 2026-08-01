<?php

namespace App\Http\Controllers\Api\Shipping;

use App\Http\Controllers\Controller;
use App\Models\ShippingCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar empresas de envío.
 */
class ShippingCompanyController extends Controller
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
     * Muestra la empresa de envío especificada.
     */
    public function show(ShippingCompany $shippingCompany): JsonResponse
    {
        //
    }

    /**
     * Actualiza la empresa de envío especificada en la base de datos.
     */
    public function update(Request $request, ShippingCompany $shippingCompany): JsonResponse
    {
        //
    }

    /**
     * Elimina la empresa de envío especificada de la base de datos.
     */
    public function destroy(ShippingCompany $shippingCompany): JsonResponse
    {
        //
    }
}
