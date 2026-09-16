<?php

declare(strict_types=1);

return [
    'auth' => [
        'email_otp' => [
            'subject' => 'Tu código de acceso',
            'greeting' => 'Hola,',
            'intro' => 'Recibiste este correo porque estás intentando iniciar sesión en :appName.',
            'code_label' => 'Tu código de acceso es:',
            'expires' => 'Este código vence en :minutes minutos.',
            'ignore_notice' => 'Si no solicitaste este código, podés ignorar este email.',
            'salutation' => '— El equipo de :appName',
        ],
        'password_reset_code' => [
            'subject' => 'Tu código para restablecer la contraseña',
            'greeting' => 'Hola,',
            'intro' => 'Recibiste este correo porque solicitaste restablecer tu contraseña en :appName.',
            'code_label' => 'Tu código para restablecer es:',
            'expires' => 'Este código vence en :minutes minutos.',
            'ignore_notice' => 'Si no solicitaste este código, podés ignorar este email.',
            'salutation' => '— El equipo de :appName',
        ],
    ],
];
