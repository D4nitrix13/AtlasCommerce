<?php

namespace App\Http\Controllers\Api\Returns;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar reembolsos.
 */
class RefundController extends Controller
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
     * Muestra el reembolso especificado.
     */
    public function show(Refund $refund): JsonResponse
    {
        //
    }

    /**
     * Actualiza el reembolso especificado en la base de datos.
     */
    public function update(Request $request, Refund $refund): JsonResponse
    {
        //
    }

    /**
     * Elimina el reembolso especificado de la base de datos.
     */
    public function destroy(Refund $refund): JsonResponse
    {
        //
    }
}
