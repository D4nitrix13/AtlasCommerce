<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar las marcas del catálogo.
 *
 * Proporciona operaciones CRUD para el recurso de marca,
 * permitiendo listar, crear, mostrar, actualizar y eliminar marcas.
 */
class BrandController extends Controller
{
    /**
     * Listar todas las marcas disponibles en el catálogo.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Crear una nueva marca en el catálogo.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Mostrar los detalles de una marca específica.
     */
    public function show(Brand $brand): JsonResponse
    {
        //
    }

    /**
     * Actualizar la información de una marca existente.
     */
    public function update(Request $request, Brand $brand): JsonResponse
    {
        //
    }

    /**
     * Eliminar una marca del catálogo.
     */
    public function destroy(Brand $brand): JsonResponse
    {
        //
    }
}
