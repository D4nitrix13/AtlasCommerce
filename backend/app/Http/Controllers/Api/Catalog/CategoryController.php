<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar las categorías del catálogo.
 *
 * Proporciona operaciones CRUD para el recurso de categoría,
 * permitiendo listar, crear, mostrar, actualizar y eliminar categorías.
 */
class CategoryController extends Controller
{
    /**
     * Listar todas las categorías disponibles en el catálogo.
     */
    public function index(): mixed
    {
        //
    }

    /**
     * Crear una nueva categoría en el catálogo.
     */
    public function store(Request $request): mixed
    {
        //
    }

    /**
     * Mostrar los detalles de una categoría específica.
     */
    public function show(Category $category): mixed
    {
        //
    }

    /**
     * Actualizar la información de una categoría existente.
     */
    public function update(Request $request, Category $category): mixed
    {
        //
    }

    /**
     * Eliminar una categoría del catálogo.
     */
    public function destroy(Category $category): mixed
    {
        //
    }
}
