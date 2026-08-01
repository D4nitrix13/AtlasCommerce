<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar las variantes de producto del catálogo.
 *
 * Proporciona operaciones CRUD para el recurso de variante de producto,
 * permitiendo listar, crear, mostrar, actualizar y eliminar variantes.
 */
class ProductVariantController extends Controller
{
    /**
     * Listar todas las variantes de producto disponibles en el catálogo.
     */
    public function index(): mixed
    {
        //
    }

    /**
     * Crear una nueva variante de producto en el catálogo.
     */
    public function store(Request $request): mixed
    {
        //
    }

    /**
     * Mostrar los detalles de una variante de producto específica.
     */
    public function show(ProductVariant $productVariant): mixed
    {
        //
    }

    /**
     * Actualizar la información de una variante de producto existente.
     */
    public function update(Request $request, ProductVariant $productVariant): mixed
    {
        //
    }

    /**
     * Eliminar una variante de producto del catálogo.
     */
    public function destroy(ProductVariant $productVariant): mixed
    {
        //
    }
}
