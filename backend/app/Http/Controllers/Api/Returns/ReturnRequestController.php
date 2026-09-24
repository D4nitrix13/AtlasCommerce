<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Returns;

use App\Exceptions\Returns\InvalidReturnTransitionException;
use App\Http\Concerns\CursorPaginates;
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
 * Controlador API para gestionar solicitudes de devolución (RMA).
 *
 * Endpoints:
 *   GET    /api/v1/returns                  - Listar paginada (cursor)
 *   GET    /api/v1/returns/{id}             - Detalle con refunds + items
 *   POST   /api/v1/returns                  - Crear nueva solicitud
 *   POST   /api/v1/returns/{id}/approve     - Aprobar (transicion de estado)
 *   POST   /api/v1/returns/{id}/reject      - Rechazar
 *   POST   /api/v1/returns/{id}/cancel      - Cancelar
 *
 * Validacion de inputs:
 *   - store() hace validacion INLINE con `Validator::make` (no FormRequest).
 *     Flag-worthy — considerar extraer a `StoreReturnRequest extends FormRequest`.
 *   - Las transiciones (`approve`, `reject`, `cancel`) capturan
 *     `InvalidReturnTransitionException` y devuelven 422 con el mensaje
 *     del service.
 *
 * **Multi-tenant**: las rutas estan bajo middleware `store.domain`, por
 * lo que el tenant siempre esta resuelto en `$request->attributes->get('store')`.
 */
class ReturnRequestController extends Controller
{
    use CursorPaginates;
    private const DEFAULT_PAGE_SIZE = 25;

    public function __construct(
        private readonly ReturnRequestService $service,
    ) {}

    /**
     * Lista paginada con cursor (25 por pagina default). Eager-loads
     * relations esperadas en el resource: order, customer, items.product,
     * refunds.
     */
    public function index(): JsonResponse
    {
        $requests = ReturnRequest::query()
            ->with(['order', 'customer', 'items.product', 'refunds'])
            ->orderByDesc('id')
            ->paginate(perPage: self::DEFAULT_PAGE_SIZE);

        return ReturnRequestResource::collection(resource: $requests)
            ->response();
    }

    /**
     * Detalle de un return request con todas las relations eagerly loaded.
     * Route model binding: Laravel resuelve el `{id}` automaticamente.
     */
    public function show(ReturnRequest $returnRequest): JsonResponse
    {
        return (new ReturnRequestResource(
            $returnRequest->load(['order', 'customer', 'items.product', 'refunds']),
        ))->response();
    }

    /**
     * Crea una nueva solicitud de devolución.
     *
     * Body esperado:
     *   - order_id (int, required): order al que pertenecen los items.
     *   - reason (string, max 2000): motivo textual para auditoría.
     *   - customer_id (int, opcional): si difiere del customer del order.
     *   - items (array, min 1): cada uno con product_id, product_variant_id (opcional), quantity.
     *
     * Status codes:
     *   - 201 Created: solicitud creada OK.
     *   - 422 Unprocessable Entity: validacion fallo o items no pertenecen al order.
     */
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

    /**
     * Aprueba una solicitud pendiente. Dispara creacion del Refund si
     * la politica de la tienda lo indica (lo decide el service).
     *
     * Status codes:
     *   - 200 OK: aprobada.
     *   - 422 Unprocessable Entity: transicion invalida (estado actual
     *     no permite aprobar).
     */
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

    /**
     * Rechaza una solicitud pendiente (no aplica para estados terminales).
     *
     * Status codes:
     *   - 200 OK: rechazada.
     *   - 422 Unprocessable Entity: transicion invalida.
     */
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

    /**
     * Cancela una solicitud en cualquier estado reversible (no completada).
     *
     * Status codes:
     *   - 200 OK: cancelada.
     *   - 422 Unprocessable Entity: ya completada (no reversible).
     */
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
