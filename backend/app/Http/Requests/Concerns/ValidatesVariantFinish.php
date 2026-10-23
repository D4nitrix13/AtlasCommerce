<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\VariantCondition;
use App\Models\Catalog\Product;
use App\Services\Catalog\VariantFinishDeriver;
use App\Services\Catalog\VariantHandednessResolver;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Validation\Rule;

/**
 * Reglas compartidas por el alta y la actualización de variantes para los
 * campos de acabado, condición y stock (`finish_slug`, `finish_label`,
 * `finish_hex`, `condition`, `stock`, `handedness`).
 *
 * ── Por qué la regla vive acá y no en cada FormRequest ─────────────────
 *
 * Mismo motivo que {@see ValidatesVariantOwnership}: es una regla de
 * CORRECTEZ, no de forma. Si el alta y la actualización la declararan por
 * su cuenta, el próximo endpoint que escriba variantes se la olvida y el
 * agujero se abre sin romper ningún test existente. Un solo lugar del que
 * salen reglas y mensajes hace que "ambos las aplican" sea una propiedad
 * estructural y no una promesa.
 *
 * ── `finish_slug`: cuándo es obligatorio ───────────────────────────────
 *
 * El dominio ya está decidido y codificado en
 * {@see VariantFinishDeriver::isFinishableCategory()}: en `guitars` y
 * `basses` el acabado (color del cuerpo) es un concepto del dominio; en
 * `cases`, `apparel`, `strings`, `parts`, `amps`, `pedals` y `capos` el
 * color NO es un acabado sino un atributo del producto.
 *
 * Por lo tanto: `finish_slug` es OBLIGATORIO en el alta de una variante de
 * categoría finishable y OPCIONAL en el resto. Y en la actualización, una
 * variante de categoría finishable NO puede quedarse sin acabado: se
 * rechaza el envío explícito de `null`.
 *
 * El motivo concreto es que `StorefrontProductResource::resolveVariants()`
 * filtra las variantes sin `finish_slug`: hoy eso no se nota porque el
 * seeder siempre escribe un acabado (copia el slug del producto cuando el
 * producto tiene una sola variante), pero en cuanto un admin crea una
 * variante por la API sin acabado, la variante desaparece de la PDP sin
 * error en ningún lado. Exigir el acabado en el borde de la API convierte
 * un dato faltante silencioso en un 422 con mensaje.
 *
 * NO se toca el filtro del storefront en este slice: el filtro es ciego a
 * la categoría y, sin `finish_slug`, no puede distinguir "variante de un
 * capo (el color es un atributo, no un acabado)" de "variante de guitarra
 * sin acabado". Cambiarlo exige revisar el picker de la PDP y es una
 * decisión aparte.
 *
 * ── Por qué `finish_slug` se valida contra `product_id` ────────────────
 *
 * Porque los índices reales de la tabla no imponen unicidad alguna sobre
 * `finish_slug` (el único índice que lo incluye es el compuesto NO único
 * `product_variants_product_finish_condition_idx (product_id,
 * finish_slug, condition)`, que existe para el lookup de la PDP). La
 * unicidad por producto es entonces una invariante de dominio que se
 * valida en la API: dos variantes del MISMO producto con el mismo slug no
 * son distinguibles en el picker de acabado. Global sería incorrecto —
 * acabados como `ebony` o `natural` se repiten legítimamente entre
 * productos distintos.
 *
 * ── Trampa de Laravel: `nullable` + `required` NO se combinan ──────────
 *
 * `Validator::isNotNullIfMarkedAsNullable()` desactiva TODA regla no
 * implícita del atributo cuando su valor es `null`, incluida la de
 * `required`. Por eso `['nullable', Rule::requiredIf(true), ...]` dejaría pasar un
 * `finish_slug: null` en una variante de guitarra. Las dos ramas de reglas
 * (`required` para categoría finishable, `nullable` para el resto) se
 * arman separadas justamente para no caer ahí.
 */
trait ValidatesVariantFinish
{
    /**
     * Formato de `finish_hex`: `#RRGGBB` o `#RRGGBBAA`.
     *
     * Misma forma que la que ya usa `Admin\ThemeUpdateRequest` para
     * `accent_color` y compañía (`/^#[0-9A-Fa-f]{6}$/`), con el grupo de
     * alpha opcional que exige la columna: `finish_hex` es `VARCHAR(9)` y
     * su comentario de migración documenta `#RRGGBB` o `#RRGGBBAA`. Con el
     * sufijo opcional la longitud máxima sigue siendo 9.
     */
    private const FINISH_HEX_PATTERN = '/^#[0-9A-Fa-f]{6}(?:[0-9A-Fa-f]{2})?$/';

