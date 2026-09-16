<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\Operations\Presentation\Http\Controllers\NetworkConfigurationController;
Route::prefix('api/v1/network')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::get('/coverage-policies', [NetworkConfigurationController::class, 'coverageIndex']);
    Route::post('/coverage-policies', [NetworkConfigurationController::class, 'coverageStore'])->middleware('idempotent:network.coverage.create');
    Route::get('/coverage-policies/{policyId}', [NetworkConfigurationController::class, 'coverageShow'])->whereUuid('policyId');
    Route::get('/coverage-policies/{policyId}/versions', [NetworkConfigurationController::class, 'coverageVersionIndex'])->whereUuid('policyId');
    Route::post('/coverage-policies/{policyId}/versions', [NetworkConfigurationController::class, 'coverageVersionStore'])->middleware('idempotent:network.coverage-version.create')->whereUuid('policyId');
    Route::get('/coverage-policies/{policyId}/versions/{versionId}', [NetworkConfigurationController::class, 'coverageVersionShow'])->whereUuid(['policyId', 'versionId']);
    Route::patch('/coverage-policies/{policyId}/versions/{versionId}', [NetworkConfigurationController::class, 'coverageVersionUpdate'])->whereUuid(['policyId', 'versionId']);
    Route::post('/coverage-policies/{policyId}/versions/{versionId}/{action}', [NetworkConfigurationController::class, 'coverageTransition'])->middleware('idempotent:network.coverage-version.transition')->whereUuid(['policyId', 'versionId'])->whereIn('action', ['validate', 'approve', 'publish', 'supersede', 'archive']);
    Route::get('/route-definitions', [NetworkConfigurationController::class, 'routeIndex']);
    Route::post('/route-definitions', [NetworkConfigurationController::class, 'routeStore'])->middleware('idempotent:network.route.create');
    Route::get('/route-definitions/{definitionId}', [NetworkConfigurationController::class, 'routeShow'])->whereUuid('definitionId');
    Route::get('/route-definitions/{definitionId}/versions', [NetworkConfigurationController::class, 'routeVersionIndex'])->whereUuid('definitionId');
    Route::post('/route-definitions/{definitionId}/versions', [NetworkConfigurationController::class, 'routeVersionStore'])->middleware('idempotent:network.route-version.create')->whereUuid('definitionId');
    Route::get('/route-definitions/{definitionId}/versions/{versionId}', [NetworkConfigurationController::class, 'routeVersionShow'])->whereUuid(['definitionId', 'versionId']);
    Route::patch('/route-definitions/{definitionId}/versions/{versionId}', [NetworkConfigurationController::class, 'routeVersionUpdate'])->whereUuid(['definitionId', 'versionId']);
    Route::post('/route-definitions/{definitionId}/versions/{versionId}/{action}', [NetworkConfigurationController::class, 'routeTransition'])->middleware('idempotent:network.route-version.transition')->whereUuid(['definitionId', 'versionId'])->whereIn('action', ['validate', 'approve', 'publish', 'supersede', 'archive']);
});
