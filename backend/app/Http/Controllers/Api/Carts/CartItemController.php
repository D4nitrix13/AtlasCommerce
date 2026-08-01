<?php

namespace App\Http\Controllers\Api\Carts;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar artículos del carrito.
 */
class CartItemController extends Controller
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
     * Muestra el artículo del carrito especificado.
     */
    public function show(CartItem $cartItem)
    {
        //
    }

    /**
     * Actualiza el artículo del carrito especificado en la base de datos.
     */
    public function update(Request $request, CartItem $cartItem)
    {
        //
    }

    /**
     * Elimina el artículo del carrito especificado de la base de datos.
     */
    public function destroy(CartItem $cartItem)
    {
        //
    }
}
