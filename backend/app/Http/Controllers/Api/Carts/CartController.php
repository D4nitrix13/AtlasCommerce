<?php

namespace App\Http\Controllers\Api\Carts;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar carritos.
 */
class CartController extends Controller
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
     * Muestra el carrito especificado.
     */
    public function show(Cart $cart): JsonResponse
    {
        //
    }

    /**
     * Actualiza el carrito especificado en la base de datos.
     */
    public function update(Request $request, Cart $cart): JsonResponse
    {
        //
    }

    /**
     * Elimina el carrito especificado de la base de datos.
     */
    public function destroy(Cart $cart): JsonResponse
    {
        //
    }
}
