<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Models\ProductAttribute;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar los atributos de producto del catálogo.
 *
 * Proporciona operaciones CRUD para el recurso de atributo de producto,
 * permitiendo listar, crear, mostrar, actualizar y eliminar atributos.
 */
class ProductAttributeController extends Controller
{
    /**
     * Listar todos los atributos de producto disponibles en el catálogo.
     */
    public function index(): mixed
    {
        //
    }

    /**
     * Crear un nuevo atributo de producto en el catálogo.
     */
    public function store(Request $request): mixed
    {
        //
    }

    /**
     * Mostrar los detalles de un atributo de producto específico.
     */
    public function show(ProductAttribute $productAttribute): mixed
    {
        //
    }

    /**
     * Actualizar la información de un atributo de producto existente.
     */
    public function update(Request $request, ProductAttribute $productAttribute): mixed
    {
        //
    }

    /**
     * Eliminar un atributo de producto del catálogo.
     */
    public function destroy(ProductAttribute $productAttribute): mixed
    {
        //
    }
}
