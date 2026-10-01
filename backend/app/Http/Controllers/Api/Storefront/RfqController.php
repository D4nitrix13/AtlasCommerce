<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\RfqStoreRequest;
use App\Http\Resources\Storefront\RfqResource;
use App\Models\Customers\Customer;
use App\Models\Storefront\RfqRequest;
use App\Models\Stores\Store;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint público de solicitud de cotización (RFQ).
 *
 * POST /api/v1/storefront/rfq
 *
 * Genera un ticket público `RFQ-{timestamp36}` (base36 del timestamp
 * Unix actual) que el cliente ve en su email y el equipo de ventas
 * referencia en el pipeline. No requiere autenticación — el contacto
 * se declara en el payload.
 */
class RfqController extends Controller
{
    public function __construct(
        private readonly StoreContextService $storeContext,
    ) {}

    public function __invoke(RfqStoreRequest $request): JsonResponse
    {
        $store = $this->storeContext->getStore();

        $customerId = null;
        if ($store instanceof Store) {
            $email = (string) $request->input('contact_email');
            $customer = Customer::query()->where('email', $email)->first();
            if ($customer !== null) {
                $customerId = $customer->id;
            }
        }

        $rfq = RfqRequest::query()->create([
            'public_id' => RfqRequest::generatePublicId(),
            'store_id' => $store?->id,
            'customer_id' => $customerId,
            'contact_name' => (string) $request->input('contact_name'),
            'contact_email' => (string) $request->input('contact_email'),
            'contact_phone' => $request->input('contact_phone'),
            'company_name' => $request->input('company_name'),
            'subject' => (string) $request->input('subject'),
            'message' => (string) $request->input('message'),
            'items' => $request->input('items', []),
            'status' => 'pending',
            'estimated_total_cents' => $request->input('estimated_total_cents'),
            'currency_code' => $request->input('currency_code'),
        ]);

        return response()->json(data: [
            'data' => (new RfqResource(resource: $rfq))->resolve(),
        ], status: Response::HTTP_CREATED);
    }
}
