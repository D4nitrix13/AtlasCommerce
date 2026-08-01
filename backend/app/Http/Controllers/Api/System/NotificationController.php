<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar notificaciones.
 */
class NotificationController extends Controller
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
     * Muestra la notificación especificada.
     */
    public function show(Notification $notification): JsonResponse
    {
        //
    }

    /**
     * Actualiza la notificación especificada en la base de datos.
     */
    public function update(Request $request, Notification $notification): JsonResponse
    {
        //
    }

    /**
     * Elimina la notificación especificada de la base de datos.
     */
    public function destroy(Notification $notification): JsonResponse
    {
        //
    }
}
