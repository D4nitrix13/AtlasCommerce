<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesVariantFinish;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el body de PUT/PATCH /admin/variants/{variant}.
 *
 * Todos los campos son `sometimes`. SKU y barcode únicos (excepto
 * el row actual).
 *
 * `finish_slug` es `sometimes` también, y por eso la obligatoriedad se
 * evalúa contra el estado POSTERIOR del merge, no contra el cuerpo: un
 * update parcial que no menciona el acabado tiene que seguir siendo
 * válido, y uno que lo mande vacío tiene que ser 422 si la categoría
 * admite acabado. Ver {@see ValidatesVariantFinish}.
 */
class ProductVariantUpdateRequest extends FormRequest
{
    use ValidatesVariantFinish;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $variant = $this->route('variant');
        $variantId = is_object($variant) ? (int) $variant->id : (int) $variant;
        $product = $this->variantProduct(variant: $variant);

        return [
            'name' => ['sometimes', 'string', 'min:1', 'max:160'],
            'sku' => [
                'sometimes',
                'string',
                'min:1',
                'max:120',
                Rule::unique(table: 'product_variants', column: 'sku')->ignore($variantId),
            ],
            'price_cents' => ['sometimes', 'integer', 'min:0'],
            'cost_cents' => ['sometimes', 'integer', 'min:0'],
            'barcode' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
                Rule::unique(table: 'product_variants', column: 'barcode')->ignore($variantId),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'finish_slug' => ['sometimes', ...$this->finishSlugRules(
                finishableCategory: $this->productCategoryIsFinishable(product: $product),
                productId: (int) ($product?->id ?? 0),
                ignoreVariantId: $variantId,
            )],
            ...$this->variantPresentationRulesForPartialUpdate(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.unique' => 'Ya existe una variante con ese SKU.',
            'barcode.unique' => 'Ya existe una variante con ese código de barras.',
            ...$this->variantFinishMessages(),
        ];
    }

    /**
     * Producto dueño de la variante de la ruta.
     *
     * El route-model-binding entrega el modelo, pero en algunos grupos de
     * rutas llega el id crudo; se aceptan las dos formas, igual que en
     * {@see \App\Http\Requests\Concerns\ValidatesVariantOwnership::routeProductId()}.
     */
    private function variantProduct(mixed $variant): ?Product
    {
        $resolved = $variant instanceof ProductVariant
            ? $variant
            : ProductVariant::query()->find(id: (int) (is_object($variant) ? $variant->id : $variant));

        return $resolved?->product()->first();
    }
}