    /**
     * Kebab-case, mismo criterio que `slug` en ProductStoreRequest y
     * CategoryStoreRequest: `products.slug` es la fuente de la que el
     * seeder copia `finish_slug`, así que el formato tiene que coincidir.
     */
    private const FINISH_SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Reglas de `finish_slug`.
     *
     * @param  bool  $finishableCategory  ¿La categoría del producto admite acabado?
     * @param  int  $productId  Producto dueño de la variante.
     * @param  int|null  $ignoreVariantId  Variante que se excluye del chequeo de unicidad (update).
     * @return array<int, mixed>
     */
    protected function finishSlugRules(bool $finishableCategory, int $productId, ?int $ignoreVariantId = null): array
    {
        $unique = Rule::unique(table: 'product_variants', column: 'finish_slug')
            ->where(static function (QueryBuilder $query) use ($productId): void {
                $query->where('product_id', $productId);
            });

        if ($ignoreVariantId !== null) {
            $unique->ignore($ignoreVariantId);
        }

        return [
            $finishableCategory ? 'required' : 'nullable',
            'string',
            // `finish_slug` widened a 180 en 2026_10_22_165823 para igualar
            // `products.slug`, que es de donde lo copia el seeder.
            'max:180',
            'regex:'.self::FINISH_SLUG_PATTERN,
            $unique,
        ];
    }

    /**
     * Reglas de los campos de presentación de la variante que no dependen
     * de la categoría.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function variantPresentationRules(): array
    {
        return [
            'finish_label' => ['nullable', 'string', 'max:120'],
            'finish_hex' => ['nullable', 'string', 'regex:'.self::FINISH_HEX_PATTERN],
            'condition' => ['nullable', Rule::enum(VariantCondition::class)],
            'stock' => ['nullable', 'integer', 'min:0'],
            // `NULL` significa "regular / diestro" por convención de la
            // migración 2026_10_19_160000; el único valor poblado es
            // 'left-handed', la constante que ya define el resolver.
            //
            // `Rule::in()` se llama posicional a propósito: su firma usa
            // `func_get_args()`, así que un named argument se comportaría
            // según cómo PHP llene los slots.
            'handedness' => ['nullable', Rule::in([VariantHandednessResolver::LEFT_HANDED])],
        ];
    }

    /**
     * Las mismas reglas de presentación, con `sometimes` delante.
     *
     * En el update, `sometimes` es lo que hace que una clave ausente no se
     * valide NADA (y por lo tanto no se borre el valor almacenado), mientras
     * que una clave presente con `null` sí se valida. Ese matiz es
     * justamente lo que impide que un `nullable` deje pasar el `required`
     * de una categoría finishable.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function variantPresentationRulesForPartialUpdate(): array
    {
        return array_map(
            static fn (array $rules): array => ['sometimes', ...$rules],
            $this->variantPresentationRules(),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function variantFinishMessages(): array
    {
        $validConditions = array_map(
            static fn (VariantCondition $condition): string => $condition->value,
            VariantCondition::cases(),
        );

        return [
            'finish_slug.required' => 'Una variante de esta categoría necesita un acabado (finish_slug).',
            'finish_slug.regex' => 'El slug del acabado debe estar en kebab-case.',
            'finish_slug.unique' => 'Este producto ya tiene una variante con ese acabado.',
            'finish_hex.regex' => 'El campo :attribute debe ser un color hexadecimal (#RRGGBB o #RRGGBBAA).',
            'condition.*' => 'La condición debe ser una de: '.implode(', ', $validConditions).'.',
            'handedness.*' => 'La única mano admitida es "'.VariantHandednessResolver::LEFT_HANDED
                .'" (o null para diestro).',
            'stock.min' => 'El stock no puede ser negativo.',
            'stock.integer' => 'El stock debe ser un número entero.',
        ];
    }

    /**
     * ¿La categoría del producto admite acabado?
     *
     * Delega el criterio en {@see VariantFinishDeriver} en vez de repetir
     * la lista de slugs acá: esa clase ya es la fuente de verdad y su
     * PHPDoc explica por qué `instrumento-musical` (el bucket de fallback
     * del seeder) está explícitamente fuera.
     */
    protected function productCategoryIsFinishable(?Product $product): bool
    {
        if ($product === null) {
            return false;
        }

        $product->loadMissing(relations: 'category');

        return (new VariantFinishDeriver)->isFinishableCategory($product->category?->slug);
    }
}
