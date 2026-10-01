<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /storefront/rfq (solicitud de cotización).
 *
 * El endpoint es público (no requiere auth), pero igual imponemos
 * rate limiting vía middleware y validación fuerte para evitar abuso.
 */
class RfqStoreRequest extends FormRequest
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
            'contact_name' => ['required', 'string', 'min:2', 'max:200'],
            'contact_email' => ['required', 'email', 'max:200'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:200'],
            'subject' => ['required', 'string', 'min:3', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:100000'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'estimated_total_cents' => ['nullable', 'integer', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
        ];
    }
}
