<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Operations\Infrastructure\Http\DeliveryTaskController;
use Modules\Operations\Infrastructure\Http\TransportRunController;

Route::prefix('api/v1')->middleware(['api', 'access.auth', 'node.access', 'password.changed'])->group(function (): void {
    Route::get('/transport-runs/candidates', [TransportRunController::class, 'candidates'])->name('transport-runs.candidates');
    Route::get('/transport-runs', [TransportRunController::class, 'index'])->name('transport-runs.index');
    Route::post('/transport-runs', [TransportRunController::class, 'store'])->middleware('idempotent:transport-runs.create')->name('transport-runs.store');
    Route::get('/transport-runs/{id}', [TransportRunController::class, 'show'])->whereUuid('id')->name('transport-runs.show');
    Route::post('/transport-runs/{id}/load', [TransportRunController::class, 'load'])->middleware('idempotent:transport-runs.load')->whereUuid('id')->name('transport-runs.load');
    Route::post('/transport-runs/{id}/depart', [TransportRunController::class, 'depart'])->middleware('idempotent:transport-runs.depart')->whereUuid('id')->name('transport-runs.depart');
    Route::post('/transport-runs/{id}/arrive', [TransportRunController::class, 'arrive'])->middleware('idempotent:transport-runs.arrive')->whereUuid('id')->name('transport-runs.arrive');
    Route::post('/transport-runs/{id}/close', [TransportRunController::class, 'close'])->middleware('idempotent:transport-runs.close')->whereUuid('id')->name('transport-runs.close');

    Route::get('/delivery-tasks', [DeliveryTaskController::class, 'index'])->name('delivery-tasks.index');
    Route::get('/delivery-tasks/{id}', [DeliveryTaskController::class, 'show'])->whereUuid('id')->name('delivery-tasks.show');
    Route::post('/delivery-tasks/{id}/assign', [DeliveryTaskController::class, 'assign'])->middleware('idempotent:delivery-tasks.assign')->whereUuid('id')->name('delivery-tasks.assign');
    Route::post('/delivery-tasks/{id}/complete', [DeliveryTaskController::class, 'complete'])->middleware('idempotent:delivery-tasks.complete')->whereUuid('id')->name('delivery-tasks.complete');
    Route::post('/delivery-tasks/{id}/fail', [DeliveryTaskController::class, 'fail'])->middleware('idempotent:delivery-tasks.fail')->whereUuid('id')->name('delivery-tasks.fail');
    Route::post('/delivery-tasks/{id}/retry', [DeliveryTaskController::class, 'retry'])->middleware('idempotent:delivery-tasks.retry')->whereUuid('id')->name('delivery-tasks.retry');
});
