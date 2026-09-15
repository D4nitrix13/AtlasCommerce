<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/stores/{store}/social-links.
 */
class StoreSocialLinkStoreRequest extends FormRequest
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
            'platform' => ['required', 'string', 'in:instagram,facebook,tiktok,whatsapp,youtube,x,linkedin,other'],
            'url' => ['required', 'url:http,https', 'max:500'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'icon_url' => ['nullable', 'url:http,https', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'platform.in' => 'Plataforma no soportada. Usá: instagram, facebook, tiktok, whatsapp, youtube, x, linkedin, other.',
        ];
    }
}
