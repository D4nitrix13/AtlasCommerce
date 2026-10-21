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
     *
     * Default `false` (soft mode): el `AssetController` sigue sirviendo
     * el binario aunque el path no matchee la convención (paths legacy
     * sin hash, p.ej. `tenants/{slug}/branding/logo.webp`) y emite un
     * log estructurado `asset.path.malformed`. Esto existe para que el
     * rollout de Fase 5 (backfill al formato con hash) pueda migrar el
     * 100% del catálogo SIN dejar al storefront sin imágenes durante el
     * proceso.
     *
     * Pasar a `true` SOLO cuando el rate de warnings `asset.path.malformed`
     * en logs caiga a ~0 (post Fase 6). Flippearlo antes rompe la PDP y
     * los tests: el `StorefrontAssetTest` asume soft mode como default
     * porque los fixtures usan paths sin hash.
     *
     * Nota histórica: la creación de este archivo en `2950b7a3` setteó
     * `default: true` por error y dejó 13 tests fallando en master. La
     * docstring del archivo original (commit `9d565597`) ya declaraba
     * `false` como default. Esta corrección vuelve al contrato original.
     */
    'path_validation' => [
        'strict' => env(key: 'ASSETS_PATH_VALIDATION_STRICT', default: false),
    ],
];
