<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar direcciones de clientes.
 */
class CustomerAddressController extends Controller
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
     * Muestra la dirección de cliente especificada.
     */
    public function show(CustomerAddress $customerAddress): JsonResponse
    {
        //
    }

    /**
     * Actualiza la dirección de cliente especificada en la base de datos.
     */
    public function update(Request $request, CustomerAddress $customerAddress): JsonResponse
    {
        //
    }

    /**
     * Elimina la dirección de cliente especificada de la base de datos.
     */
    public function destroy(CustomerAddress $customerAddress): JsonResponse
    {
        //
    }
}
