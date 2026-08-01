<?php

namespace App\Http\Controllers\Api\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar compras.
 */
class PurchaseController extends Controller
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
     * Muestra la compra especificada.
     */
    public function show(Purchase $purchase)
    {
        //
    }

    /**
     * Actualiza la compra especificada en la base de datos.
     */
    public function update(Request $request, Purchase $purchase)
    {
        //
    }

    /**
     * Elimina la compra especificada de la base de datos.
     */
    public function destroy(Purchase $purchase)
    {
        //
    }
}
