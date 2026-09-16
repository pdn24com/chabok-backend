<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\Organization\Presentation\Http\Controllers\NetworkAdministrationController;
Route::prefix('api/v1/network')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::get('/areas', [NetworkAdministrationController::class, 'areas'])->name('network.areas.index');
    Route::post('/areas', [NetworkAdministrationController::class, 'createArea'])->middleware('idempotent:network.areas.create')->name('network.areas.store');
    Route::get('/areas/{areaId}', [NetworkAdministrationController::class, 'area'])->whereUuid('areaId')->name('network.areas.show');
    Route::patch('/areas/{areaId}', [NetworkAdministrationController::class, 'updateArea'])->whereUuid('areaId')->name('network.areas.update');
    Route::get('/nodes', [NetworkAdministrationController::class, 'nodes'])->name('network.nodes.index');
    Route::post('/nodes', [NetworkAdministrationController::class, 'createNode'])->middleware('idempotent:network.nodes.create')->name('network.nodes.store');
    Route::get('/nodes/{nodeId}', [NetworkAdministrationController::class, 'node'])->whereUuid('nodeId')->name('network.nodes.show');
    Route::patch('/nodes/{nodeId}', [NetworkAdministrationController::class, 'updateNode'])->whereUuid('nodeId')->name('network.nodes.update');
});
