<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar transacciones de pago.
 */
class PaymentTransactionController extends Controller
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
     * Muestra la transacción de pago especificada.
     */
    public function show(PaymentTransaction $paymentTransaction): JsonResponse
    {
        //
    }

    /**
     * Actualiza la transacción de pago especificada en la base de datos.
     */
    public function update(Request $request, PaymentTransaction $paymentTransaction): JsonResponse
    {
        //
    }

    /**
     * Elimina la transacción de pago especificada de la base de datos.
     */
    public function destroy(PaymentTransaction $paymentTransaction): JsonResponse
    {
        //
    }
}
