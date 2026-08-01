<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar pagos.
 */
class PaymentController extends Controller
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
     * Muestra el pago especificado.
     */
    public function show(Payment $payment): JsonResponse
    {
        //
    }

    /**
     * Actualiza el pago especificado en la base de datos.
     */
    public function update(Request $request, Payment $payment): JsonResponse
    {
        //
    }

    /**
     * Elimina el pago especificado de la base de datos.
     */
    public function destroy(Payment $payment): JsonResponse
    {
        //
    }
}
