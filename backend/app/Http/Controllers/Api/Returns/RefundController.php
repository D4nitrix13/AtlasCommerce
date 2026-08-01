<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Returns;

use App\Exceptions\Returns\InvalidRefundTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Returns\RefundResource;
use App\Models\Payments\PaymentMethod;
use App\Models\Returns\Refund;
use App\Models\Returns\ReturnRequest;
use App\Services\Returns\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Controlador API para gestionar reembolsos.
 *
 * CRUD básico + endpoints de transición (process, fail).
 * `store` solo crea refunds en `pending`. `process` es el que dispara
 * la reversión de stock y el impacto en la invoice.
 */
class RefundController extends Controller
{
    private const DEFAULT_PAGE_SIZE = 25;

    public function __construct(
        private readonly RefundService $service,
    ) {}

    public function index(): JsonResponse
    {
        $refunds = Refund::query()
            ->with(['returnRequest', 'paymentMethod', 'currency'])
            ->orderByDesc('id')
            ->paginate(perPage: self::DEFAULT_PAGE_SIZE);

        return RefundResource::collection(resource: $refunds)
            ->response();
    }

    public function show(Refund $refund): JsonResponse
    {
        return (new RefundResource(
            $refund->load(['returnRequest', 'paymentMethod', 'currency']),
        ))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make(data: $request->all(), rules: [
            'return_request_id' => ['required', 'integer', 'exists:return_requests,id'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(data: ['errors' => $validator->errors()], status: 422);
        }

        $returnRequest = ReturnRequest::query()->findOrFail(id: $request->input('return_request_id'));
        $paymentMethod = $request->filled('payment_method_id')
            ? PaymentMethod::query()->findOrFail(id: $request->input('payment_method_id'))
            : null;

        try {
            $refund = $this->service->create(
                returnRequest: $returnRequest,
                amountCents: (int) $request->input('amount_cents'),
                paymentMethod: $paymentMethod,
            );
        } catch (\RuntimeException $e) {
            return response()->json(data: ['message' => $e->getMessage()], status: 422);
        }

        return (new RefundResource(
            $refund->load(['currency', 'returnRequest']),
        ))->response()->setStatusCode(201);
    }

    public function process(Refund $refund): JsonResponse
    {
        try {
            $updated = $this->service->process(refund: $refund);
        } catch (InvalidRefundTransitionException|\RuntimeException $e) {
            return response()->json(data: ['message' => $e->getMessage()], status: 422);
        }

        return (new RefundResource(
            $updated->load(['returnRequest', 'paymentMethod', 'currency']),
        ))->response();
    }

    public function fail(Refund $refund): JsonResponse
    {
        try {
            $updated = $this->service->markAsFailed(refund: $refund);
        } catch (InvalidRefundTransitionException $e) {
            return response()->json(data: ['message' => $e->getMessage()], status: 422);
        }

        return (new RefundResource(
            $updated->load(['returnRequest', 'paymentMethod', 'currency']),
        ))->response();
    }
}
