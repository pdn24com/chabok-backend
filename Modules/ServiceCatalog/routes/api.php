<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ServiceCatalog\Infrastructure\Http\ServiceCatalogController;

Route::prefix('api/v1')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::post('/services/resolve', [ServiceCatalogController::class, 'resolve']);
    Route::post('/services/{offeringId}/validate', [ServiceCatalogController::class, 'validateSelection'])->whereUuid('offeringId');
    Route::post('/services/{offeringId}/commitments', [ServiceCatalogController::class, 'commitments'])->whereUuid('offeringId');
    Route::get('/services/pickup-windows', [ServiceCatalogController::class, 'pickupWindows']);
    Route::prefix('admin/service-catalog')->group(function (): void {
        Route::prefix('records/{resource}')->whereIn('resource', ['service-types', 'shipping-methods', 'offerings', 'options', 'commitment-schedules'])->group(function (): void {
            Route::post('/', [ServiceCatalogController::class, 'saveRecord']);
            Route::get('/{identityId}', [ServiceCatalogController::class, 'recordDetail'])->whereUuid('identityId');
            Route::put('/{identityId}', [ServiceCatalogController::class, 'saveRecord'])->whereUuid('identityId');
            Route::patch('/{identityId}/status', [ServiceCatalogController::class, 'recordStatus'])->whereUuid('identityId');
        });
        Route::get('/commitment-zone-groups', [ServiceCatalogController::class, 'commitmentZoneGroups']);
        Route::get('/audit', [ServiceCatalogController::class, 'audit']);
        Route::get('/commitment-schedules/published-versions', [ServiceCatalogController::class, 'publishedSchedules']);
        Route::get('/commitment-schedules', [ServiceCatalogController::class, 'schedules']);
        Route::post('/commitment-schedules', [ServiceCatalogController::class, 'createSchedule']);
        Route::post('/commitment-schedules/identities/{identityId}/versions', [ServiceCatalogController::class, 'cloneSchedule'])->whereUuid('identityId');
        Route::get('/commitment-schedules/identities/{identityId}/versions', [ServiceCatalogController::class, 'scheduleHistory'])->whereUuid('identityId');
        Route::patch('/commitment-schedules/versions/{versionId}', [ServiceCatalogController::class, 'updateSchedule'])->whereUuid('versionId');
        Route::post('/commitment-schedules/versions/{versionId}/validate', [ServiceCatalogController::class, 'validateSchedule'])->whereUuid('versionId');
        Route::post('/commitment-schedules/versions/{versionId}/{action}', [ServiceCatalogController::class, 'transitionSchedule'])->whereUuid('versionId')->whereIn('action', ['approve', 'publish', 'supersede', 'archive']);
        Route::get('/{resource}/published-versions', [ServiceCatalogController::class, 'publishedVersions'])->whereIn('resource', ['service-types', 'shipping-methods', 'offerings', 'options']);
        Route::get('/{resource}', [ServiceCatalogController::class, 'index'])->whereIn('resource', ['service-types', 'shipping-methods', 'offerings', 'options']);
        Route::post('/{resource}', [ServiceCatalogController::class, 'store'])->whereIn('resource', ['service-types', 'shipping-methods', 'offerings', 'options']);
        Route::post('/{resource}/identities/{identityId}/versions', [ServiceCatalogController::class, 'clone'])->whereUuid('identityId');
        Route::get('/{resource}/identities/{identityId}/versions', [ServiceCatalogController::class, 'history'])->whereUuid('identityId');
        Route::patch('/{resource}/versions/{versionId}', [ServiceCatalogController::class, 'update'])->whereUuid('versionId');
        Route::post('/{resource}/versions/{versionId}/validate', [ServiceCatalogController::class, 'validateVersion'])->whereUuid('versionId');
        Route::post('/{resource}/versions/{versionId}/{action}', [ServiceCatalogController::class, 'transition'])->whereUuid('versionId')->whereIn('action', ['approve', 'publish', 'supersede', 'archive']);
    });
});
