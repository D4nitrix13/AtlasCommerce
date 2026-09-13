<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Returns;

use App\Http\Controllers\Controller;
use App\Models\Returns\ReturnRequestItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar artículos de solicitud de devolución.
 */
class ReturnRequestItemController extends Controller
{
    /**
     * Obtiene el listado de recursos.
     */
    public function index(): JsonResponse
    {
        return response()->json(
            data: ['message' => 'Not implemented yet.'],
            status: 501,
        );
    }

    /**
     * Almacena un nuevo recurso en la base de datos.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json(
            data: ['message' => 'Not implemented yet.'],
            status: 501,
        );
    }

    /**
     * Muestra el artículo de solicitud de devolución especificado.
     */
    public function show(ReturnRequestItem $returnRequestItem): JsonResponse
    {
        return response()->json(
            data: ['message' => 'Not implemented yet.'],
            status: 501,
        );
    }

    /**
     * Actualiza el artículo de solicitud de devolución especificado en la base de datos.
     */
    public function update(Request $request, ReturnRequestItem $returnRequestItem): JsonResponse
    {
        return response()->json(
            data: ['message' => 'Not implemented yet.'],
            status: 501,
        );
    }

    /**
     * Elimina el artículo de solicitud de devolución especificado de la base de datos.
     */
    public function destroy(ReturnRequestItem $returnRequestItem): JsonResponse
    {
        return response()->json(
            data: ['message' => 'Not implemented yet.'],
            status: 501,
        );
    }
}
