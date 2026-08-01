<?php

namespace App\Http\Controllers\Api\Stores;

use App\Http\Controllers\Controller;
use App\Models\Stores\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de tiendas.
 *
 * Proporciona operaciones CRUD para administrar las tiendas
 * del sistema de comercio electrónico, incluyendo creación,
 * actualización, consulta y eliminación.
 */
class StoreController extends Controller
{
    /**
     * Lista todas las tiendas disponibles.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Almacena una nueva tienda en el sistema.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Muestra los detalles de una tienda específica.
     */
    public function show(Store $store): JsonResponse
    {
        //
    }

    /**
     * Actualiza la información de una tienda existente.
     */
    public function update(Request $request, Store $store): JsonResponse
    {
        //
    }

    /**
     * Elimina una tienda del sistema.
     */
    public function destroy(Store $store): JsonResponse
    {
        //
    }
}
