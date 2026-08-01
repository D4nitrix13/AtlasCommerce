<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\OrderStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar historial de estados de pedido.
 */
class OrderStatusHistoryController extends Controller
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
     * Muestra el historial de estado de pedido especificado.
     */
    public function show(OrderStatusHistory $orderStatusHistory): JsonResponse
    {
        //
    }

    /**
     * Actualiza el historial de estado de pedido especificado en la base de datos.
     */
    public function update(Request $request, OrderStatusHistory $orderStatusHistory): JsonResponse
    {
        //
    }

    /**
     * Elimina el historial de estado de pedido especificado de la base de datos.
     */
    public function destroy(OrderStatusHistory $orderStatusHistory): JsonResponse
    {
        //
    }
}
