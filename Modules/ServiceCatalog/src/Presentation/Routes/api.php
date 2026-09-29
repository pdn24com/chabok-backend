<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\ServiceCatalog\Presentation\Http\Controllers\ServiceCatalogController;

Route::prefix('api/v1')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::post('/services/resolve', [ServiceCatalogController::class, 'resolve']);
    Route::post('/services/{offeringId}/validate', [ServiceCatalogController::class, 'validateSelection'])->whereNumber('offeringId');
    Route::post('/services/{offeringId}/commitments', [ServiceCatalogController::class, 'commitments'])->whereNumber('offeringId');
    Route::get('/services/pickup-windows', [ServiceCatalogController::class, 'pickupWindows']);
    Route::prefix('admin/service-catalog')->group(function (): void {
        Route::prefix('records/{resource}')->whereIn('resource', ['service-types', 'shipping-methods', 'offerings', 'options', 'commitment-schedules'])->group(function (): void {
            Route::post('/', [ServiceCatalogController::class, 'saveRecord']);
            Route::get('/{identityId}', [ServiceCatalogController::class, 'recordDetail'])->whereNumber('identityId');
            Route::put('/{identityId}', [ServiceCatalogController::class, 'saveRecord'])->whereNumber('identityId');
            Route::patch('/{identityId}/status', [ServiceCatalogController::class, 'recordStatus'])->whereNumber('identityId');
        });
        Route::get('/commitment-zone-groups', [ServiceCatalogController::class, 'commitmentZoneGroups']);
        Route::get('/audit', [ServiceCatalogController::class, 'audit']);
        Route::get('/commitment-schedules/published-versions', [ServiceCatalogController::class, 'publishedSchedules']);
        Route::get('/commitment-schedules', [ServiceCatalogController::class, 'schedules']);
        Route::post('/commitment-schedules', [ServiceCatalogController::class, 'createSchedule']);
        Route::post('/commitment-schedules/identities/{identityId}/versions', [ServiceCatalogController::class, 'cloneSchedule'])->whereNumber('identityId');
        Route::get('/commitment-schedules/identities/{identityId}/versions', [ServiceCatalogController::class, 'scheduleHistory'])->whereNumber('identityId');
        Route::patch('/commitment-schedules/versions/{versionId}', [ServiceCatalogController::class, 'updateSchedule'])->whereNumber('versionId');
        Route::post('/commitment-schedules/versions/{versionId}/validate', [ServiceCatalogController::class, 'validateSchedule'])->whereNumber('versionId');
        Route::post('/commitment-schedules/versions/{versionId}/{action}', [ServiceCatalogController::class, 'transitionSchedule'])->whereNumber('versionId')->whereIn('action', ['approve', 'publish', 'supersede', 'archive']);
        Route::get('/{resource}/published-versions', [ServiceCatalogController::class, 'publishedVersions'])->whereIn('resource', ['service-types', 'shipping-methods', 'offerings', 'options']);
        Route::get('/{resource}', [ServiceCatalogController::class, 'index'])->whereIn('resource', ['service-types', 'shipping-methods', 'offerings', 'options']);
        Route::post('/{resource}', [ServiceCatalogController::class, 'store'])->whereIn('resource', ['service-types', 'shipping-methods', 'offerings', 'options']);
        Route::post('/{resource}/identities/{identityId}/versions', [ServiceCatalogController::class, 'clone'])->whereNumber('identityId');
        Route::get('/{resource}/identities/{identityId}/versions', [ServiceCatalogController::class, 'history'])->whereNumber('identityId');
        Route::patch('/{resource}/versions/{versionId}', [ServiceCatalogController::class, 'update'])->whereNumber('versionId');
        Route::post('/{resource}/versions/{versionId}/validate', [ServiceCatalogController::class, 'validateVersion'])->whereNumber('versionId');
        Route::post('/{resource}/versions/{versionId}/{action}', [ServiceCatalogController::class, 'transition'])->whereNumber('versionId')->whereIn('action', ['approve', 'publish', 'supersede', 'archive']);
    });
});
