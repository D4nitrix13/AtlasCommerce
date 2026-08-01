<?php

namespace App\Http\Controllers\Api\Stores;

use App\Http\Controllers\Controller;
use App\Models\Stores\ProductStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de productos de tienda.
 *
 * Administra la relación entre productos y tiendas, controlando
 * qué productos están disponibles en cada tienda, sus precios
 * específicos y estado de disponibilidad.
 */
class ProductStoreController extends Controller
{
    /**
     * Lista todos los productos de tienda disponibles.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Almacena una nueva relación producto-tienda en el sistema.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Muestra los detalles de un producto de tienda específico.
     */
    public function show(ProductStore $productStore): JsonResponse
    {
        //
    }

    /**
     * Actualiza la información de un producto de tienda existente.
     */
    public function update(Request $request, ProductStore $productStore): JsonResponse
    {
        //
    }

    /**
     * Elimina un producto de tienda del sistema.
     */
    public function destroy(ProductStore $productStore): JsonResponse
    {
        //
    }
}
