<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shipping;

use App\Exceptions\Shipping\InvalidShipmentTransitionException;
use App\Http\Concerns\CursorPaginates;
use App\Http\Controllers\Controller;
use App\Http\Resources\Shipping\ShipmentResource;
use App\Models\Shipping\Shipment;
use App\Services\Shipping\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para gestionar envíos.
 *
 * CRUD básico + transiciones de estado (ship, deliver, cancel) que
 * delegan la validación a `ShipmentStatus` y al `ShipmentService`.
 */
class ShipmentController extends Controller
{
    use CursorPaginates;

    private const DEFAULT_PAGE_SIZE = 25;

    public function __construct(
        private readonly ShipmentService $shipments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Shipment::query()
            ->with(['shippingCompany', 'shippingZone', 'currency'])
            ->orderByDesc('id');

        return $this->cursorPaginatedResponse(
            request: $request,
            query: $query,
            resourceClass: ShipmentResource::class,
        );
    }

    public function show(Shipment $shipment): JsonResponse
    {
        return (new ShipmentResource(
            $shipment->load(['order', 'shippingCompany', 'shippingZone', 'currency']),
        ))->response();
    }

    public function markAsShipped(Shipment $shipment): JsonResponse
    {
        try {
            $updated = $this->shipments->markAsShipped(shipment: $shipment);
        } catch (InvalidShipmentTransitionException $e) {
            return response()->json(data: ['message' => $e->getMessage()], status: 422);
        }

        return (new ShipmentResource(
            $updated->load(['shippingCompany', 'shippingZone', 'currency']),
        ))->response();
    }

    public function markAsDelivered(Shipment $shipment): JsonResponse
    {
        try {
            $updated = $this->shipments->markAsDelivered(shipment: $shipment);
        } catch (InvalidShipmentTransitionException $e) {
            return response()->json(data: ['message' => $e->getMessage()], status: 422);
        }

        return (new ShipmentResource(
            $updated->load(['shippingCompany', 'shippingZone', 'currency']),
        ))->response();
    }

    public function cancel(Shipment $shipment): JsonResponse
    {
        try {
            $updated = $this->shipments->cancel(shipment: $shipment);
        } catch (InvalidShipmentTransitionException $e) {
            return response()->json(data: ['message' => $e->getMessage()], status: 422);
        }

        return (new ShipmentResource(
            $updated->load(['shippingCompany', 'shippingZone', 'currency']),
        ))->response();
    }
}
