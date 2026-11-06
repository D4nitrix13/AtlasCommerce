<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth Socialite providers (Google + Facebook)
    |--------------------------------------------------------------------------
    |
    | El `redirect` configurado acá es un fallback por env. En runtime el
    | `SocialAuthController::redirect` lo override con la URL per-host
    | (`$request->getSchemeAndHttpHost().'/api/auth/social/{provider}/callback'`)
    | vía `redirectUrl($url)` para mantener el cookie domain aislado por
    | tenant (ver `SetSessionDomainFromHost`).
    |
    | `OAUTH_REDIRECT_BASE_URL` puede apuntar al storefront o al admin
    | según el provider console donde se registró la callback. Por
    | defecto queda null (mecanismo per-host toma el control).
    |
    */

    'google' => [
        'client_id' => env(key: 'GOOGLE_CLIENT_ID', default: null),
        'client_secret' => env(key: 'GOOGLE_CLIENT_SECRET', default: null),
        // El prefijo de versión es parte del path: las rutas viven bajo
        // /api/v1 (bootstrap/app.php). Sin el `v1` Google redirige a un 404.
        'redirect' => env(key: 'OAUTH_REDIRECT_BASE_URL', default: null).'/api/v1/auth/social/google/callback',
    ],

    'facebook' => [
        'client_id' => env(key: 'FACEBOOK_CLIENT_ID', default: null),
        'client_secret' => env(key: 'FACEBOOK_CLIENT_SECRET', default: null),
        'redirect' => env(key: 'OAUTH_REDIRECT_BASE_URL', default: null).'/api/v1/auth/social/facebook/callback',
    ],

];
