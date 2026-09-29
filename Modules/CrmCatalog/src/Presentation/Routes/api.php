<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CrmCatalog\Presentation\Http\Controllers\IndustryController;

Route::prefix('api/v1/crm')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::get('/industries', [IndustryController::class, 'index'])->name('crm.industries.index');
});
