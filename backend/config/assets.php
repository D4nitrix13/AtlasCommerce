<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AtlasCommerce — Assets configuration
|--------------------------------------------------------------------------
|
| Configuración del subsystem de assets (categoría A — ver
| docs/architecture/assets-naming-convention.md §2).
|
| Path validation:
|   El flag `path_validation.strict` controla el modo de validación del
|   `AssetController` para paths de FTP:
|     - false (default, Fase 2): soft mode. Paths que no matchean la
|       convención (regex de StorageKeyDeriver) se sirven igual pero
|       emiten un log warning estructurado. Esto nos da observabilidad
|       sin riesgo de romper la PDP con paths legacy.
|     - true (post Fase 5 backfill, Fase 6): strict mode. Paths malformados
|       devuelven 403. Solo se flippea cuando el rate de warnings cae a ~0.
|
| URL signing (Fase 3):
|   - `url_signing.required` (default false) enciende la verificación
|     HMAC de query params `expires=<ts>&sig=<hex>` antes de servir un
|     asset. Fase 6 flipea a `true` post-backfill.
|   - `url_signing.ttl` ventana de validez de la firma (default 300s).
|   - `url_signing.secret` HMAC key. Si está vacío en env, cae a
|     `config('app.key')` para evitar breakage en instancias que no
|     setean la env var explícitamente.
|
| Cache (Fase 3):
|   - `cache_store` permite cambiar del default `redis` a `file` u otro
|     store sin tocar código (útil en instancias single-tenant o
|     cuando Redis está degradado).
|   - `cache_ttl` ventana de cache del contenido binario (default 300s,
|     5min). Drift histórico: el código de Fase 2 usaba 86400 (24h)
|     hard-coded — Fase 3 lo corrige y lo deja configurable.
|
| Override via env (ASSETS_PATH_VALIDATION_STRICT,
| ASSETS_URL_SIGNING_*, ASSETS_CACHE_*) en instancias específicas
| (staging, dev) sin tocar el archivo.
*/

return [
    'path_validation' => [
        'strict' => env(key: 'ASSETS_PATH_VALIDATION_STRICT', default: false),
    ],

    'url_signing' => [
        'required' => env(key: 'ASSETS_URL_SIGNING_REQUIRED', default: false),
        'ttl' => env(key: 'ASSETS_URL_SIGNING_TTL', default: 300),
        'secret' => env(key: 'ASSETS_URL_SIGNING_SECRET') ?: config(key: 'app.key'),
    ],

    'cache_store' => env(key: 'ASSETS_CACHE_STORE', default: 'redis'),

    'cache_ttl' => env(key: 'ASSETS_CACHE_TTL', default: 300),
];
