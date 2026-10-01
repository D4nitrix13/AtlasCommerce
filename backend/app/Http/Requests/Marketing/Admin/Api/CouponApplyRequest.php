<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing\Admin\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /admin/marketing/coupons/{coupon}/apply — testea la aplicabilidad
 * de un cupón contra un monto hipotético sin crear un pedido.
 *
 * Body:
 *  - amount_cents (int, required): monto del carrito en centavos.
 *  - currency_code (string, required): código ISO 4217.
 */
class CouponApplyRequest extends FormRequest
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
            'amount_cents' => ['required', 'integer', 'min:0'],
            'currency_code' => ['required', 'string', 'size:3'],
        ];
    }
}
