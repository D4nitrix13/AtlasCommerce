<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Models\ProductAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar los valores de atributo de producto del catálogo.
 *
 * Proporciona operaciones CRUD para el recurso de valor de atributo de producto,
 * permitiendo listar, crear, mostrar, actualizar y eliminar valores de atributo.
 */
class ProductAttributeValueController extends Controller
{
    /**
     * Listar todos los valores de atributo de producto disponibles en el catálogo.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Crear un nuevo valor de atributo de producto en el catálogo.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Mostrar los detalles de un valor de atributo de producto específico.
     */
    public function show(ProductAttributeValue $productAttributeValue): JsonResponse
    {
        //
    }

    /**
     * Actualizar la información de un valor de atributo de producto existente.
     */
    public function update(Request $request, ProductAttributeValue $productAttributeValue): JsonResponse
    {
        //
    }

    /**
     * Eliminar un valor de atributo de producto del catálogo.
     */
    public function destroy(ProductAttributeValue $productAttributeValue): JsonResponse
    {
        //
    }
}
