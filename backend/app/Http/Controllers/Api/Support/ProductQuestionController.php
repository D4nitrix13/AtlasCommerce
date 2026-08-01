<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Models\ProductQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar preguntas de productos.
 */
class ProductQuestionController extends Controller
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
     * Muestra la pregunta de producto especificada.
     */
    public function show(ProductQuestion $productQuestion): JsonResponse
    {
        //
    }

    /**
     * Actualiza la pregunta de producto especificada en la base de datos.
     */
    public function update(Request $request, ProductQuestion $productQuestion): JsonResponse
    {
        //
    }

    /**
     * Elimina la pregunta de producto especificada de la base de datos.
     */
    public function destroy(ProductQuestion $productQuestion): JsonResponse
    {
        //
    }
}
