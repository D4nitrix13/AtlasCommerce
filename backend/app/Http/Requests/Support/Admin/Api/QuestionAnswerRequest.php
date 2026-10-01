<?php

declare(strict_types=1);

namespace App\Http\Requests\Support\Admin\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /admin/support/questions/{question}/answer — registra la respuesta.
 */
class QuestionAnswerRequest extends FormRequest
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
            'answer' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
