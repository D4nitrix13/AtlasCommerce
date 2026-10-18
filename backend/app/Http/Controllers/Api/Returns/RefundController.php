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
use RuntimeException;

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

    /**
     * Crea un refund en estado `pending` (sin procesar).
     *
     * Body esperado:
     *   - return_request_id (int, required): return request aprobable
     *   - amount_cents (int, min:1): monto a reembolsar en cents
     *   - payment_method_id (int, opcional): metodo via el cual procesar
     *
     * Status codes:
     *   - 201: refund creado
     *   - 422: validacion fallo (FormRequest-style inline) o monto invalido
     */
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
        } catch (RuntimeException $e) {
            return response()->json(data: ['message' => $e->getMessage()], status: 422);
        }

        return (new RefundResource(
            $refund->load(['currency', 'returnRequest']),
        ))->response()->setStatusCode(201);
    }

    /**
     * Procesa un refund pending → completed.
     *
     * **Operacion IRREVERSIBLE**: dispara efectos secundarios
     * transaccionales:
     *   - Revertir stock de los items del return request asociado
     *   - Impacto en invoice (mark invoice line como refunded)
     *   - Si payment method tiene webhook → notificar al gateway
     *
     * Captura `InvalidRefundTransitionException` (estado invalido) y
     * `RuntimeException` (error del gateway de pago) -> 422 con msg.
     *
     * Status codes:
     *   - 200: refund procesado, invoice + stock revertidos
     *   - 422: transicion invalida (ya completado/failed) o error de pago
     *   - 404: refund no existe (route model binding)
     */
    public function process(Refund $refund): JsonResponse
    {
        try {
            $updated = $this->service->process(refund: $refund);
        } catch (InvalidRefundTransitionException|RuntimeException $e) {
            return response()->json(data: ['message' => $e->getMessage()], status: 422);
        }

        return (new RefundResource(
            $updated->load(['returnRequest', 'paymentMethod', 'currency']),
        ))->response();
    }

    /**
     * Marca un refund pending → failed (sin revertir stock).
     *
     * Diferencia clave con `process()`: NO dispara efectos secundarios
     * en inventario/invoice. Solo cambia el estado a `failed` para
     * auditoría. Usado cuando el gateway de pago rechaza (timeout,
     * invalid credentials, fraud detection).
     *
     * Status codes:
     *   - 200: refund marcado como failed
     *   - 422: transicion invalida (ya completado/failed)
     *   - 404: refund no existe
     */
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
