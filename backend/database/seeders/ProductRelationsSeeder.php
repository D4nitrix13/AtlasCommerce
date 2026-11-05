<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProductRelationType;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductRelation;
use Illuminate\Database\Seeder;

/**
 * Relaciones curadas de recomendación entre productos.
 *
 * Alimenta los rieles de la PDP "Frecuentemente Comprados Juntos"
 * (`bought_together`) y "También Te Puede Gustar" (`you_may_also_like`).
 * El catálogo es chico, así que las parejas están escritas a mano acá
 * en lugar de calcularse desde co-occurrence de órdenes.
 *
 * Idempotente: por cada producto origen existente borra sus filas y las
 * vuelve a insertar. Los slugs ausentes se saltan sin error (el catálogo
 * puede no estar sembrado todavía — correr DESPUÉS del seeder de
 * catálogo de la instancia).
 *
 * Nunca cruzar tenants: cada tienda se relaciona solo consigo misma
 * (el storefront filtra por `product_stores.is_visible` igual, pero un
 * cruce sería un bundle sin sentido).
 */
class ProductRelationsSeeder extends Seeder
{
    /**
     * Origen → destinos por riel (slugs de `products`).
     *
     * @var array<string, array<string, list<string>>>
     */
    private const RELATIONS = [
        'taza-ceramica' => [
            'bought_together' => ['libreta', 'tote-bag'],
            'you_may_also_like' => ['libreta', 'hoodie'],
        ],
        'playera-manga-corta' => [
            'bought_together' => ['tote-bag', 'libreta'],
            'you_may_also_like' => ['hoodie', 'gorra-trucker'],
        ],
        'hoodie' => [
            'bought_together' => ['gorra-trucker', 'tote-bag'],
            'you_may_also_like' => ['playera-manga-corta', 'gorra-trucker'],
        ],
        'libreta' => [
            'bought_together' => ['taza-ceramica', 'tote-bag'],
            'you_may_also_like' => ['taza-ceramica', 'tote-bag'],
        ],
        'nendoroid' => [
            'bought_together' => ['llavero-acrylic'],
            'you_may_also_like' => ['figura-escala-1-7', 'llavero-acrylic'],
        ],
        'figura-escala-1-7' => [
            'bought_together' => ['mango-tomo'],
            'you_may_also_like' => ['nendoroid'],
        ],
        'playera-anime' => [
            'bought_together' => ['llavero-acrylic', 'taza-tematica'],
            'you_may_also_like' => ['nendoroid', 'taza-tematica'],
        ],
        'taza-tematica' => [
            'bought_together' => ['nendoroid', 'llavero-acrylic'],
            'you_may_also_like' => ['nendoroid', 'llavero-acrylic'],
        ],
    ];

    /**
     * Ejecuta el seeder.
     */
    public function run(): void
    {
        // Todos los slugs referenciados, origen y destino: si solo
        // cargamos los orígenes, los destinos que no son origen de
        // ninguna fila (tote-bag, llavero-acrylic…) quedan fuera del
        // mapa y sus relaciones se saltan en silencio.
        $allSlugs = collect(self::RELATIONS)
            ->flatMap(fn (array $rails, string $origin): array => [
                $origin,
                ...array_merge(...array_values($rails)),
            ])
            ->unique()
            ->values()
            ->all();

        $idsBySlug = Product::query()
            ->whereIn('slug', $allSlugs)
            ->pluck('id', 'slug');

        foreach (self::RELATIONS as $originSlug => $rails) {
            $originId = $idsBySlug->get($originSlug);
            if ($originId === null) {
                continue;
            }

            ProductRelation::query()
                ->where('product_id', $originId)
                ->delete();

            foreach ($rails as $type => $targetSlugs) {
                $position = 0;
                foreach ($targetSlugs as $targetSlug) {
                    $targetId = $idsBySlug->get($targetSlug);
                    if ($targetId === null || $targetId === $originId) {
                        continue;
                    }

                    ProductRelation::query()->create(attributes: [
                        'product_id' => $originId,
                        'related_product_id' => $targetId,
                        'type' => $type,
                        'position' => $position,
                    ]);
                    $position++;
                }
            }
        }

        $this->command?->info('ProductRelationsSeeder: relaciones curadas listas.');
    }
}
