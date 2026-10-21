<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Catalog\Product;
use App\Services\Catalog\VariantHandednessResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Sincroniza `products.is_left_handed` con la evidencia textual del nombre.
 *
 * Problema que resuelve (diagnóstico Oct 2026, La Voz Instrumentos):
 *
 *     products.name ILIKE '%left handed%'  ->  18 productos
 *     products.is_left_handed = false      ->  los mismos 18
 *     product_variants.handedness          ->  'left-handed' (backfill OK)
 *
 * El PDP dice "zurdo" (lee la variante) mientras el producto dice
 * "diestro" (lee el flag) en cualquier parte que use `is_left_handed`.
 *
 * DECISIÓN DE DATOS, explícita y del owner del catálogo: el nombre es la
 * fuente de verdad. El flag nunca se cargó — 411/411 filas quedaron NULL o
 * `false` antes del backfill de variantes y el import del catálogo lo dejó
 * en `false` — así que un `false` ahí no significa "diestro", significa
 * "nunca se preguntó". Actualizarlo es rellenar un dato faltante, no
 * contradecir un dato existente.
 *
 * `catalog:backfill-handedness` reporta este desacuerdo como *señal en
 * conflicto* justamente para no elegir solo. Este comando es la otra
 * mitad: elige, y deja la elección escrita y auditable.
 *
 * ⚠️ Sólo toca los productos cuyo texto delata mano izquierda Y cuyo flag
 * está en `false`. Nunca escribe `false` sobre un `true`: un producto
 * zurdo marcado a mano no se revierte porque el nombre no lo mencione.
 *
 * Idempotente: re-correrlo con `--write` sobre una base ya sincronizada
 * reporta `products_updated: 0` y no escribe nada.
 *
 * Uso:
 *   php artisan catalog:sync-left-handed-flag            # dry-run (default)
 *   php artisan catalog:sync-left-handed-flag --write
 *   php artisan catalog:sync-left-handed-flag --product=sg-standard --write
 */
final class SyncLeftHandedFlagCommand extends Command
{
    protected $signature = 'catalog:sync-left-handed-flag
        {--write : Write products.is_left_handed (default: report only, writes nothing)}
        {--product= : Limit to a single product slug}';

    protected $description = 'Sync products.is_left_handed = true for products whose name says left-handed but whose flag is false.';

    public function handle(VariantHandednessResolver $resolver): int
    {
        $isWrite = (bool) $this->option(key: 'write');
        $productSlug = $this->option(key: 'product');

        $this->info(string: sprintf(
            '=== SyncLeftHandedFlag (mode=%s%s) ===',
            $isWrite ? 'WRITE' : 'DRY-RUN',
            is_string($productSlug) && $productSlug !== '' ? ", product={$productSlug}" : '',
        ));

        $stats = [
            'products_scanned' => 0,
            'products_updated' => 0,
            'products_already_flagged' => 0,
            'errors' => [],
        ];

        /** @var list<array{id: int, slug: string, name: string, was_left_handed: bool}> $rows */
        $rows = [];

        $this->candidateQuery(productSlug: $productSlug)
            ->chunk(count: 200, callback: function ($products) use ($resolver, $isWrite, &$stats, &$rows): void {
                foreach ($products as $product) {
                    $stats['products_scanned']++;

                    // El WHERE usa el mismo vocabulario que el resolver
                    // para acotar el scan, pero la decisión la toma el
                    // resolver: si el texto no delata mano izquierda, la
                    // fila no se toca aunque el filtro la haya traído.
                    if (! $resolver->nameSaysLeftHanded(product: $product)) {
                        continue;
                    }

                    if ($resolver->flagSaysLeftHanded(product: $product)) {
                        $stats['products_already_flagged']++;

                        continue;
                    }

                    $rows[] = [
                        'id' => (int) $product->id,
                        'slug' => (string) $product->slug,
                        'name' => (string) $product->name,
                        'was_left_handed' => false,
                    ];

                    if ($isWrite) {
                        try {
                            $product->forceFill(['is_left_handed' => true])->save();
                        } catch (Throwable $e) {
                            $stats['errors'][] = "Producto id={$product->id} ({$product->slug}): ".$e->getMessage();

                            continue;
                        }
                    }

                    $stats['products_updated']++;
                }
            });

        if ($rows !== []) {
            $this->newLine();
            $this->line('Productos con nombre "left handed" y products.is_left_handed = false:');
            $this->table(
                headers: ['id', 'slug', 'is_left_handed (actual)', 'name'],
                rows: array_map(
                    static fn (array $row): array => [
                        $row['id'],
                        $row['slug'],
                        $row['was_left_handed'] ? 'true' : 'false',
                        $row['name'],
                    ],
                    $rows,
                ),
            );
        }

        $this->newLine();
        $this->line(string: sprintf('Productos evaluados:                 %d', $stats['products_scanned']));
        $this->line(string: sprintf('Ya marcados is_left_handed = true:  %d', $stats['products_already_flagged']));
        $this->line(string: sprintf(
            'A sincronizar (nombre dice zurdo, flag dice false): %d',
            $stats['products_updated'],
        ));

        if ($stats['errors'] !== []) {
            $this->newLine();
            foreach ($stats['errors'] as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (! $isWrite) {
            $this->newLine();
            $this->comment('DRY-RUN: no se escribió nada. Re-corré con --write para aplicar.');
        }

        return self::SUCCESS;
    }

    /**
     * Productos candidatos: el texto delata mano izquierda Y el flag
     * sigue en `false`.
     *
     * El `ILIKE` sobre nombre y slug es sólo un filtro de scan; el
     * patrón sale de `VariantHandednessResolver::leftHandedPatterns()`
     * para que el SQL y la resolución no puedan divergir. El resolver
     * vuelve a validar cada fila antes de escribir, así que un `ILIKE`
     * demasiado generoso no produce escrituras de más.
     *
     * @param  string|bool|null  $productSlug  Valor crudo de `--product`.
     * @return Builder<Product>
     */
    private function candidateQuery(string|bool|null $productSlug): Builder
    {
        $query = Product::query()
            ->where(
                column: 'is_left_handed',
                operator: '=',
                value: false,
            )
            ->where(function (Builder $textMatch): void {
                foreach (VariantHandednessResolver::leftHandedPatterns() as $pattern) {
                    $textMatch->orWhereRaw(
                        sql: 'name ILIKE ?',
                        bindings: ['%'.$pattern.'%'],
                    );
                }
            });

        if (is_string($productSlug) && $productSlug !== '') {
            $query->where(column: 'slug', operator: '=', value: $productSlug);
        }

        return $query;
    }
}
