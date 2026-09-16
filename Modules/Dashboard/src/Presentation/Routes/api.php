<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Presentation\Http\Controllers\OperationsDashboardController;
Route::prefix('api/v1/dashboard')->middleware(['api', 'access.auth', 'node.access', 'password.changed'])->group(function (): void {
    Route::get('/operations', OperationsDashboardController::class)->name('dashboard.operations.show');
});
