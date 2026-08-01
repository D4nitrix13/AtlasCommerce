<?php

namespace App\Http\Controllers\Api\Stores;

use App\Http\Controllers\Controller;
use App\Models\Stores\StoreDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de dominios de tienda.
 *
 * Administra los dominios personalizados asociados a cada tienda,
 * permitiendo configurar nombres de dominio propios para
 * el storefront.
 */
class StoreDomainController extends Controller
{
    /**
     * Lista todos los dominios de tienda disponibles.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Almacena un nuevo dominio de tienda en el sistema.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Muestra los detalles de un dominio de tienda específico.
     */
    public function show(StoreDomain $storeDomain): JsonResponse
    {
        //
    }

    /**
     * Actualiza la información de un dominio de tienda existente.
     */
    public function update(Request $request, StoreDomain $storeDomain): JsonResponse
    {
        //
    }

    /**
     * Elimina un dominio de tienda del sistema.
     */
    public function destroy(StoreDomain $storeDomain): JsonResponse
    {
        //
    }
}
