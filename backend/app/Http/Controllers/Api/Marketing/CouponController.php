<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar cupones.
 */
class CouponController extends Controller
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
     * Muestra el cupón especificado.
     */
    public function show(Coupon $coupon): JsonResponse
    {
        //
    }

    /**
     * Actualiza el cupón especificado en la base de datos.
     */
    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        //
    }

    /**
     * Elimina el cupón especificado de la base de datos.
     */
    public function destroy(Coupon $coupon): JsonResponse
    {
        //
    }
}
