<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\Operations\Presentation\Http\Controllers\DeliveryTaskController;
Route::prefix('api/v1')->middleware(['api', 'access.auth', 'node.access', 'password.changed'])->group(function (): void {
    Route::get('/delivery-tasks', [DeliveryTaskController::class, 'index'])->name('delivery-tasks.index');
    Route::get('/delivery-tasks/{id}', [DeliveryTaskController::class, 'show'])->whereUuid('id')->name('delivery-tasks.show');
    Route::post('/delivery-tasks/{id}/assign', [DeliveryTaskController::class, 'assign'])->middleware('idempotent:delivery-tasks.assign')->whereUuid('id')->name('delivery-tasks.assign');
    Route::post('/delivery-tasks/{id}/complete', [DeliveryTaskController::class, 'complete'])->middleware('idempotent:delivery-tasks.complete')->whereUuid('id')->name('delivery-tasks.complete');
    Route::post('/delivery-tasks/{id}/fail', [DeliveryTaskController::class, 'fail'])->middleware('idempotent:delivery-tasks.fail')->whereUuid('id')->name('delivery-tasks.fail');
    Route::post('/delivery-tasks/{id}/retry', [DeliveryTaskController::class, 'retry'])->middleware('idempotent:delivery-tasks.retry')->whereUuid('id')->name('delivery-tasks.retry');
});
