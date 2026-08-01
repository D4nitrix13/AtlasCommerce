<?php

namespace App\Http\Controllers\Api\Taxes;

use App\Http\Controllers\Controller;
use App\Models\ProductTax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar impuestos de productos.
 */
class ProductTaxController extends Controller
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
     * Muestra el impuesto de producto especificado.
     */
    public function show(ProductTax $productTax): JsonResponse
    {
        //
    }

    /**
     * Actualiza el impuesto de producto especificado en la base de datos.
     */
    public function update(Request $request, ProductTax $productTax): JsonResponse
    {
        //
    }

    /**
     * Elimina el impuesto de producto especificado de la base de datos.
     */
    public function destroy(ProductTax $productTax): JsonResponse
    {
        //
    }
}
