<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tracking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tracking\TrackingLookupRequest;
use App\Http\Resources\Shipping\PublicShipmentResource;
use App\Models\Shipping\Shipment;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint público de tracking por número de guía (RF-SHIP-083).
 *
 * Ruta: GET /tracking/{tracking_code}?email=...
 *
 * Doble factor: el cliente debe conocer AMBOS el tracking_code Y el
 * email de la orden — sólo con uno no se obtiene info (protección
 * contra fishing con códigos obtenidos de listings públicos).
 *
 * Multi-tenant: el lookup se hace dentro de la tienda resuelta por
 * middleware `store.domain`. Cross-tenant devuelve 404.
 */
class TrackingController extends Controller
{
    public function __construct(
        private readonly StoreContextService $storeContext,
    ) {}

    /**
     * GET /tracking/{tracking_code}
     */
    public function show(
        TrackingLookupRequest $request,
        string $tracking_code,
    ): JsonResponse {
        $store = $this->storeContext->getStore();
        if ($store === null) {
            return response()->json(
                data: ['message' => 'No hay tienda activa.'],
                status: Response::HTTP_NOT_FOUND,
            );
        }

        $shipment = Shipment::query()
            ->whereHas(
                'order',
                fn ($q) => $q->where(column: 'store_id', operator: '=', value: $store->id),
            )
            ->where(column: 'tracking_code', operator: '=', value: $tracking_code)
            ->first();

        if ($shipment === null) {
            return response()->json(
                data: ['message' => 'No encontramos un envío con ese código.'],
                status: Response::HTTP_NOT_FOUND,
            );
        }

        $order = $shipment->order;
        $providedEmail = (string) $request->input('email');
        $orderEmail = (string) ($order->customer?->email ?? '');
        if ($order === null || strcasecmp($orderEmail, $providedEmail) !== 0) {
            // Mismo mensaje que "no existe" — no leakeamos si el código existe.
            return response()->json(
                data: ['message' => 'No encontramos un envío con ese código.'],
                status: Response::HTTP_NOT_FOUND,
            );
        }

        $shipment->load(['order.items', 'order.customer', 'currency', 'shippingCompany']);

        return response()->json(data: [
            'data' => (new PublicShipmentResource(resource: $shipment))->resolve(),
        ]);
    }
}
