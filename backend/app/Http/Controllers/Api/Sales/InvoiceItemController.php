<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\InvoiceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar artículos de factura.
 */
class InvoiceItemController extends Controller
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
     * Muestra el artículo de factura especificado.
     */
    public function show(InvoiceItem $invoiceItem): JsonResponse
    {
        //
    }

    /**
     * Actualiza el artículo de factura especificado en la base de datos.
     */
    public function update(Request $request, InvoiceItem $invoiceItem): JsonResponse
    {
        //
    }

    /**
     * Elimina el artículo de factura especificado de la base de datos.
     */
    public function destroy(InvoiceItem $invoiceItem): JsonResponse
    {
        //
    }
}
