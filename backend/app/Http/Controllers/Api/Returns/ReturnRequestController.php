<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Returns;

use App\Exceptions\Returns\InvalidReturnTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Returns\ReturnRequestResource;
use App\Models\Returns\ReturnRequest;
use App\Models\Sales\Order;
use App\Services\Returns\ReturnRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Controlador API para gestionar solicitudes de devolución.
 *
 * CRUD básico + transiciones de estado (approve, reject, complete, cancel).
 * La creación valida pertenencia de items al order (delegado al service).
 */
class ReturnRequestController extends Controller
{
    private const DEFAULT_PAGE_SIZE = 25;

    public function __construct(
        private readonly ReturnRequestService $service,
    ) {}

    public function index(): JsonResponse
    {
        $requests = ReturnRequest::query()
            ->with(['order', 'customer', 'items.product', 'refunds'])
            ->orderByDesc('id')
            ->paginate(perPage: self::DEFAULT_PAGE_SIZE);

        return ReturnRequestResource::collection(resource: $requests)
            ->response();
    }

    public function show(ReturnRequest $returnRequest): JsonResponse
    {
        return (new ReturnRequestResource(
            $returnRequest->load(['order', 'customer', 'items.product', 'refunds']),
        ))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make(data: $request->all(), rules: [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'reason' => ['required', 'string', 'max:2000'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(data: ['errors' => $validator->errors()], status: 422);
        }

        $order = Order::query()->findOrFail(id: $request->input('order_id'));

        try {
            $returnRequest = $this->service->create(
                order: $order,
                reason: (string) $request->input('reason'),
                items: $request->input('items'),
                customerId: $request->input('customer_id'),
            );
        } catch (RuntimeException $e) {
            return response()->json(data: ['message' => $e->getMessage()], status: 422);
        }

        return (new ReturnRequestResource(
            $returnRequest->load(['items.product', 'customer', 'order']),
        ))->response()->setStatusCode(201);
    }

    public function approve(ReturnRequest $returnRequest): JsonResponse
    {
        try {
            $updated = $this->service->approve(returnRequest: $returnRequest);
        } catch (InvalidReturnTransitionException $e) {
            return response()->json(data: ['message' => $e->getMessage()], status: 422);
        }

        return (new ReturnRequestResource(
            $updated->load(['items.product', 'refunds', 'customer', 'order']),
        ))->response();
    }

    public function reject(ReturnRequest $returnRequest): JsonResponse
    {
        try {
            $updated = $this->service->reject(returnRequest: $returnRequest);
        } catch (InvalidReturnTransitionException $e) {
            return response()->json(data: ['message' => $e->getMessage()], status: 422);
        }

        return (new ReturnRequestResource(
            $updated->load(['items.product', 'refunds', 'customer', 'order']),
        ))->response();
    }

    public function cancel(ReturnRequest $returnRequest): JsonResponse
    {
        try {
            $updated = $this->service->cancel(returnRequest: $returnRequest);
        } catch (InvalidReturnTransitionException $e) {
            return response()->json(data: ['message' => $e->getMessage()], status: 422);
        }

        return (new ReturnRequestResource(
            $updated->load(['items.product', 'refunds', 'customer', 'order']),
        ))->response();
    }
}
