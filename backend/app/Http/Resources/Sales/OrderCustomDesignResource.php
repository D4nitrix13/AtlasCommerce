<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar el snapshot de un diseño personalizado en un pedido.
 *
 * Contiene la instantánea del diseño personalizado tal como quedó
 * al momento de la compra, incluyendo capas, configuración
 * y área de impresión seleccionada.
 */
class OrderCustomDesignResource extends JsonResource
{
    //
}
