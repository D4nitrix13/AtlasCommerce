<?php

declare(strict_types=1);

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /products/{product}/questions (cliente hace pregunta).
 */
class ProductQuestionStoreRequest extends FormRequest
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
            'question' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
