<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Carts\Cart;
use Illuminate\Console\Command;

/**
 * Purga carritos abandonados (RF-STOR-132).
 *
 * Un carrito se considera abandonado cuando:
 *  - Su estado es `active` (no fue convertido a orden).
 *  - Su `expires_at` ya pasó (o nunca se seteó y fue actualizado hace
 *    más de N días, vía `updated_at`).
 *
 * Por defecto se purgan carritos con `expires_at < now()` o
 * `updated_at < now() - {days}d` cuando `expires_at IS NULL`.
 *
 * Uso:
 *   php artisan carts:purge-abandoned
 *   php artisan carts:purge-abandoned --days=7
 *   php artisan carts:purge-abandoned --dry-run
 */
class PurgeAbandonedCarts extends Command
{
    protected $signature = 'carts:purge-abandoned
        {--days=30 : Días sin actualización para considerar un carrito abandonado}
        {--dry-run : Cuenta los elegibles sin eliminarlos}';

    protected $description = 'Purga carritos activos expirados o sin movimiento reciente.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('El parámetro --days debe ser >= 1.');

            return self::INVALID;
        }

        $cutoff = now()->subDays(value: $days);

        $query = Cart::query()
            ->where('status', 'active')
            ->where(function ($q) use ($cutoff): void {
                $q->where('expires_at', '<', now())
                    ->orWhere(function ($q2) use ($cutoff): void {
                        $q2->whereNull('expires_at')
                            ->where('updated_at', '<', $cutoff);
                    });
            });

        $count = $query->count();

        if ($this->option('dry-run') === true) {
            $this->info(sprintf(
                '[dry-run] %d carritos serían purgados (cutoff: %s).',
                $count,
                $cutoff->toDateTimeString(),
            ));

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info(sprintf(
            'Purgados %d carritos abandonados (cutoff: %s).',
            $deleted,
            $cutoff->toDateTimeString(),
        ));

        return self::SUCCESS;
    }
}
