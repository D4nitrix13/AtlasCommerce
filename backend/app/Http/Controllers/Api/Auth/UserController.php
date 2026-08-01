<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de usuarios del sistema.
 *
 * Maneja las operaciones CRUD de usuarios dentro del módulo de autenticación.
 */
class UserController extends Controller
{
    /**
     * Lista todos los usuarios registrados.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Registra un nuevo usuario en el sistema.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Muestra los datos de un usuario específico.
     */
    public function show(User $user): JsonResponse
    {
        //
    }

    /**
     * Actualiza los datos de un usuario existente.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        //
    }

    /**
     * Elimina un usuario del sistema.
     */
    public function destroy(User $user): JsonResponse
    {
        //
    }
}
