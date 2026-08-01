<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de permisos.
 *
 * Maneja las operaciones CRUD de permisos utilizados para el control de acceso
 * detallado dentro del módulo de autenticación.
 */
class PermissionController extends Controller
{
    /**
     * Lista todos los permisos disponibles.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Crea un nuevo permiso en el sistema.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Muestra los datos de un permiso específico.
     */
    public function show(Permission $permission): JsonResponse
    {
        //
    }

    /**
     * Actualiza los datos de un permiso existente.
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        //
    }

    /**
     * Elimina un permiso del sistema.
     */
    public function destroy(Permission $permission): JsonResponse
    {
        //
    }
}
