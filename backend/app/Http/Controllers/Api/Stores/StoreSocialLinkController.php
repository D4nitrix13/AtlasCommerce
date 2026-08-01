<?php

namespace App\Http\Controllers\Api\Stores;

use App\Http\Controllers\Controller;
use App\Models\Stores\StoreSocialLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de enlaces sociales de tienda.
 *
 * Administra los perfiles de redes sociales asociados a cada tienda,
 * permitiendo configurar enlaces a plataformas como
 * Instagram, Facebook y Twitter.
 */
class StoreSocialLinkController extends Controller
{
    /**
     * Lista todos los enlaces sociales de una tienda.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Almacena un nuevo enlace social para una tienda.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Muestra los detalles de un enlace social específico.
     */
    public function show(StoreSocialLink $storeSocialLink): JsonResponse
    {
        //
    }

    /**
     * Actualiza la información de un enlace social existente.
     */
    public function update(Request $request, StoreSocialLink $storeSocialLink): JsonResponse
    {
        //
    }

    /**
     * Elimina un enlace social de la tienda.
     */
    public function destroy(StoreSocialLink $storeSocialLink): JsonResponse
    {
        //
    }
}
