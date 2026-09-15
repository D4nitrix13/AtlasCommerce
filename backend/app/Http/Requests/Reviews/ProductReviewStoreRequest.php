<?php

declare(strict_types=1);

namespace App\Http\Requests\Reviews;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /products/{product}/reviews (cliente crea reseña).
 *
 * El customer_id se resuelve del User autenticado — no se acepta del body.
 * Restricción "una reseña por cliente por producto" se enforce en el
 * controller (verifica existencia previa).
 */
class ProductReviewStoreRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'min:10', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.min' => 'La calificación mínima es 1 estrella.',
            'rating.max' => 'La calificación máxima es 5 estrellas.',
            'comment.min' => 'El comentario debe tener al menos 10 caracteres.',
        ];
    }
}
