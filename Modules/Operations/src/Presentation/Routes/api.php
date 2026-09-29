<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\Operations\Presentation\Http\Controllers\MovementController;
use Modules\Operations\Presentation\Http\Controllers\OperationalDirectoryController;
use Modules\Operations\Presentation\Http\Controllers\PickupTaskController;

Route::prefix('api/v1')->middleware(['api', 'access.auth', 'node.access', 'password.changed'])->group(function (): void {
    Route::get('/drivers', [OperationalDirectoryController::class, 'drivers'])->name('operations.drivers.index');
    Route::get('/vehicles', [OperationalDirectoryController::class, 'vehicles'])->name('operations.vehicles.index');
    Route::get('/route-definitions', [OperationalDirectoryController::class, 'routes'])->name('operations.route-definitions.index');
    Route::get('/pickup-tasks', [PickupTaskController::class, 'index'])->name('pickup-tasks.index');
    Route::post('/pickup-tasks', [PickupTaskController::class, 'store'])->middleware('idempotent:pickup-tasks.create')->name('pickup-tasks.store');
    Route::get('/pickup-tasks/{id}', [PickupTaskController::class, 'show'])->whereNumber('id')->name('pickup-tasks.show');
    Route::post('/pickup-tasks/{id}/assign', [PickupTaskController::class, 'assign'])
        ->middleware('idempotent:pickup-tasks.assign')
        ->whereNumber('id')
        ->name('pickup-tasks.assign');
    Route::post('/pickup-tasks/{id}/complete', [PickupTaskController::class, 'complete'])
        ->middleware('idempotent:pickup-tasks.complete')
        ->whereNumber('id')
        ->name('pickup-tasks.complete');
    Route::post('/pickup-tasks/{id}/fail', [PickupTaskController::class, 'fail'])
        ->middleware('idempotent:pickup-tasks.fail')
        ->whereNumber('id')
        ->name('pickup-tasks.fail');
    Route::post('/consignments/{consignmentId}/route-plan', [MovementController::class, 'plan'])
        ->middleware('idempotent:route-plans.create')
        ->whereNumber('consignmentId')
        ->name('route-plans.store');
    Route::get('/route-plans/{id}', [MovementController::class, 'showPlan'])->whereNumber('id')->name('route-plans.show');
    Route::post('/consignments/{consignmentId}/cluster', [MovementController::class, 'cluster'])
        ->middleware('idempotent:route-plans.cluster')
        ->whereNumber('consignmentId')
        ->name('route-plans.cluster');
});
