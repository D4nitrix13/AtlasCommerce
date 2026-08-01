<?php

namespace App\Http\Controllers\Api\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar compras.
 */
class PurchaseController extends Controller
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
     * Muestra la compra especificada.
     */
    public function show(Purchase $purchase): JsonResponse
    {
        //
    }

    /**
     * Actualiza la compra especificada en la base de datos.
     */
    public function update(Request $request, Purchase $purchase): JsonResponse
    {
        //
    }

    /**
     * Elimina la compra especificada de la base de datos.
     */
    public function destroy(Purchase $purchase): JsonResponse
    {
        //
    }
}
