<?php

declare(strict_types=1);

namespace App\Services\Assets;

/**
 * Resuelve la raíz del árbol de assets (`assets/products/{tenant}/…`).
 *
 * No hay un default único que valga en todos los entornos: en el
 * container de dev `assets/` está montada en `base_path('assets')`, y en
 * el checkout del host vive en `base_path('../assets')`. El default
 * `base_path('../../assets')` que usan algunos comandos resuelve a
 * `/var/assets` dentro del container, que NO existe.
 *
 * Y acá está la trampa: un path inexistente no lanza error. El comando
 * sigue, la primera `is_dir()` devuelve `false`, y cada producto cuenta
 * como "sin asset dir" — un reporte de 346/346 `images_missing_asset_dir`
 * que se lee como "el catálogo no tiene fotos" cuando en realidad
 * significa "no encontramos dónde están las fotos". Es la misma clase
 * de fallo silencioso que la llamada `where()` de 2 argumentos: cero
 * resultados, cero excepciones, todo verde.
 *
 * Por eso el contrato es `?string`: `null` significa "no encontré la
 * raíz" y el caller DEBE abortar o dizer por qué, nunca seguir con
 * ceroes como si fueran un resultado.
 *
 * El override (`--asset-base` o `ATLAS_ASSET_BASE`) lo lee el caller y
 * lo pasa como `$explicit`: `env()` fuera de `config/` está prohibido
 * por Larastan, y leer una variable de entorno es tarea de la capa CLI,
 * no de un service.
 *
 * Extraído de `BackfillVariantFinishCommand::resolveAssetBase()`, que
 * ya tenía esta corrección en privado.
 */
final class AssetBaseResolver
{
    /**
     * Raíz candidata por defecto, en orden de preferencia.
     *
     * @return list<string>
     */
    public static function defaultCandidates(): array
    {
        return [
            base_path(path: 'assets'),
            base_path(path: '../assets'),
        ];
    }

    /**
     * Devuelve la primera raíz que realmente contiene el árbol del tenant.
     *
     * @param  string|null  $explicit  Override explícito (`--asset-base` o
     *                                 `ATLAS_ASSET_BASE`) ya normalizado a `?string`.
     * @return string|null `null` si ninguna raíz candidata existe.
     */
    public function resolve(?string $explicit, string $tenantSlug): ?string
    {
        $candidates = [];

        if ($explicit !== null && $explicit !== '') {
            $candidates[] = rtrim($explicit, '/');
        }

        foreach (self::defaultCandidates() as $candidate) {
            $candidates[] = rtrim($candidate, '/');
        }

        foreach ($candidates as $candidate) {
            if (is_dir($candidate.'/products/'.$tenantSlug)) {
                return $candidate;
            }
        }

        return null;
    }
}
