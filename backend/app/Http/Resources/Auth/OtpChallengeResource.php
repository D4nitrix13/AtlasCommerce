<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un desafío de autenticación OTP.
 *
 * Contiene los datos necesarios para el flujo de verificación
 * de un código de un solo uso, incluyendo el identificador del desafío
 * y su estado de expiración.
 */
class OtpChallengeResource extends JsonResource
{
    //
}
