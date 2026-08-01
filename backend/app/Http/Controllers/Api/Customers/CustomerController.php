<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar clientes.
 */
class CustomerController extends Controller
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
     * Muestra el cliente especificado.
     */
    public function show(Customer $customer): JsonResponse
    {
        //
    }

    /**
     * Actualiza el cliente especificado en la base de datos.
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        //
    }

    /**
     * Elimina el cliente especificado de la base de datos.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        //
    }
}
