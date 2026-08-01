<?php

namespace App\Http\Controllers\Api\Carts;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar listas de deseos.
 */
class WishlistController extends Controller
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
     * Muestra la lista de deseos especificada.
     */
    public function show(Wishlist $wishlist)
    {
        //
    }

    /**
     * Actualiza la lista de deseos especificada en la base de datos.
     */
    public function update(Request $request, Wishlist $wishlist)
    {
        //
    }

    /**
     * Elimina la lista de deseos especificada de la base de datos.
     */
    public function destroy(Wishlist $wishlist)
    {
        //
    }
}
