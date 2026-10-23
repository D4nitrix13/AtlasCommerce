<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesVariantFinish;
use App\Models\Catalog\Product;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/variants.
 *
 * Reglas:
 *  - product_id: requerido, existe en products.
 *  - name: requerido, max 160.
 *  - sku: único en product_variants.sku, max 120.
 *  - price_cents: int >= 0.
 *  - cost_cents: int >= 0.
 *  - barcode: opcional, único en product_variants.barcode (si viene).
 *  - is_active: bool, default true.
 *  - finish_slug: kebab-case, único DENTRO del producto, y REQUERIDO si
 *    la categoría del producto admite acabado (`guitars`, `basses`).
 *  - finish_label, finish_hex, condition, stock, handedness: reglas
 *    compartidas con el update — ver {@see ValidatesVariantFinish}.
 */
class ProductVariantStoreRequest extends FormRequest
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
        $productId = $this->input(key: 'product_id');
        $product = is_numeric($productId)
            ? Product::query()->with(relations: 'category')->find(id: (int) $productId)
            : null;

        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'name' => ['required', 'string', 'min:1', 'max:160'],
            'sku' => ['required', 'string', 'min:1', 'max:120', 'unique:product_variants,sku'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'cost_cents' => ['nullable', 'integer', 'min:0'],
            'barcode' => ['nullable', 'string', 'max:120', 'unique:product_variants,barcode'],
            'is_active' => ['nullable', 'boolean'],
            'finish_slug' => $this->finishSlugRules(
                finishableCategory: $this->productCategoryIsFinishable(product: $product),
                productId: (int) $productId,
            ),
            ...$this->variantPresentationRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'El producto indicado no existe.',
            'sku.unique' => 'Ya existe una variante con ese SKU.',
            'barcode.unique' => 'Ya existe una variante con ese código de barras.',
            ...$this->variantFinishMessages(),
        ];
    }
}
