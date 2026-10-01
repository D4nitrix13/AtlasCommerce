<?php

declare(strict_types=1);

namespace App\Http\Requests\Support\Admin\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /admin/support/tickets — crea ticket manualmente.
 *
 * Body:
 *  - customer_id (int, required, exists:customers)
 *  - subject (string, required, max 200)
 *  - description (string, required)
 *  - priority (low|normal|high|urgent, default normal)
 */
class TicketStoreRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'subject' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['required', 'string', 'min:3', 'max:5000'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
        ];
    }
}
