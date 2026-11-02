<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `product_images.is_hover`: la foto que la card del catálogo
 * muestra al pasar el cursor.
 *
 * POR QUÉ UNA COLUMNA
 *
 * Hasta ahora la card derivaba el hover del `alt_text` buscando el sufijo
 * " - vista back", con `backbody` y `body` como escalones. Ese
 * vocabulario existe porque el scraper rotula las fotos del fabricante; el
 * administrador del catálogo no piensa en roles — sube "la foto 3 de mi
 * guitarra" y marca para qué la quiere. El dato va en las FILAS, y no en
 * el producto, por la misma razón que `is_primary`: la card necesita una
 * URL (con su `srcset`), no una referencia.
 *
 * GRANULARIDAD: EN TODAS LAS FILAS DE LA FOTO
 *
 * `is_primary` se marca en una sola fila porque *selecciona* la portada:
 * con elegir una fila alcanza. `is_hover` describe la foto completa, y la
 * card la renderiza en el mismo stack que la portada con `srcset`. Si
 * sólo la fila de 1600 quedara marcada, cualquier consulta que necesite
 * "cuáles son las resoluciones de la foto de hover" — o que no colapse
 * por rol, como el caso de `galleryPerRole()` con `width` desconocido —
 * devolvería una de las cuatro en lugar de la foto. Con la marca
 * replicada, el invariante "como máximo una foto de hover por producto"
 * se comprueba limpiando `is_hover` en todas las filas del producto,
 * igual que `clearPrimaryFlag()`.
 *
 * `NOT NULL DEFAULT false`: la columna tiene que responder siempre. Con
 * `NULL` cada consumidor tiene que decidir qué significa "no lo sé", y
 * `false` ya significa exactamente "esta foto no es la de hover".
 *
 * EL VALOR INICIAL NO LO PONE ESTA MIGRACIÓN
 *
 * Derivar la bandera desde el `alt_text` es una decisión de catálogo, no
 * de esquema. Va en `php artisan assets:backfill-image-hover`, que es
 * idempotente y admite `--dry-run`. Ponerlo acá metería el vocabulario
 * `back`/`backbody`/`body` en el historial del esquema, del que no se
 * puede salir sin escribir otra migración — y una migración de 10.244
 * filas bloquea el deploy durante el UPDATE.
 *
 * ÍNDICE PARCIAL
 *
 * `(product_id) WHERE is_hover`, no `(product_id, is_hover)`: en el
 * catálogo sembrado sólo ~1 de cada 30 filas de un producto queda en
 * `true`, así que el índice completo guardaría 10.000 entradas que nunca
 * matchean. El parcial sólo almacena las que sí. Sin `CONCURRENTLY` a
 * propósito — no puede correr dentro de la transacción que Laravel abre
 * para la migración, y sobre 10.244 filas el bloqueo es despreciable.
 */
return new class extends Migration
{
    /**
     * Nombre del índice parcial. Reutilizado por `down()`.
     */
    private const INDEX = 'product_images_hover_idx';

    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::table(table: 'product_images', callback: function (Blueprint $table): void {
            $table->boolean(column: 'is_hover')
                ->default(value: false);
        });

        DB::statement(<<<'SQL'
            CREATE INDEX product_images_hover_idx
                ON product_images (product_id)
                WHERE is_hover
            SQL);
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);

        Schema::table(table: 'product_images', callback: function (Blueprint $table): void {
            $table->dropColumn(columns: ['is_hover']);
        });
    }
};