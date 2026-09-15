<?php

declare(strict_types=1);

namespace App\Http\Requests\Support\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PUT /admin/support/tickets/{ticket}.
 *
 * Admin usa esto para cambiar status/priority (subject/description
 * NO son editables — preservan trazabilidad de lo que el cliente
 * reportó originalmente).
 */
class SupportTicketUpdateRequest extends FormRequest
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
            'status' => ['sometimes', 'string', 'in:open,in_progress,closed'],
            'priority' => ['sometimes', 'string', 'in:low,normal,high,urgent'],
        ];
    }
}
