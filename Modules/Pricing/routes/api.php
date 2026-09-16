<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Pricing\Infrastructure\Http\PricingController;

Route::prefix('api/v1')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::post('/pricing/quotes', [PricingController::class, 'quote'])->middleware('idempotent:pricing.quotes.create');
    Route::get('/pricing/quotes/{quoteId}', [PricingController::class, 'quoteDetail'])->whereUuid('quoteId');
    Route::post('/pricing/quotes/{quoteId}/reject', [PricingController::class, 'reject'])->whereUuid('quoteId');
    Route::post('/pricing/snapshots/accept', [PricingController::class, 'accept'])->middleware('idempotent:pricing.snapshots.accept');
    Route::prefix('admin/pricing')->group(function (): void {
        Route::post('/tariff-versions/{versionId}/simulate', [PricingController::class, 'simulateDraft'])->whereUuid('versionId');
        Route::get('/audit', [PricingController::class, 'audit']);
        Route::get('/tariff-families', [PricingController::class, 'tariffs']);
        Route::get('/service-tariffs/references', [PricingController::class, 'serviceTariffReferences']);
        Route::post('/matrix-workbooks/sample', [PricingController::class, 'matrixWorkbookSample']);
        Route::post('/matrix-workbooks/preview', [PricingController::class, 'matrixWorkbookPreview']);
        Route::get('/charge-types', [PricingController::class, 'chargeTypes']);
        Route::post('/charge-types', [PricingController::class, 'chargeType']);
        Route::get('/zone-sets', [PricingController::class, 'zoneSets']);
        Route::get('/zone-set-versions/references', [PricingController::class, 'zoneSetVersionReferences']);
        Route::post('/zone-sets', [PricingController::class, 'zoneSet']);
        Route::get('/{kind}/identities/{identityId}/versions', [PricingController::class, 'history'])->whereIn('kind', ['tariffs', 'zone-sets'])->whereUuid('identityId');
        Route::post('/{kind}/identities/{identityId}/versions', [PricingController::class, 'clone'])->whereIn('kind', ['tariffs', 'zone-sets'])->whereUuid('identityId');
        Route::patch('/zone-set-versions/{versionId}', [PricingController::class, 'updateZone'])->whereUuid('versionId');
        Route::post('/zone-set-versions/{versionId}/validate', [PricingController::class, 'validateZoneSet'])->whereUuid('versionId');
        Route::post('/tariff-families', [PricingController::class, 'tariff']);
        Route::patch('/tariff-versions/{versionId}', [PricingController::class, 'updateTariff'])->whereUuid('versionId');
        Route::post('/tariff-versions/{versionId}/validate', [PricingController::class, 'validateTariff'])->whereUuid('versionId');
        Route::post('/{kind}/{versionId}/{action}', [PricingController::class, 'transition'])->whereIn('kind', ['tariffs', 'zone-sets'])->whereUuid('versionId')->whereIn('action', ['approve', 'publish', 'supersede', 'archive']);
    });
});
