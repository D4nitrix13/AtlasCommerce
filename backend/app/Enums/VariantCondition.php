<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados posibles de una unidad (`product_variants.condition`).
 *
 * Vocabulario tomado del dominio, no inventado acá:
 *
 *  - la migración `2026_10_17_090003_alter_product_variants_add_finish_condition_size_stock`
 *    documenta los valores esperados (`nuevo`, `usado`, `ambos`) y explica
 *    por qué la columna es `VARCHAR(16)` y no un `ENUM` de PostgreSQL:
 *    agregar un valor no debe requerir DDL.
 *  - el seeder (`database/seeders/ProductSeeder.php`) y
 *    `Services/Assets/CatalogAssetsSeeder.php` escriben `'nuevo'`.
 *  - `ProductVariant::scopeByCondition()` documenta el mismo juego de valores.
 *  - En la BD de desarrollo, el 100% de las 421 filas usa `nuevo`.
 *
 * El enum existe para que la validación de la API admin sea UNA sola
 * definición compartida entre el alta y la actualización. Una lista de
 * strings suelta en cada FormRequest es la forma de que los dos endpoints
 * diverjan sin que ningún test existente se rompa.
 *
 * No se agrega un cast al modelo a propósito: `StorefrontProductResource`
 * y los comandos de backfill leen `condition` como string, y castear a
 * enum los cambiaría a objetos `BackedEnum`.
 */
enum VariantCondition: string
{
    case Nuevo = 'nuevo';

    case Usado = 'usado';

    case Ambos = 'ambos';
}
