<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportTicketMessage;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar mensajes de tickets de soporte.
 */
class SupportTicketMessageController extends Controller
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
     * Muestra el mensaje de ticket de soporte especificado.
     */
    public function show(SupportTicketMessage $supportTicketMessage)
    {
        //
    }

    /**
     * Actualiza el mensaje de ticket de soporte especificado en la base de datos.
     */
    public function update(Request $request, SupportTicketMessage $supportTicketMessage)
    {
        //
    }

    /**
     * Elimina el mensaje de ticket de soporte especificado de la base de datos.
     */
    public function destroy(SupportTicketMessage $supportTicketMessage)
    {
        //
    }
}
