<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una credencial de passkey registrada.
 *
 * Contiene los metadatos de la clave de autenticación WebAuthn
 * asociada a un usuario, incluyendo identificador y nombre del dispositivo.
 */
class PasskeyCredentialResource extends JsonResource
{
    //
}
