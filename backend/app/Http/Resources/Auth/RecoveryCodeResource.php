<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un código de recuperación de cuenta.
 *
 * Contiene la información de un código de respaldo utilizado para
 * acceder a la cuenta cuando el usuario no dispone de su método
 * de autenticación de dos factores.
 */
class RecoveryCodeResource extends JsonResource
{
    //
}
