<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\Operations\Presentation\Http\Controllers\FleetAdministrationController;

Route::prefix('api/v1/fleet')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::get('/drivers', [FleetAdministrationController::class, 'drivers'])->name('fleet.drivers.index');
    Route::post('/drivers', [FleetAdministrationController::class, 'createDriver'])->middleware('idempotent:fleet.drivers.create')->name('fleet.drivers.store');
    Route::get('/drivers/{driver_id}', [FleetAdministrationController::class, 'driver'])->whereNumber('driver_id')->name('fleet.drivers.show');
    Route::patch('/drivers/{driver_id}', [FleetAdministrationController::class, 'updateDriver'])->whereNumber('driver_id')->name('fleet.drivers.update');
    Route::get('/vehicles', [FleetAdministrationController::class, 'vehicles'])->name('fleet.vehicles.index');
    Route::post('/vehicles', [FleetAdministrationController::class, 'createVehicle'])->middleware('idempotent:fleet.vehicles.create')->name('fleet.vehicles.store');
    Route::get('/vehicles/{vehicle_id}', [FleetAdministrationController::class, 'vehicle'])->whereNumber('vehicle_id')->name('fleet.vehicles.show');
    Route::patch('/vehicles/{vehicle_id}', [FleetAdministrationController::class, 'updateVehicle'])->whereNumber('vehicle_id')->name('fleet.vehicles.update');
});
