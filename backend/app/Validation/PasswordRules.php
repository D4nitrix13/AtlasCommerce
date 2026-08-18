<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Password complexity rules compartidas entre FormRequests.
 *
 * Aplica defensa contra fuerza bruta siguiendo NIST SP 800-63B + OWASP:
 *  - min 12 chars (NIST + OWASP mínimo recomendado)
 *  - max 255 chars (límite Bcrypt)
 *  - al menos 1 mayúscula (regex [A-Z])
 *  - al menos 1 minúscula (regex [a-z])
 *  - al menos 1 número   (regex [0-9])
 *  - al menos 1 símbolo  (regex [^A-Za-z0-9])
 *
 * Uso en FormRequest::rules():
 *
 *   'password' => PasswordRules::complexity(),
 *   'new_password' => PasswordRules::complexity(required: false),
 *   'password_optional' => PasswordRules::complexity(confirmed: false),
 */
final class PasswordRules
{
    /** Mínimo recomendado por NIST + OWASP. */
    public const MIN_LENGTH = 12;

    /** Límite de Bcrypt para hashear. */
    public const MAX_LENGTH = 255;

    /**
     * Reglas de complejidad.
     *
     * @param  bool  $required  Si true, exige presencia. Si false, es opcional.
     * @param  bool  $confirmed  Si true, exige `password_confirmation` que matchee.
     * @return array<int, string>
     */
    public static function complexity(bool $required = true, bool $confirmed = true): array
    {
        return array_merge(
            $required ? ['required'] : ['sometimes'],
            ['string'],
            ['min:' . self::MIN_LENGTH],
            ['max:' . self::MAX_LENGTH],
            $confirmed ? ['confirmed'] : [],
            [
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        );
    }
}
