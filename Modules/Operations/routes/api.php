<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Operations\Infrastructure\Http\OperationalDirectoryController;
use Modules\Operations\Infrastructure\Http\PickupTaskController;
use Modules\Operations\Infrastructure\Http\DeliveryTaskController;
use Modules\Operations\Infrastructure\Http\MovementController;

Route::prefix('api/v1')->middleware(['api', 'access.auth', 'node.access', 'password.changed'])->group(function (): void {
    Route::get('/drivers', [OperationalDirectoryController::class, 'drivers'])->name('operations.drivers.index');
    Route::get('/vehicles', [OperationalDirectoryController::class, 'vehicles'])->name('operations.vehicles.index');
    Route::get('/route-plans', [OperationalDirectoryController::class, 'routes'])->name('operations.routes.index');
    Route::get('/pickup-tasks', [PickupTaskController::class, 'index'])->name('pickup-tasks.index');
    Route::post('/pickup-tasks', [PickupTaskController::class, 'store'])->middleware('idempotent:pickup-tasks.create')->name('pickup-tasks.store');
    Route::get('/pickup-tasks/{id}', [PickupTaskController::class, 'show'])->whereUuid('id')->name('pickup-tasks.show');
    Route::post('/pickup-tasks/{id}/assign', [PickupTaskController::class, 'assign'])->middleware('idempotent:pickup-tasks.assign')->whereUuid('id')->name('pickup-tasks.assign');
    Route::post('/pickup-tasks/{id}/complete', [PickupTaskController::class, 'complete'])->middleware('idempotent:pickup-tasks.complete')->whereUuid('id')->name('pickup-tasks.complete');
    Route::post('/pickup-tasks/{id}/fail', [PickupTaskController::class, 'fail'])->middleware('idempotent:pickup-tasks.fail')->whereUuid('id')->name('pickup-tasks.fail');
    Route::get('/delivery-tasks', [DeliveryTaskController::class, 'index'])->name('delivery-tasks.index');
    Route::get('/delivery-tasks/{id}', [DeliveryTaskController::class, 'show'])->whereUuid('id')->name('delivery-tasks.show');
    Route::post('/delivery-tasks/{id}/complete', [DeliveryTaskController::class, 'complete'])->middleware('idempotent:delivery-tasks.complete')->whereUuid('id')->name('delivery-tasks.complete');
    Route::post('/delivery-tasks/{id}/fail', [DeliveryTaskController::class, 'fail'])->middleware('idempotent:delivery-tasks.fail')->whereUuid('id')->name('delivery-tasks.fail');
    Route::post('/consignments/{consignmentId}/route-plan', [MovementController::class, 'plan'])->middleware('idempotent:route-plans.create')->whereUuid('consignmentId')->name('route-plans.store');
    Route::get('/route-plans/{id}', [MovementController::class, 'showPlan'])->whereUuid('id')->name('route-plans.show');
    Route::post('/consignments/{consignmentId}/cluster', [MovementController::class, 'cluster'])->middleware('idempotent:route-plans.cluster')->whereUuid('consignmentId')->name('route-plans.cluster');
    Route::post('/transport-runs', [MovementController::class, 'createRun'])->middleware('idempotent:transport-runs.create')->name('transport-runs.store');
    Route::get('/transport-runs/{id}', [MovementController::class, 'showRun'])->whereUuid('id')->name('transport-runs.show');
    Route::post('/transport-runs/{id}/load', [MovementController::class, 'load'])->middleware('idempotent:transport-runs.load')->whereUuid('id')->name('transport-runs.load');
    Route::post('/transport-runs/{id}/depart', [MovementController::class, 'depart'])->middleware('idempotent:transport-runs.depart')->whereUuid('id')->name('transport-runs.depart');
    Route::post('/transport-runs/{id}/arrive', [MovementController::class, 'arrive'])->middleware('idempotent:transport-runs.arrive')->whereUuid('id')->name('transport-runs.arrive');
    Route::post('/transport-runs/{id}/close', [MovementController::class, 'close'])->middleware('idempotent:transport-runs.close')->whereUuid('id')->name('transport-runs.close');
});
