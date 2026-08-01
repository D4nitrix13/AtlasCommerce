<?php

namespace App\Http\Controllers\Api\Returns;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar solicitudes de devolución.
 */
class ReturnRequestController extends Controller
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
     * Muestra la solicitud de devolución especificada.
     */
    public function show(ReturnRequest $returnRequest): JsonResponse
    {
        //
    }

    /**
     * Actualiza la solicitud de devolución especificada en la base de datos.
     */
    public function update(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        //
    }

    /**
     * Elimina la solicitud de devolución especificada de la base de datos.
     */
    public function destroy(ReturnRequest $returnRequest): JsonResponse
    {
        //
    }
}
