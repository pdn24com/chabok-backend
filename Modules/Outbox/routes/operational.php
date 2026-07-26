<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Outbox\Infrastructure\Http\OperationalHealthController;

Route::get('/health/live', [OperationalHealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [OperationalHealthController::class, 'ready'])->name('health.ready');
