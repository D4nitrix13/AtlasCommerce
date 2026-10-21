<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Support\Str;

/**
 * Deriva el acabado real (`finish_slug` / `finish_label`) de un producto
 * a partir de su nombre, cuando el `finish_slug` que tiene en BD no es
 * más que una copia del slug del producto.
 *
 * Contexto (diagnóstico Oct 2026, La Voz Instrumentos): de las 411
 * variantes, 306 tienen `finish_slug === products.slug`. Eso no es un
 * acabado — es el handle del producto repetido — y termina renderizado
 * como "ACABADO: 1960 Les Paul Standard Reissue Iced Tea Burst Left
 * Handed" en la PDP. Otras 105 ya traen el acabado real.
 *
 * Vocabulario explícito, NO un regex abierto.
 *
 * El criterio de diseño acá es "degradación honesta": un nombre que no
 * matchea el vocabulario devuelve `null` y el comando deja la variante
 * como está. Inventar un acabado a partir de palabras sueltas produce
 * basura ("Black" en "Premium Gigbag Les Paul Sg Black") que después
 * nadie sabe corregir. Un `null` es un dato pendiente, una basura es un
 * bug.
 *
 * Por eso el vocabulario es cerrado y curado contra el vocabulario real
 * de Gibson / Epiphone / Kramer / Mesa-Boogie / Maestro que aparece en
 * el catálogo, y por eso el comando que lo usa acota la búsqueda a las
 * categorías donde "acabado" es un concepto del dominio (`guitars`,
 * `basses`). En `cases`, `apparel`, `strings`, `parts`, `amps`,
 * `pedals` y `capos` el color NO es un acabado: es un dato del
 * producto.
 *
 * Orden de matching: gana la coincidencia más larga. "Vintage Cherry
 * Sunburst" tiene que ganarle a "Cherry", y "Vintage Cherry" a
 * "Cherry". Sin eso, medio catálogo quedaría mal etiquetado.
 */
final class VariantFinishDeriver
{
    /**
     * Vocabulario de acabados: label legible → slug canónico.
     *
     * El slug es el kebab-case del label, que es exactamente la
     * convención que ya usan las 105 variantes con acabado real
     * (`vintage-sunburst` → "Vintage Sunburst",
     * `ec-heritage-cherry-sunburst` → "Ec Heritage Cherry Sunburst").
     *
     * No se incluyen aquí los colores genéricos ("Red", "Black",
     * "White") porque son ambiguos: matchearían en fundas, ropa y
     * amplificadores. Los colores genéricos que sí son acabados de
     * guitarra están listados explícitamente con su matiz real
     * ("Ebony", "Cherry", "Oxblood", "Natural", "Purple").
     *
     * @var array<string, string>
     */
    private const FINISH_LABELS = [
        // Bursts de varios términos — el matching más largo gana.
        'Vintage Cherry Sunburst',
        'Heritage Cherry Sunburst',
        'Washed Cherry Sunburst',
        'Faded Heritage Cherry Sunburst',
        'Cherry Sunburst',
        'Vintage Sunburst',
        'Vintage Tobacco Burst',
        'Vintage Cherry',
        'Sixties Cherry',
        'Blueberry Burst',
        'Iced Tea Burst',
        'Raspberry Tea Burst',
        'Bourbon Burst',
        'Tobacco Burst',
        'Dark Purple Burst',
        'Autumn Burst',
        'Tri Burst',
        'Triburst',
        'Antique Silverburst',
        'Antique Natural',
        'Sparkling Burgundy',
        'Smoked Almond Metallic',
        'Forest Green Metallic',
        'Crimson Metallic',
        'Radio Blue Metallic',
        'Royal Blue Metallic',
        'California Coral',
        'Heather Poly',
        'Golden Mist Poly',
        'Aqua Mist',
        'Frost Blue',
        'Polaris White',
        'Classic White',
        'Alpine White',
        'Silver Fox',
        'Sunset Yellow',
        'Bumblebee Yellow',
        'Radiant Red',
        'Defender Red',
        'Jumper Red',
        'Wine Red',
        'Red Satin',
        'Hot Pink',
        'Miami Blue',
        'Miami Pink',
        'Pacific Blue',
        'Splash Blue',
        'Vice City Blue',
        'Flip Flop Blue',
        'Kerry Green',
        'Wild Ivy',
        'Majestic Purple',
        'Transparent Red',
        'Transparent Purple',
        'Transparent Ebony',
        'Ultramarine Burst',
        'Ultraviolet Burst',
        'Magenta Burst',
        'Aqua Burst',
        'Anniversary Gold',
        'Aged Ebony',
        'Quicksilver Shift',
        'Firestorm Shift',
        'Natural',
        'Cherry',
        'Ebony',
        'Oxblood',
        'Fuchsia',
        'Purple',
    ];

