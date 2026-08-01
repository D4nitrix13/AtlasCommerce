<?php

/**
 * Configuración de autenticación de dos factores.
 *
 * Centraliza los valores configurables para Email OTP y Recovery Codes.
 * Los valores se leen de variables de entorno con fallbacks a defaults sensatos.
 */

return [
    // Configuración de códigos OTP enviados por correo.
    'email_otp' => [
        'digits' => env('TWO_FACTOR_EMAIL_OTP_DIGITS', 6),
        'expires_minutes' => env('TWO_FACTOR_EMAIL_OTP_EXPIRES_MINUTES', 10),
        'max_attempts' => env('TWO_FACTOR_EMAIL_OTP_MAX_ATTEMPTS', 5),
    ],

    // Configuración de códigos de recuperación de uso único.
    'recovery_codes' => [
        'count' => env('TWO_FACTOR_RECOVERY_CODES_COUNT', 8),
        'prefix' => env('TWO_FACTOR_RECOVERY_CODES_PREFIX', 'APP'),
    ],
];
