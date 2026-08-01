<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar las imágenes de producto del catálogo.
 *
 * Proporciona operaciones CRUD para el recurso de imagen de producto,
 * permitiendo listar, crear, mostrar, actualizar y eliminar imágenes.
 */
class ProductImageController extends Controller
{
    /**
     * Listar todas las imágenes de producto disponibles en el catálogo.
     */
    public function index(): mixed
    {
        //
    }

    /**
     * Crear una nueva imagen de producto en el catálogo.
     */
    public function store(Request $request): mixed
    {
        //
    }

    /**
     * Mostrar los detalles de una imagen de producto específica.
     */
    public function show(ProductImage $productImage): mixed
    {
        //
    }

    /**
     * Actualizar la información de una imagen de producto existente.
     */
    public function update(Request $request, ProductImage $productImage): mixed
    {
        //
    }

    /**
     * Eliminar una imagen de producto del catálogo.
     */
    public function destroy(ProductImage $productImage): mixed
    {
        //
    }
}
