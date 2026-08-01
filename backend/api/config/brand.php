<?php

return [
    'logo_path' => env(key: 'BRAND_LOGO_PATH', default: 'images/brand/app-logo.png'),

    'support_email' => env(key: 'BRAND_SUPPORT_EMAIL', default: 'support@example.test'),

    'address' => env(key: 'BRAND_ADDRESS', default: ''),

    'social' => [
        'facebook_url' => env(key: 'BRAND_FACEBOOK_URL', default: null),
        'instagram_url' => env(key: 'BRAND_INSTAGRAM_URL', default: null),
        'facebook_icon_path' => env(key: 'BRAND_FACEBOOK_ICON_PATH', default: 'images/social/facebook.png'),
        'instagram_icon_path' => env(key: 'BRAND_INSTAGRAM_ICON_PATH', default: 'images/social/instagram.png'),
    ],
];
