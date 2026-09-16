<?php

declare(strict_types=1);

return [
    'auth' => [
        'email_otp' => [
            'subject' => 'Your access code',
            'greeting' => 'Hi,',
            'intro' => 'You received this email because you are trying to sign in to :appName.',
            'code_label' => 'Your access code is:',
            'expires' => 'This code expires in :minutes minutes.',
            'ignore_notice' => 'If you did not request this code, you can safely ignore this email.',
            'salutation' => '— The :appName team',
        ],
        'password_reset_code' => [
            'subject' => 'Your password reset code',
            'greeting' => 'Hi,',
            'intro' => 'You received this email because you requested a password reset on :appName.',
            'code_label' => 'Your password reset code is:',
            'expires' => 'This code expires in :minutes minutes.',
            'ignore_notice' => 'If you did not request this code, you can safely ignore this email.',
            'salutation' => '— The :appName team',
        ],
    ],
];
