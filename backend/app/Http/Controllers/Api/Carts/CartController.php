<?php

namespace App\Http\Controllers\Api\Carts;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar carritos.
 */
class CartController extends Controller
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
     * Muestra el carrito especificado.
     */
    public function show(Cart $cart)
    {
        //
    }

    /**
     * Actualiza el carrito especificado en la base de datos.
     */
    public function update(Request $request, Cart $cart)
    {
        //
    }

    /**
     * Elimina el carrito especificado de la base de datos.
     */
    public function destroy(Cart $cart)
    {
        //
    }
}
