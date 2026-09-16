<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// RF-STOR-132: purga de carritos abandonados.
// En local corre cada hora para facilitar testing manual; en prod corre
// diario a las 03:00 (ventana de baja carga).
Schedule::command('carts:purge-abandoned --days=30')
    ->hourly()
    ->environments('local');

Schedule::command('carts:purge-abandoned --days=30')
    ->dailyAt('03:00')
    ->environments('production');
