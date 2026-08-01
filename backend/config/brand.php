<?php

return [
    // Logo fallback cuando la tienda no tiene logo propio
    'default_logo_path' => env(key: 'BRAND_DEFAULT_LOGO_PATH', default: 'images/shared/brand/app-logo.webp'),

    // Favicon fallback
    'default_favicon_path' => env(key: 'BRAND_DEFAULT_FAVICON_PATH', default: 'images/shared/brand/favicon.webp'),

    // OG image fallback
    'default_og_image_path' => env(key: 'BRAND_DEFAULT_OG_IMAGE_PATH', default: 'images/shared/placeholders/store-og-placeholder.webp'),

    // Cover image fallback
    'default_cover_image_path' => env(key: 'BRAND_DEFAULT_COVER_IMAGE_PATH', default: 'images/shared/placeholders/store-cover-placeholder.webp'),

    // Colores fallback
    'default_primary_color' => env(key: 'BRAND_DEFAULT_PRIMARY_COLOR', default: '#111827'),
    'default_secondary_color' => env(key: 'BRAND_DEFAULT_SECONDARY_COLOR', default: '#f97316'),

    // Soporte (global, no por tienda)
    'support_email' => env(key: 'BRAND_SUPPORT_EMAIL', default: 'support@example.test'),

    // Resolución de tienda
    'default_store_slug' => env(key: 'DEFAULT_STORE_SLUG', default: 'panda-estampados'),
    'resolution_mode' => env(key: 'STORE_RESOLUTION_MODE', default: 'domain'),

    // Iconos de redes sociales (fallback compartido)
    'social' => [
        'facebook_icon_path' => env(key: 'BRAND_FACEBOOK_ICON_PATH', default: 'images/shared/icons/social/facebook.webp'),
        'instagram_icon_path' => env(key: 'BRAND_INSTAGRAM_ICON_PATH', default: 'images/shared/icons/social/instagram.webp'),
    ],
];
