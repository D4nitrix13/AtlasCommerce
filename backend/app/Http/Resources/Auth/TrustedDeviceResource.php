<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un dispositivo de confianza registrado.
 *
 * Contiene los metadatos de un dispositivo que el usuario ha marcado
 * como seguro, permitiendo omitir verificaciones adicionales
 * en futuros inicios de sesión.
 */
class TrustedDeviceResource extends JsonResource
{
    //
}