    /**
     * Categorías donde "acabado" (color del cuerpo) es un concepto del
     * dominio. Fuera de este set el backfill no toca nada.
     *
     * @var list<string>
     */
    public const FINISHABLE_CATEGORY_SLUGS = [
        'guitars',
        'basses',
    ];

    /**
     * Categorías que el comando cuenta aparte para el reporte. Son las
     * 306 - (guitars + basses) variantes cuyo finish es un fallback y
     * que además no son instrumentos: fundas, cuerdas, ropa, amps.
     *
     * @var list<string>
     */
    private const NON_FINISHABLE_CATEGORY_SLUGS = [
        'apparel',
        'cases',
        'strings',
        'parts',
        'amps',
        'pedals',
        'capos',
    ];

    /**
     * Labels ordenados de más largo a más corto, precompilados una vez.
     *
     * `array{label: string, slug: string, pattern: string}`
     *
     * @var list<array{label: string, slug: string, pattern: string}>|null
     */
    private ?array $orderedVocabulary = null;

    /**
     * Intenta derivar el acabado a partir del nombre del producto.
     *
     * @param  string  $productName  `products.name` (puede traer el sufijo de marca entre paréntesis).
     * @return array{slug: string, label: string}|null `null` cuando el nombre no matchea el vocabulario.
     */
    public function deriveFromProductName(string $productName): ?array
    {
        $haystack = $this->normalizeForMatching($productName);

        foreach ($this->orderedVocabulary() as $entry) {
            if (preg_match($entry['pattern'], $haystack) === 1) {
                return [
                    'slug' => $entry['slug'],
                    'label' => $entry['label'],
                ];
            }
        }

        return null;
    }

    /**
     * ¿Esta categoría admite un acabado?
     *
     * `guitars` y `basses` sí. `instrumento-musical` (el bucket de
     * fallback del seeder) NO: en el catálogo actual ese bucket está
     * poblado por amps, que no tienen acabado. Se deja explícito para
     * que si el bucket cambia, el comando reporte la diferencia en vez
     * de etiquetar amps con un color.
     */
    public function isFinishableCategory(?string $categorySlug): bool
    {
        return $categorySlug !== null
            && in_array($categorySlug, self::FINISHABLE_CATEGORY_SLUGS, true);
    }

    /**
     * ¿Esta categoría es una donde "acabado" no aplica por dominio?
     *
     * Existe para el reporte: son las variantes que el comando saltea
     * y cuenta como `skipped_non_instrument`.
     */
    public function isNonFinishableCategory(?string $categorySlug): bool
    {
        return $categorySlug !== null
            && in_array($categorySlug, self::NON_FINISHABLE_CATEGORY_SLUGS, true);
    }

    /**
     * Vocabulario ordenado por longitud descendente, compilado a regex
     * con boundaries.
     *
     * El boundary importa: sin él, "Cherry" matchearía dentro de
     * "Chernobyl" y "Ebony" dentro de "Ebonyish". Se usa `\b` sobre
     * la forma normalizada (no acentuada, no puntuada).
     *
     * @return list<array{label: string, slug: string, pattern: string}>
     */
    private function orderedVocabulary(): array
    {
        if ($this->orderedVocabulary !== null) {
            return $this->orderedVocabulary;
        }

        $entries = [];
        foreach (self::FINISH_LABELS as $label) {
            $entries[] = [
                'label' => $label,
                'slug' => $this->slugify($label),
                'pattern' => '/\b'.preg_quote($this->normalizeForMatching($label), '/').'\b/u',
            ];
        }

        usort(
            $entries,
            static fn (array $a, array $b): int => mb_strlen($b['label']) <=> mb_strlen($a['label']),
        );

        return $this->orderedVocabulary = $entries;
    }

    /**
     * Normaliza el texto para matching: minúsculas, sin acentos y con
     * separadores reduzidos a un solo espacio.
     *
     * El nombre del catálogo mezcla ", " (Kramer) y " - " (Epiphone),
     * así que la puntuación tiene que ser neutra para que un finish
     * matchee exista o no según sus palabras, no según el separador
     * que le tocó.
     */
    private function normalizeForMatching(string $text): string
    {
        $normalized = mb_strtolower($text);

        // Sustituye cualquier corrida de no-letras por un espacio simple.
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * Slug canónico de un acabado: kebab-case ASCII del label.
     *
     * Mismo criterio que el seeder (`ucwords(str_replace('-', ' ', ...))`
     * para el label; el slug es el input original), y mismo resultado
     * que las 105 variantes que ya traen acabado real.
     */
    private function slugify(string $label): string
    {
        return Str::slug($label);
    }
}
