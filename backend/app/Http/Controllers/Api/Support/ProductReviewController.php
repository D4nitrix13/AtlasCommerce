<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar reseñas de productos.
 */
class ProductReviewController extends Controller
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
     * Muestra la reseña de producto especificada.
     */
    public function show(ProductReview $productReview): JsonResponse
    {
        //
    }

    /**
     * Actualiza la reseña de producto especificada en la base de datos.
     */
    public function update(Request $request, ProductReview $productReview): JsonResponse
    {
        //
    }

    /**
     * Elimina la reseña de producto especificada de la base de datos.
     */
    public function destroy(ProductReview $productReview): JsonResponse
    {
        //
    }
}
