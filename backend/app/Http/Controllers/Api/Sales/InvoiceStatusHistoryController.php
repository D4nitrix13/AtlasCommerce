<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\InvoiceStatusHistory;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar historial de estados de factura.
 */
class InvoiceStatusHistoryController extends Controller
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
     * Muestra el historial de estado de factura especificado.
     */
    public function show(InvoiceStatusHistory $invoiceStatusHistory)
    {
        //
    }

    /**
     * Actualiza el historial de estado de factura especificado en la base de datos.
     */
    public function update(Request $request, InvoiceStatusHistory $invoiceStatusHistory)
    {
        //
    }

    /**
     * Elimina el historial de estado de factura especificado de la base de datos.
     */
    public function destroy(InvoiceStatusHistory $invoiceStatusHistory)
    {
        //
    }
}
