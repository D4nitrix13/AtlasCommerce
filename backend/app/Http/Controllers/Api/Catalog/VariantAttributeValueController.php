<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Models\VariantAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar los valores de atributo de variante del catálogo.
 *
 * Proporciona operaciones CRUD para el recurso de valor de atributo de variante,
 * permitiendo listar, crear, mostrar, actualizar y eliminar valores de atributo.
 */
class VariantAttributeValueController extends Controller
{
    /**
     * Listar todos los valores de atributo de variante disponibles en el catálogo.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Crear un nuevo valor de atributo de variante en el catálogo.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Mostrar los detalles de un valor de atributo de variante específico.
     */
    public function show(VariantAttributeValue $variantAttributeValue): JsonResponse
    {
        //
    }

    /**
     * Actualizar la información de un valor de atributo de variante existente.
     */
    public function update(Request $request, VariantAttributeValue $variantAttributeValue): JsonResponse
    {
        //
    }

    /**
     * Eliminar un valor de atributo de variante del catálogo.
     */
    public function destroy(VariantAttributeValue $variantAttributeValue): JsonResponse
    {
        //
    }
}
