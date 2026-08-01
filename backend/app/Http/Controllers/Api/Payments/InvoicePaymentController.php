<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar pagos de facturas.
 */
class InvoicePaymentController extends Controller
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
     * Muestra el pago de factura especificado.
     */
    public function show(InvoicePayment $invoicePayment)
    {
        //
    }

    /**
     * Actualiza el pago de factura especificado en la base de datos.
     */
    public function update(Request $request, InvoicePayment $invoicePayment)
    {
        //
    }

    /**
     * Elimina el pago de factura especificado de la base de datos.
     */
    public function destroy(InvoicePayment $invoicePayment)
    {
        //
    }
}
