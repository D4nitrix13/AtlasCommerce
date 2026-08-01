<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar reseñas de productos.
 */
class ProductReviewController extends Controller
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
     * Muestra la reseña de producto especificada.
     */
    public function show(ProductReview $productReview)
    {
        //
    }

    /**
     * Actualiza la reseña de producto especificada en la base de datos.
     */
    public function update(Request $request, ProductReview $productReview)
    {
        //
    }

    /**
     * Elimina la reseña de producto especificada de la base de datos.
     */
    public function destroy(ProductReview $productReview)
    {
        //
    }
}
