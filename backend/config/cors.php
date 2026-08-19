<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| Configuración explícita para Sanctum stateful httpOnly cookies (CR2).
| El default de Laravel 13 tiene `supports_credentials: false`, lo que
| BLOQUEA cookies en cualquier request cross-origin. Como el backend
| panda-estampados sirve 2 eTLD+1s distintos (panda-estampados.com +
| kitsune.com) y el frontend hace requests cross-origin desde `www.`
| y `admin.` subdomains, necesitamos lista explícita de origins.
|
| IMPORTANTE: NO usar wildcard `*` en `allowed_origins` con
| `supports_credentials: true` — los browsers rechazan la combinación
| (CORS spec RFC 6454 §3.2).
|
| Para agregar un nuevo tenant: agregar sus 3 hosts (apex + www + admin)
| en `allowed_origins` Y en `SANCTUM_STATEFUL_DOMAINS` (ver .env.example).
*/

return [
    'paths' => ['api/*', 'sanctum/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:4321',
        'http://panda-estampados.test',
        'http://www.panda-estampados.test',
        'http://admin.panda-estampados.test',
        'http://kitsune.test',
        'http://www.kitsune.test',
        'http://admin.kitsune.test',
        'http://mm-boutique.test',
        'http://www.mm-boutique.test',
        'http://admin.mm-boutique.test',
        'http://morales-store.test',
        'http://www.morales-store.test',
        'http://admin.morales-store.test',
        'https://panda-estampados.com',
        'https://www.panda-estampados.com',
        'https://admin.panda-estampados.com',
        'https://kitsune.com',
        'https://www.kitsune.com',
        'https://admin.kitsune.com',
        'https://mm-boutique.com',
        'https://www.mm-boutique.com',
        'https://admin.mm-boutique.com',
        'https://morales-store.com',
        'https://www.morales-store.com',
        'https://admin.morales-store.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*', 'X-XSRF-TOKEN'],

    'exposed_headers' => ['X-XSRF-TOKEN'],

    'max_age' => 0,

    'supports_credentials' => true,
];