<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar métodos de pago.
 */
class PaymentMethodController extends Controller
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
     * Muestra el método de pago especificado.
     */
    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        //
    }

    /**
     * Actualiza el método de pago especificado en la base de datos.
     */
    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        //
    }

    /**
     * Elimina el método de pago especificado de la base de datos.
     */
    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        //
    }
}
