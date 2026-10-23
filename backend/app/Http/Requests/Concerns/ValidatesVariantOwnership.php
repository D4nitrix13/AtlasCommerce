<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Regla compartida por los tres endpoints que escriben `variant_id` en
 * `product_images`: el alta por URL, el upload por binario y el update.
 *
 * ── Por qué la regla vive acá y no en cada FormRequest ───────────────
 *
 * Porque es una regla de CORRECTEZ, no de forma: si mañana cada endpoint
 * la declara por su cuenta, el próximo que se agregue se la olvida y el
 * agujero se abre sin que ningún test existente se rompa. Un solo lugar
 * del que salen las reglas y los mensajes hace que "los tres la aplican"
 * sea una propiedad estructural y no una promesa.
 *
 * ── Por qué la variante tiene que ser DEL producto de la URL ─────────
 *
 * `product_images.variant_id` sólo tiene FK contra `product_variants.id`:
 * la base acepta la fila aunque la variante sea de otro producto. Y eso
 * no es un detalle cosmético — el storefront agrupa la galería por
 * `(variant_id, sort_order)` y el PDP filtra con
 * `img.variantId === selectedVariantId`:
 *
 *  - La foto de la variante A atada al producto B NUNCA entra en el
 *    filtro del producto B (los ids de variante son globalmente únicos,
 *    así que jamás coinciden), pero sí aparece en el set completo cuando
 *    no hay acabado seleccionado, como si fuera una foto de ese producto.
 *  - Y el admin acaba de etiquetarla en el panel como "esta foto es el
 *    acabado Cereza", etiqueta que el PDP jamás va a cumplir.
 *
 * O sea: la galería miente en las dos direcciones. Por eso un `variant_id`
 * ajeno es 422 y no un attach silencioso.
 *
 * El mensaje no distingue "no existe" de "es de otro producto": responder
 * distinto confirmaría la existencia de filas de otro tenant.
 */
trait ValidatesVariantOwnership
{
    /**
     * Reglas de `variant_id` para un producto de la ruta.
     *
     * @return array<int, string|Exists>
     */
    protected function variantIdRules(): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists(table: 'product_variants', column: 'id')
                ->where(column: 'product_id', value: $this->routeProductId()),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function variantIdMessages(): array
    {
        return [
            'variant_id.integer' => 'La variante indicada no es válida.',
            'variant_id.exists' => 'Esa variante no pertenece a este producto.',
        ];
    }

    /**
     * Id del producto de la ruta.
     *
     * Sale de la ruta y no del body a propósito: si el id viniera del
     * payload, la validación de la pertenencia sería circular (el cliente
     * podría declarar cualquier producto como "el suyo").
     *
     * El route-model-binding entrega el modelo, pero en algunos grupos de
     * rutas llega el id crudo; se aceptan las dos formas.
     */
    protected function routeProductId(): int
    {
        $product = $this->route('product');

        return is_object($product) ? (int) $product->id : (int) $product;
    }
}
