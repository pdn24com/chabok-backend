<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Operations\Infrastructure\Http\MovementController;

Route::prefix('api/v1')->middleware(['api', 'access.auth', 'node.access', 'password.changed'])->group(function (): void {
    Route::get('/route-plans', [MovementController::class, 'plans'])->name('route-plans.index');
});
