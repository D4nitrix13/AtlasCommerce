<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar tickets de soporte.
 */
class SupportTicketController extends Controller
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
     * Muestra el ticket de soporte especificado.
     */
    public function show(SupportTicket $supportTicket): JsonResponse
    {
        //
    }

    /**
     * Actualiza el ticket de soporte especificado en la base de datos.
     */
    public function update(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        //
    }

    /**
     * Elimina el ticket de soporte especificado de la base de datos.
     */
    public function destroy(SupportTicket $supportTicket): JsonResponse
    {
        //
    }
}
