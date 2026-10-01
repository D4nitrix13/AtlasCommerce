<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /account/bulk-cart — agrega un item al bulk-cart.
 *
 * Body:
 *  - product_id (int, required, exists:products)
 *  - product_variant_id (int, optional, exists:product_variants)
 *  - quantity (int, required, min:1)
 *  - notes (string, optional, max:500)
 */
class BulkCartItemStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
