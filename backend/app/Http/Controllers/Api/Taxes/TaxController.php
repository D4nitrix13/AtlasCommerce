<?php

namespace App\Http\Controllers\Api\Taxes;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar impuestos.
 */
class TaxController extends Controller
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
     * Muestra el impuesto especificado.
     */
    public function show(Tax $tax)
    {
        //
    }

    /**
     * Actualiza el impuesto especificado en la base de datos.
     */
    public function update(Request $request, Tax $tax)
    {
        //
    }

    /**
     * Elimina el impuesto especificado de la base de datos.
     */
    public function destroy(Tax $tax)
    {
        //
    }
}
