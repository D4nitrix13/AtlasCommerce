<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar tipos de cambio.
 */
class ExchangeRateController extends Controller
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
     * Muestra el tipo de cambio especificado.
     */
    public function show(ExchangeRate $exchangeRate)
    {
        //
    }

    /**
     * Actualiza el tipo de cambio especificado en la base de datos.
     */
    public function update(Request $request, ExchangeRate $exchangeRate)
    {
        //
    }

    /**
     * Elimina el tipo de cambio especificado de la base de datos.
     */
    public function destroy(ExchangeRate $exchangeRate)
    {
        //
    }
}
