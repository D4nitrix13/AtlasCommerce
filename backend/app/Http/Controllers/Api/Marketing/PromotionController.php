<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar promociones.
 */
class PromotionController extends Controller
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
     * Muestra la promoción especificada.
     */
    public function show(Promotion $promotion)
    {
        //
    }

    /**
     * Actualiza la promoción especificada en la base de datos.
     */
    public function update(Request $request, Promotion $promotion)
    {
        //
    }

    /**
     * Elimina la promoción especificada de la base de datos.
     */
    public function destroy(Promotion $promotion)
    {
        //
    }
}
