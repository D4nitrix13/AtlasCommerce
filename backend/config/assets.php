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
| El flag `path_validation.strict` controla el modo de validación del
| `AssetController` para paths de FTP:
|   - false (default, Fase 2): soft mode. Paths que no matchean la
|     convención (regex de StorageKeyDeriver) se sirven igual pero
|     emiten un log warning estructurado. Esto nos da observabilidad
|     sin riesgo de romper la PDP con paths legacy.
|   - true (post Fase 5 backfill, Fase 6): strict mode. Paths malformados
|     devuelven 403. Solo se flippea cuando el rate de warnings cae a ~0.
|
| Override via env `ASSETS_PATH_VALIDATION_STRICT` en instancias
| específicas (staging, dev) sin tocar el archivo.
*/

return [
    'path_validation' => [
        'strict' => env(key: 'ASSETS_PATH_VALIDATION_STRICT', default: false),
    ],
];
