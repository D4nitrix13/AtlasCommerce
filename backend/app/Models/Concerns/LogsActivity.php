<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\System\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra automaticamente eventos `created`, `updated` y `deleted`
 * del modelo en la tabla `audits` con valores anteriores y nuevos.
 *
 * Aplicar a modelos operativos donde el historial es critico:
 * Order, Invoice, Payment, Refund, Shipment, ReturnRequest.
 *
 * El usuario actual se resuelve con `auth()->id()` (queda null en
 * jobs/console commands).
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function (Model $model) use ($event): void {
                Audit::create([
                    'user_id' => auth()->id(),
                    'store_id' => $model->store_id ?? null,
                    'action' => $event,
                    'table_name' => $model->getTable(),
                    'record_id' => (string) ($model->getKey() ?? ''),
                    'old_values' => $event === 'updated' || $event === 'deleted'
                        ? $model->getOriginal()
                        : null,
                    'new_values' => $event === 'created' || $event === 'updated'
                        ? $model->getAttributes()
                        : null,
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                    'registered_at' => now(),
                ]);
            });
        }
    }
}
