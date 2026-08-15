<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Geography\Infrastructure\Http\GeographyController;

Route::prefix('api/v1/reference')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->group(function (): void {
        Route::get('/provinces', [GeographyController::class, 'provinces'])->name('reference.provinces.index');
        Route::get('/cities', [GeographyController::class, 'cities'])->name('reference.cities.index');
        Route::get('/cities/{cityId}', [GeographyController::class, 'city'])
            ->whereUuid('cityId')->name('reference.cities.show');
    });
