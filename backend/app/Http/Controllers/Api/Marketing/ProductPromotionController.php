<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Models\ProductPromotion;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar promociones de productos.
 */
class ProductPromotionController extends Controller
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
     * Muestra la promoción de producto especificada.
     */
    public function show(ProductPromotion $productPromotion)
    {
        //
    }

    /**
     * Actualiza la promoción de producto especificada en la base de datos.
     */
    public function update(Request $request, ProductPromotion $productPromotion)
    {
        //
    }

    /**
     * Elimina la promoción de producto especificada de la base de datos.
     */
    public function destroy(ProductPromotion $productPromotion)
    {
        //
    }
}
