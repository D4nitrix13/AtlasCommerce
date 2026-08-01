<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar registros de auditoría.
 */
class AuditController extends Controller
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
     * Muestra el registro de auditoría especificado.
     */
    public function show(Audit $audit)
    {
        //
    }

    /**
     * Actualiza el registro de auditoría especificado en la base de datos.
     */
    public function update(Request $request, Audit $audit)
    {
        //
    }

    /**
     * Elimina el registro de auditoría especificado de la base de datos.
     */
    public function destroy(Audit $audit)
    {
        //
    }
}
