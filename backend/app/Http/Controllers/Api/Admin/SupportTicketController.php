<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\CursorPaginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\Admin\SupportTicketUpdateRequest;
use App\Http\Requests\Support\SupportTicketReplyRequest;
use App\Http\Resources\Support\AdminSupportTicketResource;
use App\Models\Auth\User as UserModel;
use App\Models\Support\SupportTicket;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de tickets de soporte (RF-SUP-160).
 *
 * Reglas:
 *  - Aislamiento por tenant: admin sólo ve tickets de tiendas accesibles.
 *  - Status se gestiona via PUT (status/priority).
 *  - Reply crea un nuevo mensaje asociado al ticket.
 *  - Close marca el ticket como `closed` (terminal).
 *
 * Permiso: `support.manage` (admin slug bypasea).
 */
class SupportTicketController extends Controller
{
    use CursorPaginates;
    /**
     * GET /admin/support/tickets
     */
    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::query()
            ->whereIn('store_id', $this->accessibleStoreIds())
            ->with(['messages']);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->query('priority'));
        }

        $paginator = $query->orderByDesc('created_at')
            ->paginate(perPage: (int) $request->query('per_page', 20));

        return $this->cursorPaginatedResponse(
            request: $request, query: $query, resourceClass: AdminSupportTicketResource::class,
        );
    }

    /**
     * GET /admin/support/tickets/{ticket}
     */
    public function show(SupportTicket $ticket): JsonResponse
    {
        $this->assertTicketInTenantScope(ticket: $ticket);

        $ticket->load(['messages', 'customer']);

        return response()->json(data: [
            'data' => (new AdminSupportTicketResource(resource: $ticket))->resolve(),
        ]);
    }

    /**
     * PUT /admin/support/tickets/{ticket}
     */
    public function update(SupportTicketUpdateRequest $request, SupportTicket $ticket): JsonResponse
    {
        $this->assertTicketInTenantScope(ticket: $ticket);

        $ticket->update(attributes: $request->validated());

        return response()->json(data: [
            'data' => (new AdminSupportTicketResource(resource: $ticket->fresh(['messages'])))->resolve(),
        ]);
    }

    /**
     * POST /admin/support/tickets/{ticket}/reply
     */
    public function reply(
        SupportTicketReplyRequest $request,
        SupportTicket $ticket,
    ): JsonResponse {
        $this->assertTicketInTenantScope(ticket: $ticket);

        $user = $request->user();
        if (! $user instanceof UserModel) {
            abort(code: 401);
        }

        try {
            $message = app(SupportTicketService::class)->reply(
                ticket: $ticket,
                message: $request->string('message')->toString(),
                author: $user,
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
     * POST /admin/support/tickets/{ticket}/close
     */
    public function close(SupportTicket $ticket): JsonResponse
    {
        $this->assertTicketInTenantScope(ticket: $ticket);

        $closed = app(SupportTicketService::class)->close(ticket: $ticket);
        $closed->load('messages');

        return response()->json(data: [
            'data' => (new AdminSupportTicketResource(resource: $closed))->resolve(),
        ]);
    }

    /**
     * Helper: IDs de tiendas accesibles por el admin actual.
     *
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

    /**
     * Helper: aborta si el ticket pertenece a una tienda no accesible.
     */
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
