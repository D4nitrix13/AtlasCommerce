<?php

namespace App\Http\Controllers\Api\Returns;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequestItem;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar artículos de solicitud de devolución.
 */
class ReturnRequestItemController extends Controller
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
     * Muestra el artículo de solicitud de devolución especificado.
     */
    public function show(ReturnRequestItem $returnRequestItem)
    {
        //
    }

    /**
     * Actualiza el artículo de solicitud de devolución especificado en la base de datos.
     */
    public function update(Request $request, ReturnRequestItem $returnRequestItem)
    {
        //
    }

    /**
     * Elimina el artículo de solicitud de devolución especificado de la base de datos.
     */
    public function destroy(ReturnRequestItem $returnRequestItem)
    {
        //
    }
}
