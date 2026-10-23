<?php

declare(strict_types=1);

use App\Services\Assets\UploadWidthResolver;

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

    /**
     * Validación de las imágenes que el admin sube por la API.
     *
     * Los cuatro valores viven acá y no inline en el FormRequest porque son
     * política operativa: cambiarlos no es un refactor, es cambiar qué
     * acepta el sistema y qué guarda en disco.
     */
    'upload' => [

        /**
         * MIME types aceptados. Se comparan contra el contenido REAL del
         * archivo (Symfony lo adivina con fileinfo), no contra el header
         * `Content-Type` que manda el cliente — si no, un `.php` renamed
         * a `.jpg` pasa el filtro y después el conversor explota.
         *
         * Se excluyen SVG y GIF a propósito: SVG es un formato ejecutable
         * (scripts embebidos) y GIF no tiene transparencia fiable en el
         * pipeline WebP de este repo. Los tres formatos restantes son los
         * que produce cualquier cámara o editor de fotos decente.
         */
        'mimes' => ['image/jpeg', 'image/png', 'image/webp'],

        /**
         * Tope del archivo ORIGINAL subido, en kilobytes.
         *
         * 8192 KB (8 MB) es el tamaño típico de foto de catálogo: un JPEG de 1600 px de
         * este repo pesa 150-400 KB, así que 8 MB deja margen de sobra para
         * el original de una cámara sin convertir, y a la vez deja el
         * `upload_max_filesize` del container (64 MB) como red de
         * seguridad, no como el límite real de negocio.
         *
         * El límite protege el disco Y la RAM: convertir 4 anchos abre la
         * imagen 4 veces, así que el costo escala con el tamaño de entrada.
         */
        'max_kilobytes' => (int) env(key: 'ASSETS_UPLOAD_MAX_KB', default: 8192),

        /**
         * Calidad WebP de salida. 85 es el valor que ya usa el pipeline
         * del repo (`scripts/migrate-gibson-images.sh`), así que las
         * imágenes subidas por el admin tienen el mismo peso que las del
         * catálogo sembrado y no se nota el origen al served.
         */
        'quality' => (int) env(key: 'ASSETS_UPLOAD_QUALITY', default: 85),

        /**
         * Anchos que se generan por cada imagen subida, en px.
         *
         * Override por entorno con CSV ascendente: `ASSETS_UPLOAD_WIDTHS`.
         * El default sale de {@see UploadWidthResolver::DEFAULT_WIDTHS}
         * para que la lista esté escrita una sola vez en el repo.
         *
         * Contrato que {@see UploadWidthResolver::resolve()} valida y hace
         * cumplir: enteros positivos, sin repetidos y en orden ascendente.
         * Una lista que no lo cumpla se RECHAZA con un error explícito en
         * vez de corregirse en silencio — ver el docblock de esa clase para
         * por qué (y para qué pasa con la lista vacía).
         *
         * Anchos mayores que el original se descartan: el conversor no
         * hace upscale, así que un JPEG de 800 px con esta lista produce
         * dos archivos (400 y 800), no cuatro.
         */
        'widths' => UploadWidthResolver::fromEnvList(
            list: (string) env(
                key: 'ASSETS_UPLOAD_WIDTHS',
                default: implode(separator: ',', array: UploadWidthResolver::DEFAULT_WIDTHS),
            ),
        ),
    ],
];
