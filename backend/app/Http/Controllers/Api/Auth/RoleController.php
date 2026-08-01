<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de roles de usuario.
 *
 * Maneja las operaciones CRUD de roles utilizados para el control de acceso
 * basado en roles (RBAC) dentro del módulo de autenticación.
 */
class RoleController extends Controller
{
    /**
     * Lista todos los roles disponibles.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Crea un nuevo rol en el sistema.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Muestra los datos de un rol específico.
     */
    public function show(Role $role): JsonResponse
    {
        //
    }

    /**
     * Actualiza los datos de un rol existente.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        //
    }

    /**
     * Elimina un rol del sistema.
     */
    public function destroy(Role $role): JsonResponse
    {
        //
    }
}
