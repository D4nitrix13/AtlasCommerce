<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar pedidos.
 */
class OrderController extends Controller
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
     * Muestra el pedido especificado.
     */
    public function show(Order $order)
    {
        //
    }

    /**
     * Actualiza el pedido especificado en la base de datos.
     */
    public function update(Request $request, Order $order)
    {
        //
    }

    /**
     * Elimina el pedido especificado de la base de datos.
     */
    public function destroy(Order $order)
    {
        //
    }
}
