<?php

declare(strict_types=1);

namespace App\Models\Storefront;

use App\Models\Customers\Customer;
use App\Models\Stores\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud de cotización (RFQ) enviada desde el storefront.
 *
 * Tabla: `rfq_requests`. Es la versión industrial de un ticket: el
 * cliente describe qué necesita (cantidades grandes / productos a
 * medida) y el equipo de ventas responde con una cotización.
 *
 * @property int $id
 * @property string $public_id
 * @property int|null $store_id
 * @property int|null $customer_id
 * @property string $contact_name
 * @property string $contact_email
 * @property string|null $contact_phone
 * @property string|null $company_name
 * @property string $subject
 * @property string $message
 * @property array<int, array<string, mixed>>|null $items
 * @property string $status
 * @property int|null $estimated_total_cents
 * @property string|null $currency_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RfqRequest extends Model
{
    use HasFactory;

    protected $table = 'rfq_requests';

    protected $fillable = [
        'public_id',
        'store_id',
        'customer_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'company_name',
        'subject',
        'message',
        'items',
        'status',
        'estimated_total_cents',
        'currency_code',
    ];

    protected $casts = [
        'items' => 'array',
        'estimated_total_cents' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Genera un public_id tipo `RFQ-{timestamp36}` (base36 del timestamp Unix).
     */
    public static function generatePublicId(): string
    {
        return 'RFQ-'.strtoupper(base_convert(num: (string) time(), from_base: 10, to_base: 36));
    }
}
