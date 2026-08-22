<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Operations\Infrastructure\Http\OperationalDirectoryController;

Route::prefix('api/v1')->middleware(['api', 'access.auth', 'node.access', 'password.changed'])->group(function (): void {
    Route::get('/drivers', [OperationalDirectoryController::class, 'drivers'])->name('operations.drivers.index');
    Route::get('/vehicles', [OperationalDirectoryController::class, 'vehicles'])->name('operations.vehicles.index');
    Route::get('/route-plans', [OperationalDirectoryController::class, 'routes'])->name('operations.routes.index');
});
