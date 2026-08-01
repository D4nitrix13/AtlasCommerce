<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar artículos de pedido.
 */
class OrderItemController extends Controller
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
     * Muestra el artículo de pedido especificado.
     */
    public function show(OrderItem $orderItem)
    {
        //
    }

    /**
     * Actualiza el artículo de pedido especificado en la base de datos.
     */
    public function update(Request $request, OrderItem $orderItem)
    {
        //
    }

    /**
     * Elimina el artículo de pedido especificado de la base de datos.
     */
    public function destroy(OrderItem $orderItem)
    {
        //
    }
}
