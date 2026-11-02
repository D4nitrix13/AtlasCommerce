<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Services\Catalog\HoverImageBackfiller;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Deriva `product_images.is_hover` desde el `alt_text` del catálogo
 * sembrado.
 *
 * POR QUÉ UN COMANDO Y NO UNA MIGRACIÓN
 *
 * La migración `2026_10_23_210000_add_is_hover_to_product_images_table` sólo
 * agrega la columna. Dos razones:
 *
 *   1. Es una decisión de catálogo, no de esquema. El vocabulario
 *      `back → backbody → body` es traducción del scraper y puede cambiar
 *      sin que cambie el schema. Meterlo en una migración lo condena al
 *      historial: corregir la regla exigiría una segunda migración,
 *      porque la primera ya corrió en todos los entornos.
 *   2. Una migración de 10.244 filas escribe dentro de la transacción que
 *      Laravel abre para el deploy. Un UPDATE de catálogo bloqueando el
 *      deploy, sin forma de re-correrlo en seco, es peor que un comando
 *      que el operador lanza a propósito y lee.
 *
 * Es además el mismo camino que los otros backfills del repo
 * (`assets:backfill-image-variant`, `variants:backfill-finish`,
 * `variants:backfill-handedness`): un ritual de deploy, no dos.
 *
 * IDEMPOTENCIA
 *
 * Cada producto se recalcula entero y sólo se escribe la diferencia:
 *
 *   - foto elegida que ya estaba marcada → ni un UPDATE;
 *   - producto con hover cuya marca estaba en otra foto → se limpia la
 *     anterior y se marca la correcta;
 *   - producto SIN hover (ninguno de los tres roles) → se limpian las
 *     marcas que tuviera, que serían un error previo.
 *
 * Correrlo dos veces seguidas informa 0 y 0 la segunda vez. Un cambio en
 * la regla tampoco necesita un flag de fuerza: la diferencia se detecta
 * sola, porque el comando compara el estado actual contra el derivado.
 *
 * `--dry-run` recalcula y reporta sin escribir. `--tenant=` acota a un
 * tenant; vacío significa todos.
 */
final class BackfillImageHoverCommand extends Command
{
    protected $signature = 'assets:backfill-image-hover
        {--tenant=instrumentos-musicales-la-voz : Tenant slug (vacío = todos)}
        {--dry-run : Recalcula y reporta sin escribir en la BD}';

    protected $description = 'Deriva product_images.is_hover desde el alt_text del catálogo (back → backbody → body, dentro de la variante de la portada).';

    public function __construct(private readonly HoverImageBackfiller $backfiller)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = (bool) $this->option(key: 'dry-run');
        $tenantOption = $this->option(key: 'tenant');
        $tenant = is_string($tenantOption) ? trim($tenantOption) : '';

        $this->info(string: sprintf(
            '=== BackfillImageHover (tenant=%s, mode=%s) ===',
            $tenant === '' ? 'TODOS' : $tenant,
            $isDryRun ? 'DRY-RUN' : 'WRITE',
        ));

        // El slug del tenant se valida antes de contar nada. Un typo en el
        // argumento saldría como "0 productos" y el operador leería "no hay
        // nada que derivar" en vez de "no encontré ese tenant" — la misma
        // clase de salida honesta que ya documenta
        // `BackfillImageVariantCommand`.
        if ($tenant !== '' && ! $this->storeExists(slug: $tenant)) {
            $this->error(string: "Store con slug='{$tenant}' no existe.");

            return self::FAILURE;
        }

        $products = $this->productsWithImages(tenantSlug: $tenant);

        $this->line(string: sprintf('Productos con imágenes: %d', $products->count()));

        if ($products->isEmpty()) {
            $this->warn('No hay productos con imágenes: no hay nada que derivar.');

            return self::SUCCESS;
        }

        $stats = [
            'products_processed' => 0,
            'products_with_hover' => 0,
            'products_without_hover' => 0,
            'rows_marked' => 0,
            'rows_unmarked' => 0,
            'errors' => [],
        ];

        foreach ($products as $product) {
            try {
                $this->backfillOneProduct(product: $product, isDryRun: $isDryRun, stats: $stats);
                $stats['products_processed']++;
            } catch (Throwable $e) {
                $stats['errors'][] = "Product id={$product->id} ({$product->slug}): ".$e->getMessage();
            }
        }

        $this->newLine();
        $this->info('=== Resumen ===');
        foreach ($stats as $key => $value) {
            if ($key === 'errors') {
                continue;
            }
            $this->line(string: "  {$key}: {$value}");
        }

