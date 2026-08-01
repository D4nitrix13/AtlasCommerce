<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar los productos del catálogo.
 *
 * Proporciona operaciones CRUD para el recurso de producto,
 * permitiendo listar, crear, mostrar, actualizar y eliminar productos.
 */
class ProductController extends Controller
{
    /**
     * Listar todos los productos disponibles en el catálogo.
     */
    public function index(): mixed
    {
        //
    }

    /**
     * Crear un nuevo producto en el catálogo.
     */
    public function store(Request $request): mixed
    {
        //
    }

    /**
     * Mostrar los detalles de un producto específico.
     */
    public function show(Product $product): mixed
    {
        //
    }

    /**
     * Actualizar la información de un producto existente.
     */
    public function update(Request $request, Product $product): mixed
    {
        //
    }

    /**
     * Eliminar un producto del catálogo.
     */
    public function destroy(Product $product): mixed
    {
        //
    }
}
