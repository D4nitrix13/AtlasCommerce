<?php

declare(strict_types=1);

namespace App\Http\Requests\Wishlist;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /api/account/wishlists.
 */
class WishlistStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
