<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Support;

use App\Http\Concerns\CursorPaginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\SupportTicketReplyRequest;
use App\Http\Requests\Support\SupportTicketStoreRequest;
use App\Http\Resources\Support\AdminSupportTicketResource;
use App\Models\Customers\Customer;
use App\Models\Support\SupportTicket;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoints del cliente autenticado para gestionar SUS tickets (RF-SUP-160).
 *
 * Rutas:
 *   - GET    /api/support/tickets              lista los propios
 *   - POST   /api/support/tickets              crea ticket con primer mensaje
 *   - GET    /api/support/tickets/{ticket}    detalle (sólo propios)
 *   - POST   /api/support/tickets/{ticket}/reply   agrega mensaje
 *
 * Reglas:
 *  - El cliente sólo opera sobre SUS tickets. Cross-customer → 404.
 *  - El status lo gestiona el admin vía /admin/support/tickets.
 */
class SupportTicketController extends Controller
{
    use CursorPaginates;

    public function __construct(
        private readonly SupportTicketService $service,
    ) {}

    /**
     * GET /api/support/tickets
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $paginator = SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at');

        return $this->cursorPaginatedResponse(
            request: $request, query: $paginator, resourceClass: AdminSupportTicketResource::class,
        );
    }

    /**
     * POST /api/support/tickets
     */
    public function store(SupportTicketStoreRequest $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $result = $this->service->create(
            subject: $request->string('subject')->toString(),
            description: $request->string('description')->toString(),
            customer: $customer,
            priority: $request->input('priority'),
        );

        $ticket = $result['ticket'];
        $ticket->load('messages');

        return response()->json(data: [
            'data' => (new AdminSupportTicketResource(resource: $ticket))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * GET /api/support/tickets/{ticket}
     */
    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $this->assertOwnedBy(customer: $customer, ticket: $ticket);

        $ticket->load('messages');

        return response()->json(data: [
            'data' => (new AdminSupportTicketResource(resource: $ticket))->resolve(),
        ]);
    }

    /**
     * POST /api/support/tickets/{ticket}/reply
     */
    public function reply(
        SupportTicketReplyRequest $request,
        SupportTicket $ticket,
    ): JsonResponse {
        $customer = $this->resolveCustomer($request);

        $this->assertOwnedBy(customer: $customer, ticket: $ticket);

        try {
            $message = $this->service->reply(
                ticket: $ticket,
                message: $request->string('message')->toString(),
                author: null, // Cliente → sin user_id (registro anónimo).
            );
        } catch (RuntimeException $e) {
            return response()->json(
                data: ['message' => $e->getMessage()],
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $ticket->refresh();
        $ticket->load('messages');

        return response()->json(data: [
            'data' => (new AdminSupportTicketResource(resource: $ticket))->resolve(),
            'message' => [
                'id' => $message->id,
                'message' => $message->message,
                'messaged_at' => $message->messaged_at?->toIso8601String(),
            ],
        ], status: Response::HTTP_CREATED);
    }

    /**
     * Helper: resuelve el Customer asociado al User autenticado.
     */
    private function resolveCustomer(Request $request): Customer
    {
        $user = $request->user();
        abort_if($user === null, 401, 'Autenticación requerida.');

        $customer = Customer::query()->where('user_id', $user->id)->first();
        abort_if($customer === null, 404, 'No existe perfil de cliente para este usuario.');

        return $customer;
    }

    /**
     * Helper: aborta si el ticket no pertenece al cliente autenticado.
     */
    private function assertOwnedBy(Customer $customer, SupportTicket $ticket): void
    {
        abort_if(
            $ticket->customer_id !== $customer->id,
            404,
            'Ticket no encontrado.',
        );
    }
}
