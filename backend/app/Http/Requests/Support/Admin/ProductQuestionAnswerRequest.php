<?php

declare(strict_types=1);

namespace App\Http\Requests\Support\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PUT /admin/questions/{question}/answer.
 */
class ProductQuestionAnswerRequest extends FormRequest
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
            'answer' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
