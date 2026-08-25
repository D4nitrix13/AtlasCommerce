<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un token de recuperación de contraseña.
 *
 * Contiene la información del token generado para restablecer
 * la contraseña de un usuario, incluyendo su estado de expiración.
 */
class PasswordResetTokenResource extends JsonResource
{
    //
}
