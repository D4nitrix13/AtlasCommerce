<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\PaymentInstallment;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar cuotas de pago.
 */
class PaymentInstallmentController extends Controller
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
     * Muestra la cuota de pago especificada.
     */
    public function show(PaymentInstallment $paymentInstallment)
    {
        //
    }

    /**
     * Actualiza la cuota de pago especificada en la base de datos.
     */
    public function update(Request $request, PaymentInstallment $paymentInstallment)
    {
        //
    }

    /**
     * Elimina la cuota de pago especificada de la base de datos.
     */
    public function destroy(PaymentInstallment $paymentInstallment)
    {
        //
    }
}
