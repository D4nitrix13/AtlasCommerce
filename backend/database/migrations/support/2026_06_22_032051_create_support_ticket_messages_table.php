<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de mensajes de tickets de soporte para
     * registrar la conversación entre cliente y soporte.
     */
    public function up(): void
    {
        Schema::create(table: 'support_ticket_messages', callback: function (Blueprint $table): void {
            $table->id();

            // Ticket al que pertenece el mensaje: cascadeOnDelete porque si se elimina el ticket sus mensajes no tienen sentido
            $table->foreignId(column: 'support_ticket_id')
                ->constrained(table: 'support_tickets')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Usuario que envió el mensaje (puede ser cliente o agente de soporte), nullable si es un mensaje automático del sistema
            // nullOnDelete: si se elimina el usuario, se conserva el mensaje como registro histórico
            $table->foreignId(column: 'user_id')
                ->nullable()
                ->constrained(table: 'users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Contenido del mensaje de la conversación
            $table->text(column: 'message');
            // Fecha y hora en que se envió el mensaje, se establece automáticamente
            $table->timestamp(column: 'messaged_at')->useCurrent();

            $table->timestamps();

            $table->index(columns: ['support_ticket_id'], name: 'support_ticket_messages_support_ticket_id_idx');
            $table->index(columns: ['user_id'], name: 'support_ticket_messages_user_id_idx');
            $table->index(columns: ['messaged_at'], name: 'support_ticket_messages_messaged_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'support_ticket_messages');
    }
};
