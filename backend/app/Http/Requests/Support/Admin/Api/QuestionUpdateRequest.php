<?php

declare(strict_types=1);

namespace App\Http\Requests\Support\Admin\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /admin/support/questions/{question}.
 */
class QuestionUpdateRequest extends FormRequest
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
            'question' => ['sometimes', 'string', 'min:3', 'max:1000'],
            'answer' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
