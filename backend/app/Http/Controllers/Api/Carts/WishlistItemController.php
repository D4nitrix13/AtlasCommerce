<?php

namespace App\Http\Controllers\Api\Carts;

use App\Http\Controllers\Controller;
use App\Models\WishlistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar artículos de la lista de deseos.
 */
class WishlistItemController extends Controller
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
     * Muestra el artículo de la lista de deseos especificado.
     */
    public function show(WishlistItem $wishlistItem): JsonResponse
    {
        //
    }

    /**
     * Actualiza el artículo de la lista de deseos especificado en la base de datos.
     */
    public function update(Request $request, WishlistItem $wishlistItem): JsonResponse
    {
        //
    }

    /**
     * Elimina el artículo de la lista de deseos especificado de la base de datos.
     */
    public function destroy(WishlistItem $wishlistItem): JsonResponse
    {
        //
    }
}
