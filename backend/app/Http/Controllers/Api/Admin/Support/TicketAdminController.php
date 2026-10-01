<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\Admin\Api\TicketReplyRequest;
use App\Http\Requests\Support\Admin\Api\TicketStoreRequest;
use App\Http\Requests\Support\Admin\Api\TicketUpdateRequest;
use App\Http\Resources\Admin\Support\TicketResource;
use App\Models\Auth\User as UserModel;
use App\Models\Customers\Customer;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de tickets bajo `/admin/support/tickets`.
 *
 * Espejo del `Admin\SupportTicketController` legacy pero organizado bajo
 * `Support/`. Registra reply/close como acciones explícitas (sub-rutas).
 */
class TicketAdminController extends Controller
{
    /**
     * GET /admin/support/tickets
     */
    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::query()
            ->with(['messages', 'customer'])
            ->whereIn('store_id', $this->accessibleStoreIds());

        if ($request->has('status')) {
            $query->where(column: 'status', operator: '=', value: (string) $request->query('status'));
        }

        if ($request->has('priority')) {
            $query->where(column: 'priority', operator: '=', value: (string) $request->query('priority'));
        }

        $tickets = $query->orderByDesc('created_at')->limit(200)->get();

        return response()->json(data: [
            'data' => TicketResource::collection(resource: $tickets)->resolve(),
        ]);
    }

    /**
     * GET /admin/support/tickets/{ticket}
     */
    public function show(SupportTicket $ticket): JsonResponse
    {
        $this->assertTicketInTenantScope(ticket: $ticket);

        $ticket->load(['messages', 'customer']);

        return response()->json(data: [
            'data' => (new TicketResource(resource: $ticket))->resolve(),
        ]);
    }

    /**
     * POST /admin/support/tickets
     *
     * Crea ticket manualmente (ej. cuando un admin reporta un problema
     * en nombre de un cliente). El primer mensaje es la `description`.
     */
    public function store(TicketStoreRequest $request): JsonResponse
    {
        $customer = Customer::query()->findOrFail((int) $request->integer('customer_id'));

        $user = $request->user();
        if (! $user instanceof UserModel) {
            abort(code: 401);
        }

        $result = app(SupportTicketService::class)->create(
            subject: (string) $request->input('subject'),
            description: (string) $request->input('description'),
            customer: $customer,
            priority: $request->input('priority'),
            staffUser: $user,
        );

        $result['ticket']->load('messages');

        return response()->json(data: [
            'data' => (new TicketResource(resource: $result['ticket']))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/support/tickets/{ticket}
     */
    public function update(TicketUpdateRequest $request, SupportTicket $ticket): JsonResponse
    {
        $this->assertTicketInTenantScope(ticket: $ticket);

        $ticket->update(attributes: $request->validated());
        $ticket->load('messages');

        return response()->json(data: [
            'data' => (new TicketResource(resource: $ticket))->resolve(),
        ]);
    }

    /**
     * POST /admin/support/tickets/{ticket}/reply
     */
    public function reply(TicketReplyRequest $request, SupportTicket $ticket): JsonResponse
    {
        $this->assertTicketInTenantScope(ticket: $ticket);

        $user = $request->user();
        if (! $user instanceof UserModel) {
            abort(code: 401);
        }

        try {
            $message = app(SupportTicketService::class)->reply(
                ticket: $ticket,
                message: (string) $request->input('message'),
                author: $user,
            );
        } catch (RuntimeException $e) {
            return response()->json(
                data: ['message' => $e->getMessage()],
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $ticket->refresh()->load('messages');

        return response()->json(data: [
            'data' => (new TicketResource(resource: $ticket))->resolve(),
            'message' => [
                'id' => $message->id,
                'message' => $message->message,
                'messaged_at' => $message->messaged_at?->toIso8601String(),
            ],
        ], status: Response::HTTP_CREATED);
    }

    /**
     * POST /admin/support/tickets/{ticket}/close
     */
    public function close(SupportTicket $ticket): JsonResponse
    {
        $this->assertTicketInTenantScope(ticket: $ticket);

        $closed = app(SupportTicketService::class)->close(ticket: $ticket);
        $closed->load('messages');

        return response()->json(data: [
            'data' => (new TicketResource(resource: $closed))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/support/tickets/{ticket} — soft delete.
     */
    public function destroy(SupportTicket $ticket): JsonResponse
    {
        $this->assertTicketInTenantScope(ticket: $ticket);

        // Eliminar mensajes asociados también (cascada suave).
        SupportTicketMessage::query()
            ->where(column: 'support_ticket_id', operator: '=', value: $ticket->id)
            ->delete();

        $ticket->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<int, int>
     */
    private function accessibleStoreIds(): array
    {
        $user = auth()->user();
        if (! $user instanceof UserModel) {
            abort(code: 401);
        }

        return $user->stores()->pluck('stores.id')->all();
    }

    private function assertTicketInTenantScope(SupportTicket $ticket): void
    {
        if ($ticket->store_id === null) {
            return;
        }

        if (! in_array((int) $ticket->store_id, $this->accessibleStoreIds(), strict: true)) {
            abort(code: 404, message: 'Ticket no encontrado en tu tenant.');
        }
    }
}
