<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Models\Auth\User;
use App\Models\Customers\Customer;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Servicio de tickets de soporte (RF-SUP-160).
 *
 * Responsabilidades:
 *  - Crear un ticket con su primer mensaje en una sola TX.
 *  - Agregar respuestas al ticket (manteniendo trazabilidad de autor).
 *  - Cerrar tickets (transición a `closed`).
 *
 * Reglas:
 *  - Un ticket cerrado NO acepta más respuestas.
 *  - El primer mensaje se crea junto con el ticket (atómico).
 *  - El `user_id` se setea para staff; es null para mensajes del sistema.
 */
final class SupportTicketService
{
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    /**
     * Crea un ticket con su primer mensaje.
     *
     * Para clientes, `$user` es el User autenticado (el customer_id se
     * resuelve desde $user). Para staff/admin, se puede pasar un
     * customer_id explícito.
     *
     * @return array{ticket: SupportTicket, message: SupportTicketMessage}
     */
    public function create(
        string $subject,
        string $description,
        Customer $customer,
        ?string $priority = self::PRIORITY_NORMAL,
        ?User $staffUser = null,
    ): array {
        return DB::transaction(function () use ($subject, $description, $customer, $priority, $staffUser): array {
            $ticket = SupportTicket::query()->create([
                'customer_id' => $customer->id,
                'store_id' => $customer->stores()->first()?->id,
                'subject' => $subject,
                'description' => $description,
                'status' => self::STATUS_OPEN,
                'priority' => $priority ?? self::PRIORITY_NORMAL,
            ]);

            $message = SupportTicketMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $staffUser?->id,
                'message' => $description,
                'messaged_at' => now(),
            ]);

            return ['ticket' => $ticket, 'message' => $message];
        });
    }

    /**
     * Agrega una respuesta al ticket.
     *
     * @throws RuntimeException Si el ticket está cerrado.
     */
    public function reply(SupportTicket $ticket, string $message, ?User $author = null): SupportTicketMessage
    {
        if ($ticket->status === self::STATUS_CLOSED) {
            throw new RuntimeException(
                "El ticket #{$ticket->id} está cerrado y no acepta más respuestas.",
            );
        }

        return DB::transaction(function () use ($ticket, $message, $author): SupportTicketMessage {
            // Auto-transición a in_progress si todavía está open.
            if ($ticket->status === self::STATUS_OPEN) {
                $ticket->update(['status' => self::STATUS_IN_PROGRESS]);
            }

            return SupportTicketMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $author?->id,
                'message' => $message,
                'messaged_at' => now(),
            ]);
        });
    }

    /**
     * Cierra un ticket (terminal — no se puede reabrir vía MVP).
     */
    public function close(SupportTicket $ticket): SupportTicket
    {
        $ticket->update(['status' => self::STATUS_CLOSED]);

        return $ticket->fresh();
    }
}
