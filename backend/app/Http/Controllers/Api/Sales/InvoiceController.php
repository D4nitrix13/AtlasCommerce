<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar facturas.
 */
class InvoiceController extends Controller
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
     * Muestra la factura especificada.
     */
    public function show(Invoice $invoice)
    {
        //
    }

    /**
     * Actualiza la factura especificada en la base de datos.
     */
    public function update(Request $request, Invoice $invoice)
    {
        //
    }

    /**
     * Elimina la factura especificada de la base de datos.
     */
    public function destroy(Invoice $invoice)
    {
        //
    }
}
