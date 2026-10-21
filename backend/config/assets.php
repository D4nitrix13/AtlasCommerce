<?php

declare(strict_types=1);

return [

    /**
     * Disco desde el que el AssetController sirve los archivos del tenant.
     *
     * En producción es `ftp`: los archivos viven en el servidor FTP y el
     * controller hace de proxy porque el browser no puede hablar FTP.
     *
     * En desarrollo se usa `local`: el seeder puebla el disco local con
     * `--storage-disk=local` y el FTP queda vacío, así que servir desde
     * `ftp` daba 404 en todas las imágenes.
     */
    'disk' => env(key: 'ASSETS_DISK', default: 'ftp'),

    /**
     * Cache del contenido binario servido. Default `redis` en el
     * controller cuando no está definido.
     */
    'cache_store' => env(key: 'ASSETS_CACHE_STORE', default: 'redis'),

    /** TTL de la cache, en segundos. */
    'cache_ttl' => (int) env(key: 'ASSETS_CACHE_TTL', default: 300),

    /**
     * Firma HMAC de las URLs de assets. Exige `expires` + `sig` cuando
     * está activa. El backend la tiene apagada porque el frontend sirve
     * los assets sin firmar.
     */
    'url_signing' => [
        'required' => env(key: 'ASSETS_URL_SIGNING_REQUIRED', default: false),
        'secret' => env(key: 'ASSETS_URL_SIGNING_SECRET'),
    ],

    /**
     * Validación de path: rechaza assets que no vivan bajo el namespace
     * del tenant activo ni bajo `shared/`.
     */
    'path_validation' => [
        'strict' => env(key: 'ASSETS_PATH_VALIDATION_STRICT', default: true),
    ],
];