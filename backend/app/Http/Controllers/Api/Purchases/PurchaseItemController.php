<?php

namespace App\Http\Controllers\Api\Purchases;

use App\Http\Controllers\Controller;
use App\Models\PurchaseItem;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar artículos de compra.
 */
class PurchaseItemController extends Controller
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
     * Muestra el artículo de compra especificado.
     */
    public function show(PurchaseItem $purchaseItem)
    {
        //
    }

    /**
     * Actualiza el artículo de compra especificado en la base de datos.
     */
    public function update(Request $request, PurchaseItem $purchaseItem)
    {
        //
    }

    /**
     * Elimina el artículo de compra especificado de la base de datos.
     */
    public function destroy(PurchaseItem $purchaseItem)
    {
        //
    }
}
