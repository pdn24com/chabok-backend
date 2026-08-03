<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ServiceCatalog\Infrastructure\Http\ServiceCatalogController;

Route::prefix('api/v1')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::post('/services/resolve', [ServiceCatalogController::class, 'resolve']);
    Route::post('/services/{offeringId}/validate', [ServiceCatalogController::class, 'validateSelection'])->whereUuid('offeringId');
    Route::post('/services/{offeringId}/commitments', [ServiceCatalogController::class, 'commitments'])->whereUuid('offeringId');
    Route::prefix('admin/service-catalog')->group(function (): void {
        Route::get('/audit', [ServiceCatalogController::class, 'audit']);
        Route::get('/{resource}', [ServiceCatalogController::class, 'index'])->whereIn('resource', ['service-types', 'shipping-methods', 'offerings', 'options']);
        Route::post('/{resource}', [ServiceCatalogController::class, 'store'])->whereIn('resource', ['service-types', 'shipping-methods', 'offerings', 'options']);
        Route::post('/{resource}/identities/{identityId}/versions', [ServiceCatalogController::class, 'clone'])->whereUuid('identityId');
        Route::get('/{resource}/identities/{identityId}/versions', [ServiceCatalogController::class, 'history'])->whereUuid('identityId');
        Route::patch('/{resource}/versions/{versionId}', [ServiceCatalogController::class, 'update'])->whereUuid('versionId');
        Route::post('/{resource}/versions/{versionId}/validate', [ServiceCatalogController::class, 'validateVersion'])->whereUuid('versionId');
        Route::post('/{resource}/versions/{versionId}/{action}', [ServiceCatalogController::class, 'transition'])->whereUuid('versionId')->whereIn('action', ['approve', 'publish', 'supersede', 'archive']);
    });
});
