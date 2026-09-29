<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\Consignment\Presentation\Http\Controllers\ConsignmentController;
use Modules\Consignment\Presentation\Http\Controllers\ConsignmentNumberRangeController;
use Modules\Consignment\Presentation\Http\Controllers\OperationalStatusController;

Route::prefix('api/v1/admin/consignment-number-ranges')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::get('/', [ConsignmentNumberRangeController::class, 'index'])->name('admin.consignment-number-ranges.index');
    Route::post('/validate', [ConsignmentNumberRangeController::class, 'validateRange'])->name('admin.consignment-number-ranges.validate');
    Route::post('/', [ConsignmentNumberRangeController::class, 'store'])->middleware('idempotent:consignment-number-ranges.create')->name('admin.consignment-number-ranges.store');
    Route::get('/{rangeId}', [ConsignmentNumberRangeController::class, 'show'])->whereNumber('rangeId')->name('admin.consignment-number-ranges.show');
    Route::get('/{rangeId}/allocations', [ConsignmentNumberRangeController::class, 'allocations'])->whereNumber('rangeId')->name('admin.consignment-number-ranges.allocations');
    Route::post('/{rangeId}/disable', [ConsignmentNumberRangeController::class, 'disable'])->whereNumber('rangeId')->name('admin.consignment-number-ranges.disable');
});
Route::prefix('api/v1/consignments')->middleware(['api', 'access.auth', 'node.access', 'password.changed'])->group(function (): void {
    Route::get('/', [ConsignmentController::class, 'index'])->name('consignments.index');
    Route::post('/pricing-quotes', [ConsignmentController::class, 'quote'])->name('consignments.pricing-quotes.store');
    Route::post('/', [ConsignmentController::class, 'store'])->middleware('idempotent:consignments.create')->name('consignments.store');
    Route::get('/{consignmentId}', [ConsignmentController::class, 'show'])->whereNumber('consignmentId')->name('consignments.show');
    Route::patch('/{consignmentId}', [ConsignmentController::class, 'update'])->whereNumber('consignmentId')->name('consignments.update');
});
Route::prefix('api/v1/operational-statuses')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::get('/', [OperationalStatusController::class, 'index']);
    Route::post('/', [OperationalStatusController::class, 'store'])->middleware('idempotent:operational-status.create');
    Route::patch('/{id}', [OperationalStatusController::class, 'update'])->whereNumber('id');
});
