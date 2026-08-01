<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación del endpoint POST /api/payments.
 *
 * Reglas:
 *  - `invoice_id` debe existir.
 *  - `payment_method_id` debe existir.
 *  - `amount_cents` entero > 0 y <= 99_999_999.99 (cap defensivo).
 *  - `reference` opcional (transferencia, cheque, número de operación).
 *  - `notes` opcional (max 1000 chars).
 */
class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'reference' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
