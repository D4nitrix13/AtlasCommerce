<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar notificaciones.
 */
class NotificationController extends Controller
{
    /**
     * Obtiene el listado de recursos.
     */
    public function index()
    {
        //
    }

    /**
     * Almacena un nuevo recurso en la base de datos.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Muestra la notificación especificada.
     */
    public function show(Notification $notification)
    {
        //
    }

    /**
     * Actualiza la notificación especificada en la base de datos.
     */
    public function update(Request $request, Notification $notification)
    {
        //
    }

    /**
     * Elimina la notificación especificada de la base de datos.
     */
    public function destroy(Notification $notification)
    {
        //
    }
}
