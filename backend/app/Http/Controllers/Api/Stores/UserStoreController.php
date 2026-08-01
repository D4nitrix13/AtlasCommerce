<?php

namespace App\Http\Controllers\Api\Stores;

use App\Http\Controllers\Controller;
use App\Models\Stores\UserStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de la relación usuario-tienda.
 *
 * Administra la asignación de usuarios a tiendas, controlando
 * qué usuarios tienen acceso a cada tienda y sus roles
 * correspondientes dentro de la organización.
 */
class UserStoreController extends Controller
{
    /**
     * Lista todas las relaciones usuario-tienda disponibles.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Almacena una nueva relación usuario-tienda en el sistema.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Muestra los detalles de una relación usuario-tienda específica.
     */
    public function show(UserStore $userStore): JsonResponse
    {
        //
    }

    /**
     * Actualiza la información de una relación usuario-tienda existente.
     */
    public function update(Request $request, UserStore $userStore): JsonResponse
    {
        //
    }

    /**
     * Elimina una relación usuario-tienda del sistema.
     */
    public function destroy(UserStore $userStore): JsonResponse
    {
        //
    }
}
