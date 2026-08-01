<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\PermissionRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de la relación permisos-roles.
 *
 * Maneja las operaciones CRUD de la tabla pivote que asocia permisos con roles
 * dentro del sistema de control de acceso basado en roles (RBAC).
 */
class PermissionRoleController extends Controller
{
    /**
     * Lista todas las relaciones permiso-rol registradas.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Crea una nueva asociación entre un permiso y un rol.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Muestra los datos de una relación permiso-rol específica.
     */
    public function show(PermissionRole $permissionRole): JsonResponse
    {
        //
    }

    /**
     * Actualiza una relación permiso-rol existente.
     */
    public function update(Request $request, PermissionRole $permissionRole): JsonResponse
    {
        //
    }

    /**
     * Elimina una relación permiso-rol del sistema.
     */
    public function destroy(PermissionRole $permissionRole): JsonResponse
    {
        //
    }
}
