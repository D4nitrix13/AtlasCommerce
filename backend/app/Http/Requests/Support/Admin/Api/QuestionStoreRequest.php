<?php

declare(strict_types=1);

namespace App\Http\Requests\Support\Admin\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /admin/support/questions — crea pregunta manualmente (ej. FAQ).
 */
class QuestionStoreRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'question' => ['required', 'string', 'min:3', 'max:1000'],
            'answer' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
