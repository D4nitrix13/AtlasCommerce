<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesVariantOwnership;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PUT /admin/products/{product}/images/order.
 *
 * CONTRATO — ORDEN COMPLETO O NADA
 *
 * `image_ids` es el orden NUEVO de la galería y tiene que ser el juego
 * COMPLETO de imágenes del producto: los mismos ids que devuelve
 * `GET /admin/products/{product}/images`, reordenados. Si falta uno, sobra
 * uno, se repite o alguno es de otro producto, la respuesta es 422 y no se
 * escribe absolutamente nada.
 *
 * Por qué no se acepta un reorder parcial: `sort_order` es el ORDEN de la
 * galería, no un índice arbitrario. Un envío parcial ("mové estas dos")
 * necesita una regla para decidir qué pasa con las posiciones intermedias
 * —¿se renumeran? ¿se abre un hueco?— y cualquier respuesta razonable deja
 * la galería en un estado que nadie pidió. Con el juego completo, el
 * resultado es exactamente el que el admin ve en pantalla, o nada.
 *
 * ¿POR QUÉ SE MANDAN IDS DE FILA Y NO DE FOTO?
 *
 * No existe una fila que represente a la foto: la identidad de la foto es
 * la terna `(variant_id, sort_order)` (ver
 * {@see \App\Services\Catalog\ProductPhotoGrouper}). El endpoint acepta los
 * ids de fila que ya devuelve el listado, y reescribe `sort_order` de TODAS
 * las filas de cada foto al mismo valor, así que la agrupación por foto
 * sobrevive al reordenamiento: la galería muestra las fotos en el orden
 * pedido, cada una con sus 4 resoluciones intactas.
 *
 * Los ids no tienen que venir contiguos por foto: el servidor agrupa antes
 * de escribir y usa la PRIMERA aparición de cada foto para decidir su
 * posición. Aceptarlos en cualquier orden hace que el cliente no tenga que
 * conocer el agrupamiento para construir el payload.
 */
class ProductImageReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        return [
            'image_ids' => ['required', 'array', 'min:1'],
            // `distinct` es parte del contrato y no una formalidad: un id
            // repetido hace que dos fotos compartan posición y que la
            // galería tenga un orden que el admin nunca pidió.
            'image_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image_ids.required' => 'Mandá el orden nuevo como image_ids.',
            'image_ids.array' => 'image_ids tiene que ser una lista de ids de imagen.',
            'image_ids.min' => 'image_ids no puede estar vacío.',
            'image_ids.*.integer' => 'Cada id de image_ids tiene que ser un entero.',
            'image_ids.*.distinct' => 'image_ids no puede repetir el mismo id.',
        ];
    }

    /**
     * Ids del payload, ya validados como enteros.
     *
     * El chequeo de COMPLETITUD (que el juego sea exactamente el del
     * producto) NO vive acá: necesita la base, y por eso lo hace
     * `ProductImageController::reorder()` antes de abrir la transacción.
     *
     * @return list<int>
     */
    public function imageIds(): array
    {
        return array_map(
            static fn (mixed $id): int => (int) $id,
            array_values((array) $this->validated(key: 'image_ids')),
        );
    }
}