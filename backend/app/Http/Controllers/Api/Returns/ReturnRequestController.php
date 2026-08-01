<?php

namespace App\Http\Controllers\Api\Returns;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar solicitudes de devolución.
 */
class ReturnRequestController extends Controller
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
     * Muestra la solicitud de devolución especificada.
     */
    public function show(ReturnRequest $returnRequest)
    {
        //
    }

    /**
     * Actualiza la solicitud de devolución especificada en la base de datos.
     */
    public function update(Request $request, ReturnRequest $returnRequest)
    {
        //
    }

    /**
     * Elimina la solicitud de devolución especificada de la base de datos.
     */
    public function destroy(ReturnRequest $returnRequest)
    {
        //
    }
}
