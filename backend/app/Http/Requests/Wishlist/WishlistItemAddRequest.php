<?php

declare(strict_types=1);

namespace App\Http\Requests\Wishlist;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /api/account/wishlists/{wishlist}/items.
 */
class WishlistItemAddRequest extends FormRequest
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
        ];
    }
}
