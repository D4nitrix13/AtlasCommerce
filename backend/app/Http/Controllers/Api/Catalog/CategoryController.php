<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
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
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Crear una nueva categoría en el catálogo.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Mostrar los detalles de una categoría específica.
     */
    public function show(Category $category): JsonResponse
    {
        //
    }

    /**
     * Actualizar la información de una categoría existente.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        //
    }

    /**
     * Eliminar una categoría del catálogo.
     */
    public function destroy(Category $category): JsonResponse
    {
        //
    }
}
