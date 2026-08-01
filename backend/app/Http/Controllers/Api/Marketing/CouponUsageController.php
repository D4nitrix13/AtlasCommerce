<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CouponUsage;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar usos de cupones.
 */
class CouponUsageController extends Controller
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
     * Muestra el uso de cupón especificado.
     */
    public function show(CouponUsage $couponUsage)
    {
        //
    }

    /**
     * Actualiza el uso de cupón especificado en la base de datos.
     */
    public function update(Request $request, CouponUsage $couponUsage)
    {
        //
    }

    /**
     * Elimina el uso de cupón especificado de la base de datos.
     */
    public function destroy(CouponUsage $couponUsage)
    {
        //
    }
}
