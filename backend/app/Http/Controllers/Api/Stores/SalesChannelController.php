<?php

namespace App\Http\Controllers\Api\Stores;

use App\Http\Controllers\Controller;
use App\Models\Stores\SalesChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de canales de venta.
 *
 * Administra los diferentes canales por los cuales una tienda
 * distribuye sus productos, como tienda física, plataforma
 * en línea o marketplaces externos.
 */
class SalesChannelController extends Controller
{
    /**
     * Lista todos los canales de venta disponibles.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Almacena un nuevo canal de venta en el sistema.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Muestra los detalles de un canal de venta específico.
     */
    public function show(SalesChannel $salesChannel): JsonResponse
    {
        //
    }

    /**
     * Actualiza la información de un canal de venta existente.
     */
    public function update(Request $request, SalesChannel $salesChannel): JsonResponse
    {
        //
    }

    /**
     * Elimina un canal de venta del sistema.
     */
    public function destroy(SalesChannel $salesChannel): JsonResponse
    {
        //
    }
}
