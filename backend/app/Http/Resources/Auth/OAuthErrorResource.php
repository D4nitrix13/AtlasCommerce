<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use App\Exceptions\SocialAuth\OAuthException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Envelope uniforme para errores del flujo OAuth.
 *
 * El frontend recibe `{code, message, ...extras}` donde `code` es
 * el `oauthCode()` de la excepción y los extras dependen del tipo
 * (`email`, `provider`). El i18n lookup es `auth.login.error.{code}`
 * — el código debe ser estable y único por tipo de fallo.
 *
 * Uso:
 *   return OAuthErrorResource::from($e);                  // API JSON response
 *   return new OAuthErrorResource($e);                    // equivalente
 *   return OAuthErrorResource::make($genericThrowable);    // fallback genérico
 *
 * @property-read \Throwable $resource
 */
class OAuthErrorResource extends JsonResource
{
    /**
     * Factory: serializa una excepción cualquiera al envelope uniforme.
     *
     * Si la excepción es una `OAuthException`, extrae `oauthCode()` +
     * `extras()`. Para cualquier otra, devuelve un envelope genérico
     * con código `oauth_error` y mensaje sanitizado.
     */
    public static function from(\Throwable $e): self
    {
        return new self($e);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $e = $this->resource;

        if ($e instanceof OAuthException) {
            return array_merge(
                [
                    'code' => $e->oauthCode(),
                    'message' => $e->getMessage(),
                ],
                $e->extras(),
            );
        }

        // Fallback genérico — NO leakear detalles del throwable.
        return [
            'code' => 'oauth_error',
            'message' => 'Error interno durante el flujo OAuth.',
        ];
    }
}
