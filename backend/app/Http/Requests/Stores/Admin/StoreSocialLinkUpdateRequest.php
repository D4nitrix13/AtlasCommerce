<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PUT /admin/stores/{store}/social-links/{link}.
 */
class StoreSocialLinkUpdateRequest extends FormRequest
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
            'url' => ['sometimes', 'url:http,https', 'max:500'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'icon_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
