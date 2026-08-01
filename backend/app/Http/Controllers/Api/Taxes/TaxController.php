<?php

namespace App\Http\Controllers\Api\Taxes;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar impuestos.
 */
class TaxController extends Controller
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
     * Muestra el impuesto especificado.
     */
    public function show(Tax $tax): JsonResponse
    {
        //
    }

    /**
     * Actualiza el impuesto especificado en la base de datos.
     */
    public function update(Request $request, Tax $tax): JsonResponse
    {
        //
    }

    /**
     * Elimina el impuesto especificado de la base de datos.
     */
    public function destroy(Tax $tax): JsonResponse
    {
        //
    }
}
