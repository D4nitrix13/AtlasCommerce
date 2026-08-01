<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar direcciones de clientes.
 */
class CustomerAddressController extends Controller
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
     * Muestra la dirección de cliente especificada.
     */
    public function show(CustomerAddress $customerAddress)
    {
        //
    }

    /**
     * Actualiza la dirección de cliente especificada en la base de datos.
     */
    public function update(Request $request, CustomerAddress $customerAddress)
    {
        //
    }

    /**
     * Elimina la dirección de cliente especificada de la base de datos.
     */
    public function destroy(CustomerAddress $customerAddress)
    {
        //
    }
}
