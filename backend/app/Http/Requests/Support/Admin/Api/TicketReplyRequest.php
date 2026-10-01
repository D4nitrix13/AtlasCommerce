<?php

declare(strict_types=1);

namespace App\Http\Requests\Support\Admin\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /admin/support/tickets/{ticket}/reply — body del mensaje.
 */
class TicketReplyRequest extends FormRequest
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
            'message' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }
}
