<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar artículos de factura.
 */
class InvoiceItemController extends Controller
{
    /**
     * Obtiene el listado de recursos.
     */
    public function index()
    {
        //
    }

    /**
     * Almacena un nuevo recurso en la base de datos.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Muestra el artículo de factura especificado.
     */
    public function show(InvoiceItem $invoiceItem)
    {
        //
    }

    /**
     * Actualiza el artículo de factura especificado en la base de datos.
     */
    public function update(Request $request, InvoiceItem $invoiceItem)
    {
        //
    }

    /**
     * Elimina el artículo de factura especificado de la base de datos.
     */
    public function destroy(InvoiceItem $invoiceItem)
    {
        //
    }
}
