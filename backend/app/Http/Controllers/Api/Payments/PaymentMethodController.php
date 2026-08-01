<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar métodos de pago.
 */
class PaymentMethodController extends Controller
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
     * Muestra el método de pago especificado.
     */
    public function show(PaymentMethod $paymentMethod)
    {
        //
    }

    /**
     * Actualiza el método de pago especificado en la base de datos.
     */
    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        //
    }

    /**
     * Elimina el método de pago especificado de la base de datos.
     */
    public function destroy(PaymentMethod $paymentMethod)
    {
        //
    }
}
