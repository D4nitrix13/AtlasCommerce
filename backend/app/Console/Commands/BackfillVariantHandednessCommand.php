<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Catalog\ProductVariant;
use App\Services\Catalog\VariantHandednessResolver;
use Illuminate\Console\Command;

/**
 * Backfill de `product_variants.handedness`.
 *
 * Problema que resuelve (diagnóstico Oct 2026): la columna existe desde
 * la migración `2026_10_19_160000_add_left_handed_flags_to_products`
 * (`VARCHAR(16) NULL`, valor de dominio `'left-handed'`, `NULL` =
 * regular) pero está en NULL en las 411 variantes del catálogo. El
 * seeder `assets:seed-catalog` sí escribe el campo, pero la última
 * corrida del seeder fue anterior a la migración: `updateOrCreate` no
 * podía setear una columna que todavía no existía, así que la fila
 * nació sin ella y nadie la volvió a tocar.
 *
 * Por qué NO se deriva solo de `products.is_left_handed`: esa columna
 * también quedó sin poblar (default `false` de la migración, misma
 * causa). Con el flag como fuente única, este comando no cambiaría
 * ninguna de las 411 variantes. Ver `VariantHandednessResolver` para
 * el orden de señales y el porqué.
 *
 * Idempotente: sólo escribe cuando el valor calculado difiere del
 * almacenado, así que re-correrlo sobre una base ya backfilleada
 * reporta `variants_updated: 0`.
 *
 * Uso:
 *   php artisan catalog:backfill-handedness --dry-run
 *   php artisan catalog:backfill-handedness
 */
final class BackfillVariantHandednessCommand extends Command
{
    protected $signature = 'catalog:backfill-handedness
        {--dry-run : Report what would change without writing}
        {--product= : Limit to a single product slug}';

    protected $description = 'Derive product_variants.handedness from the product handle and name.';

    public function handle(VariantHandednessResolver $resolver): int
    {
        $isDryRun = (bool) $this->option(key: 'dry-run');
        $productSlug = $this->option(key: 'product');

        $this->info(string: sprintf(
            '=== BackfillVariantHandedness (mode=%s%s) ===',
            $isDryRun ? 'DRY-RUN' : 'WRITE',
            is_string($productSlug) && $productSlug !== '' ? ", product={$productSlug}" : '',
        ));

        $query = ProductVariant::query()->with(relations: ['product.category']);

        if (is_string($productSlug) && $productSlug !== '') {
            $query->whereHas(
                relation: 'product',
                callback: fn ($productQuery) => $productQuery
                    ->where(column: 'slug', operator: '=', value: $productSlug),
            );
        }

        $stats = [
            'variants_scanned' => 0,
            'variants_updated' => 0,
            'variants_unchanged' => 0,
            'variants_left_handed' => 0,
            'products_flag_stale' => 0,
            'products_flag_conflicts' => 0,
            'errors' => [],
        ];

        /** @var list<string> $flagStaleProducts */
        $flagStaleProducts = [];

        $query->chunk(
            count: 200,
            callback: function ($variants) use ($resolver, $isDryRun, &$stats, &$flagStaleProducts): void {
                foreach ($variants as $variant) {
                    $product = $variant->product;

                    if ($product === null) {
                        $stats['errors'][] = "Variant id={$variant->id} sin producto padre.";

                        continue;
                    }

                    $stats['variants_scanned']++;

                    $resolved = $resolver->resolveForProduct(product: $product);

                    $textSaysLeft = $resolver->nameSaysLeftHanded(product: $product);
                    $flagSaysLeft = $resolver->flagSaysLeftHanded(product: $product);

                    // Un producto cuyo texto dice "left-handed" pero cuyo
                    // flag quedó en `false` es un flag stale: la
                    // migración puso el default y el seeder nunca corrió
                    // después. No se "arregla" acá (el brief es backfill de
                    // variantes), pero se reporta con números.
                    if ($textSaysLeft && ! $flagSaysLeft) {
                        $stats['products_flag_stale']++;
                        $flagStaleProducts[$product->slug] = true;
                    }

                    if ($textSaysLeft !== $flagSaysLeft) {
                        $stats['products_flag_conflicts']++;
                    }

                    if ($resolved === VariantHandednessResolver::LEFT_HANDED) {
                        $stats['variants_left_handed']++;
                    }

                    if ($variant->handedness === $resolved) {
                        $stats['variants_unchanged']++;

                        continue;
                    }

                    if (! $isDryRun) {
                        $variant->forceFill(['handedness' => $resolved])->save();
                    }

                    $stats['variants_updated']++;
                }
            },
        );

        $this->line(string: sprintf('Variantes escaneadas:     %d', $stats['variants_scanned']));
        $this->line(string: sprintf('Variantes actualizadas:   %d', $stats['variants_updated']));
        $this->line(string: sprintf('Variantes sin cambio:    %d', $stats['variants_unchanged']));
        $this->line(string: sprintf('Variantes zurdas:        %d', $stats['variants_left_handed']));

        $this->newLine();
        $this->line(string: sprintf(
            'Productos con nombre "left-handed" y products.is_left_handed = false: %d',
            $stats['products_flag_stale'],
        ));

        if ($flagStaleProducts !== []) {
            $this->line('  '.implode(', ', array_slice(array_keys($flagStaleProducts), 0, 25)));
        }

        $this->line(string: sprintf(
            'Señales en conflicto (nombre vs products.is_left_handed): %d',
            $stats['products_flag_conflicts'],
        ));

        if ($stats['errors'] !== []) {
            $this->newLine();
            foreach ($stats['errors'] as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($isDryRun) {
            $this->newLine();
            $this->comment('DRY-RUN: no se escribió nada.');
        }

        return self::SUCCESS;
    }
}
