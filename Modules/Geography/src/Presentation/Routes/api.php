<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\Geography\Presentation\Http\Controllers\GeographyController;

Route::prefix('api/v1/reference')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::get('/countries', [GeographyController::class, 'countries'])->name('reference.countries.index');
    Route::get('/countries/{countryId}', [GeographyController::class, 'country'])->whereNumber('countryId')->name('reference.countries.show');
    Route::get('/provinces', [GeographyController::class, 'provinces'])->name('reference.provinces.index');
    Route::get('/cities', [GeographyController::class, 'cities'])->name('reference.cities.index');
    Route::get('/cities/{cityId}', [GeographyController::class, 'city'])->whereNumber('cityId')->name('reference.cities.show');
});
