<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Consignment\Infrastructure\Http\ConsignmentController;

Route::prefix('api/v1/consignments')
    ->middleware(['api', 'access.auth', 'node.access', 'password.changed'])
    ->group(function (): void {
        Route::get('/', [ConsignmentController::class, 'index'])->name('consignments.index');
        Route::post('/pricing-quotes', [ConsignmentController::class, 'quote'])
            ->name('consignments.pricing-quotes.store');
        Route::post('/', [ConsignmentController::class, 'store'])
            ->middleware('idempotent:consignments.create')->name('consignments.store');
        Route::get('/{consignmentId}', [ConsignmentController::class, 'show'])
            ->whereUuid('consignmentId')->name('consignments.show');
        Route::patch('/{consignmentId}', [ConsignmentController::class, 'update'])
            ->whereUuid('consignmentId')->name('consignments.update');
    });
