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
        'digits' => env(key: 'TWO_FACTOR_EMAIL_OTP_DIGITS', default: 6),
        'expires_minutes' => env(key: 'TWO_FACTOR_EMAIL_OTP_EXPIRES_MINUTES', default: 10),
        'max_attempts' => env(key: 'TWO_FACTOR_EMAIL_OTP_MAX_ATTEMPTS', default: 5),
    ],

    // Configuración de códigos de recuperación de uso único.
    'recovery_codes' => [
        'count' => env(key: 'TWO_FACTOR_RECOVERY_CODES_COUNT', default: 8),
        'prefix' => env(key: 'TWO_FACTOR_RECOVERY_CODES_PREFIX', default: 'APP'),
    ],
];
