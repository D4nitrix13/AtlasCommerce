<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar monedas.
 */
class CurrencyController extends Controller
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
     * Muestra la moneda especificada.
     */
    public function show(Currency $currency): JsonResponse
    {
        //
    }

    /**
     * Actualiza la moneda especificada en la base de datos.
     */
    public function update(Request $request, Currency $currency): JsonResponse
    {
        //
    }

    /**
     * Elimina la moneda especificada de la base de datos.
     */
    public function destroy(Currency $currency): JsonResponse
    {
        //
    }
}