        if ($stats['errors'] !== []) {
            $this->error('Errores:');
            foreach ($stats['errors'] as $error) {
                $this->line(string: "  ❌ {$error}");
            }

            return self::FAILURE;
        }

        if ($isDryRun && ($stats['rows_marked'] > 0 || $stats['rows_unmarked'] > 0)) {
            $this->newLine();
            $this->comment('DRY-RUN: no se escribió nada. Re-corré sin --dry-run para aplicar.');
        }

        return self::SUCCESS;
    }

    /**
     * ¿Existe una tienda con ese slug?
     *
     * Se valida antes de contar para que un slug equivocado sea un error y
     * no un "0 productos" que el operador lee como "no hay nada que
     * derivar".
     */
    private function storeExists(string $slug): bool
    {
        return DB::table(table: 'stores')
            ->where(column: 'slug', operator: '=', value: $slug)
            ->exists();
    }

    /**
     * Productos con al menos una imagen, acotados al tenant pedido.
     *
     * `tenantSlug` vacío significa "todos los tenants": el catálogo de
     * pruebas y los demás tenants del repo comparten la misma tabla, y la
     * regla no depende de ninguno en particular.
     *
     * @return Collection<int, Product>
     */
    private function productsWithImages(string $tenantSlug): Collection
    {
        $query = Product::query()
            ->whereHas(relation: 'images')
            ->with(relations: ['images'])
            ->orderBy(column: 'id');

        if ($tenantSlug === '') {
            return $query->get();
        }

        // Filtra primero por `store_id` del pivot y recién después entra a
        // la tienda: consultar la relación `stores()` de Product y filtrar
        // por `stores.id` sobre ella arma un self-join que PostgreSQL
        // rechaza con "Duplicate alias: table name product_stores" (el
        // mismo camino que usa ProductImageController::resolveTenantStore).
        $productIds = DB::table(table: 'product_stores')
            ->whereIn(column: 'store_id', values: DB::table(table: 'stores')
                ->where(column: 'slug', operator: '=', value: $tenantSlug)
                ->pluck(column: 'id')
                ->all())
            ->pluck(column: 'product_id')
            ->all();

        return $query->whereIn(column: 'id', values: $productIds)->get();
    }

    /**
     * Aplica la derivación a un producto.
     *
     * El producto se procesa entero y dentro de una transacción, no fila
     * por fila: el invariante "como máximo una foto de hover por producto"
     * sólo se sostiene si el desmarque y la marca ocurren juntos. Un
     * producto con dos fotos marcadas en medio de la corrida mostraría en
     * la card la que el API ordenara al final, sin señal de que hubo un
     * instante inválido.
     *
     * @param  array{
     *     products_processed: int,
     *     products_with_hover: int,
     *     products_without_hover: int,
     *     rows_marked: int,
     *     rows_unmarked: int,
     *     errors: array<int, string>,
     * }  $stats  Contadores por referencia.
     */
    private function backfillOneProduct(Product $product, bool $isDryRun, array &$stats): void
    {
        $images = $product->images->all();
        $expectedIds = [];

        foreach ($this->backfiller->hoverRowsFor(images: $images) as $row) {
            $expectedIds[(int) $row->id] = true;
        }

        if ($expectedIds === []) {
            $stats['products_without_hover']++;
        } else {
            $stats['products_with_hover']++;
        }

        $toMark = [];
        $toUnmark = [];

        foreach ($images as $image) {
            $shouldBeHover = isset($expectedIds[(int) $image->id]);
            $isHover = (bool) $image->is_hover;

            if ($shouldBeHover && ! $isHover) {
                $toMark[] = (int) $image->id;
            } elseif (! $shouldBeHover && $isHover) {
                $toUnmark[] = (int) $image->id;
            }
        }

        if ($toMark === [] && $toUnmark === []) {
            return;
        }

        $stats['rows_marked'] += count($toMark);
        $stats['rows_unmarked'] += count($toUnmark);

        if ($isDryRun) {
            return;
        }

        DB::transaction(function () use ($toMark, $toUnmark): void {
            if ($toUnmark !== []) {
                ProductImage::query()
                    ->whereIn(column: 'id', values: $toUnmark)
                    ->update(['is_hover' => false]);
            }

            if ($toMark !== []) {
                ProductImage::query()
                    ->whereIn(column: 'id', values: $toMark)
                    ->update(['is_hover' => true]);
            }
        });
    }
}