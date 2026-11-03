<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Catalog\ProductAttribute;
use Illuminate\Database\Seeder;

/**
 * Diccionario de atributos del catálogo.
 *
 * Cada tipo de producto trae sus propios datos, y muchos se miden con
 * unidad: la taza por su capacidad en onzas (o libras), la camisa y la
 * gorra por su talla. Este seeder fija ese vocabulario — qué atributos
 * existen, de qué tipo son, en qué unidad se miden, qué opciones
 * admite un `select` y a qué categorías aplica.
 *
 * Es **idempotente**: usa `updateOrCreate` sobre el `slug`, así que se
 * puede correr cuantas veces haga falta sin duplicar filas. Correrlo
 * después de `php artisan migrate`.
 */
class AttributeDefinitionSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     */
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            ProductAttribute::query()->updateOrCreate(
                attributes: ['slug' => $definition['slug']],
                values: $definition,
            );
        }
    }

    /**
     * Vocabulario de atributos por tipo de producto.
     *
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            // ── drinkware: la capacidad es el dato que define la pieza ──
            [
                'name' => 'Capacidad',
                'slug' => 'capacity',
                'type' => 'number',
                'unit' => 'oz',
                'applies_to' => ['drinkware'],
                'is_filterable' => true,
                'is_required' => false,
            ],

            // ── ropa y accesorios: la talla se enumera, no se mide ──
            [
                'name' => 'Talla',
                'slug' => 'size',
                'type' => 'select',
                'unit' => null,
                'options' => ['CH', 'M', 'G', 'XG'],
                'applies_to' => ['textil', 'accesorio'],
                'is_filterable' => true,
                'is_required' => false,
            ],

            // ── transversal ──
            [
                'name' => 'Material',
                'slug' => 'material',
                'type' => 'select',
                'unit' => null,
                'options' => [
                    'Algodón',
                    'Poliéster',
                    'Cerámica',
                    'Acero inoxidable',
                    'Canvas',
                    'PVC',
                    'Papel kraft',
                ],
                'applies_to' => null,
                'is_filterable' => true,
                'is_required' => false,
            ],
            [
                'name' => 'Peso',
                'slug' => 'weight',
                'type' => 'number',
                'unit' => 'kg',
                'applies_to' => null,
                'is_filterable' => false,
                'is_required' => false,
            ],

            // ── cuidado de tazas y drinkware ──
            [
                'name' => 'Apto para microondas',
                'slug' => 'microwave_safe',
                'type' => 'boolean',
                'unit' => null,
                'applies_to' => ['drinkware'],
                'is_filterable' => true,
                'is_required' => false,
            ],
            [
                'name' => 'Apto para lavavajillas',
                'slug' => 'dishwasher_safe',
                'type' => 'boolean',
                'unit' => null,
                'applies_to' => ['drinkware'],
                'is_filterable' => true,
                'is_required' => false,
            ],
        ];
    }
}
