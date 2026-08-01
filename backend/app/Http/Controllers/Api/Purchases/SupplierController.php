<?php

namespace App\Http\Controllers\Api\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar proveedores.
 */
class SupplierController extends Controller
{
    /**
     * Obtiene el listado de recursos.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Almacena un nuevo recurso en la base de datos.
     */
    public function store(Request $request): JsonResponse
    {
        //
    }

    /**
     * Muestra el proveedor especificado.
     */
    public function show(Supplier $supplier): JsonResponse
    {
        //
    }

    /**
     * Actualiza el proveedor especificado en la base de datos.
     */
    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        //
    }

    /**
     * Elimina el proveedor especificado de la base de datos.
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        //
    }
}
