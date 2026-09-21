<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// RF-OBS-131 — Fase 13 Observabilidad PR2: health endpoints operacionales.
// Registrados FUERA de cualquier middleware group (web/api) para que
// no pasen por session/CSRF/SecurityHeaders — son endpoints de
// infraestructura, no de negocio. El CorrelationIdMiddleware SÍ se
// ejecuta porque está prependido al stack global.
Route::get('/health/live', [HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');
Route::get('/health', [HealthController::class, 'report'])->name('health.report');
