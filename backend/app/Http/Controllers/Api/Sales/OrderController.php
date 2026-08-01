<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar pedidos.
 */
class OrderController extends Controller
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
     * Muestra el pedido especificado.
     */
    public function show(Order $order): JsonResponse
    {
        //
    }

    /**
     * Actualiza el pedido especificado en la base de datos.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        //
    }

    /**
     * Elimina el pedido especificado de la base de datos.
     */
    public function destroy(Order $order): JsonResponse
    {
        //
    }
}
