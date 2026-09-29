<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CrmOpportunitie\Presentation\Http\Controllers\OpportunityController;
use Modules\CrmOpportunitie\Presentation\Http\Controllers\SalesFunnelController;

// The sales pipeline. Everything here sits behind the crm.opportunity permissions.
Route::prefix('api/v1/crm')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::get('/sales-funnels', [SalesFunnelController::class, 'index'])->name('crm.sales-funnels.index');
    Route::get('/opportunities', [OpportunityController::class, 'index'])->name('crm.opportunities.index');
    Route::post('/opportunities', [OpportunityController::class, 'store'])->name('crm.opportunities.store');
});

Route::prefix('api/v1/crm/opportunities/{opportunityId}')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber('opportunityId')
    ->group(function (): void {
        // A move is an entry appended to the history, so it is posted rather than patched onto the record.
        Route::post('/step-transitions', [OpportunityController::class, 'transition'])->name('crm.opportunities.step-transitions.store');
        Route::get('/events', [OpportunityController::class, 'events'])->name('crm.opportunities.events.index');
    });
