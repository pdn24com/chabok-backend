<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\Operations\Presentation\Http\Controllers\NetworkConfigurationController;

Route::prefix('api/v1/network')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::get('/coverage-policies', [NetworkConfigurationController::class, 'coverageIndex']);
    Route::post('/coverage-policies', [NetworkConfigurationController::class, 'coverageStore'])->middleware('idempotent:network.coverage.create');
    Route::get('/coverage-policies/{policyId}', [NetworkConfigurationController::class, 'coverageShow'])->whereNumber('policyId');
    Route::get('/coverage-policies/{policyId}/versions', [NetworkConfigurationController::class, 'coverageVersionIndex'])->whereNumber('policyId');
    Route::post('/coverage-policies/{policyId}/versions', [NetworkConfigurationController::class, 'coverageVersionStore'])->middleware('idempotent:network.coverage-version.create')->whereNumber('policyId');
    Route::get('/coverage-policies/{policyId}/versions/{versionId}', [NetworkConfigurationController::class, 'coverageVersionShow'])->whereNumber(['policyId', 'versionId']);
    Route::patch('/coverage-policies/{policyId}/versions/{versionId}', [NetworkConfigurationController::class, 'coverageVersionUpdate'])->whereNumber(['policyId', 'versionId']);
    Route::post('/coverage-policies/{policyId}/versions/{versionId}/{action}', [NetworkConfigurationController::class, 'coverageTransition'])
        ->middleware('idempotent:network.coverage-version.transition')
        ->whereNumber(['policyId', 'versionId'])
        ->whereIn('action', ['validate', 'approve', 'publish', 'supersede', 'archive']);
    Route::get('/route-definitions', [NetworkConfigurationController::class, 'routeIndex']);
    Route::post('/route-definitions', [NetworkConfigurationController::class, 'routeStore'])->middleware('idempotent:network.route.create');
    Route::get('/route-definitions/{definitionId}', [NetworkConfigurationController::class, 'routeShow'])->whereNumber('definitionId');
    Route::get('/route-definitions/{definitionId}/versions', [NetworkConfigurationController::class, 'routeVersionIndex'])->whereNumber('definitionId');
    Route::post('/route-definitions/{definitionId}/versions', [NetworkConfigurationController::class, 'routeVersionStore'])->middleware('idempotent:network.route-version.create')->whereNumber('definitionId');
    Route::get('/route-definitions/{definitionId}/versions/{versionId}', [NetworkConfigurationController::class, 'routeVersionShow'])->whereNumber(['definitionId', 'versionId']);
    Route::patch('/route-definitions/{definitionId}/versions/{versionId}', [NetworkConfigurationController::class, 'routeVersionUpdate'])->whereNumber(['definitionId', 'versionId']);
    Route::post('/route-definitions/{definitionId}/versions/{versionId}/{action}', [NetworkConfigurationController::class, 'routeTransition'])
        ->middleware('idempotent:network.route-version.transition')
        ->whereNumber(['definitionId', 'versionId'])
        ->whereIn('action', ['validate', 'approve', 'publish', 'supersede', 'archive']);
});
