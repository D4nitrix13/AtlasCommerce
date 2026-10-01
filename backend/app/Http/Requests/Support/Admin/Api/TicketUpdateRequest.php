<?php

declare(strict_types=1);

namespace App\Http\Requests\Support\Admin\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /admin/support/tickets/{ticket}.
 */
class TicketUpdateRequest extends FormRequest
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
            'subject' => ['sometimes', 'string', 'min:3', 'max:200'],
            'description' => ['sometimes', 'string', 'min:3', 'max:5000'],
            'status' => ['nullable', 'string', 'in:open,in_progress,closed'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
        ];
    }
}
