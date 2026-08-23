<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Manifest\Infrastructure\Http\ManifestController;

Route::prefix('api/v1/manifests')
    ->middleware(['api', 'access.auth', 'node.access', 'password.changed'])
    ->group(function (): void {
        Route::get('/', [ManifestController::class, 'index'])->name('manifests.index');
        Route::post('/', [ManifestController::class, 'store'])
            ->middleware('idempotent:manifests.create')->name('manifests.store');
        Route::get('/context-options', [ManifestController::class, 'contextOptions'])
            ->name('manifests.context-options.index');
        Route::get('/{manifestId}', [ManifestController::class, 'show'])
            ->whereUuid('manifestId')->name('manifests.show');
        Route::patch('/{manifestId}', [ManifestController::class, 'update'])
            ->whereUuid('manifestId')->name('manifests.update');
        Route::get('/{manifestId}/eligible-parcels', [ManifestController::class, 'eligible'])
            ->whereUuid('manifestId')->name('manifests.eligible-parcels.index');
        Route::post('/{manifestId}/parcels', [ManifestController::class, 'addParcels'])
            ->middleware('idempotent:manifests.add-parcels')
            ->whereUuid('manifestId')->name('manifests.parcels.store');
        Route::post('/{manifestId}/validate', [ManifestController::class, 'validateManifest'])
            ->middleware('idempotent:manifests.validate')
            ->whereUuid('manifestId')->name('manifests.validate');
        Route::post('/{manifestId}/confirm', [ManifestController::class, 'confirm'])
            ->middleware('idempotent:manifests.confirm')
            ->whereUuid('manifestId')->name('manifests.confirm');
    });
