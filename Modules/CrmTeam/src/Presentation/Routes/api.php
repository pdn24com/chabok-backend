<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CrmTeam\Presentation\Http\Controllers\TeamController;
use Modules\CrmTeam\Presentation\Http\Controllers\TeamMemberController;

// CRM work teams and who is in them. The CRM role of a person lives in IAM, not here.
Route::prefix('api/v1/crm')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber(['teamId', 'membershipId'])
    ->group(function (): void {
        Route::get('/teams', [TeamController::class, 'index'])->name('crm.teams.index');
        Route::post('/teams', [TeamController::class, 'store'])->middleware('idempotent:crm-teams.create')->name('crm.teams.store');
        Route::patch('/teams/{teamId}', [TeamController::class, 'update'])->name('crm.teams.update');

        Route::get('/team-members', [TeamMemberController::class, 'index'])->name('crm.team-members.index');
        Route::post('/teams/{teamId}/members', [TeamMemberController::class, 'store'])->middleware('idempotent:crm-team-members.create')->name('crm.teams.members.store');
        // Ending a membership keeps the row and its history, so it is posted rather than deleted.
        Route::post('/memberships/{membershipId}/end', [TeamMemberController::class, 'end'])->name('crm.memberships.end');

        // The preview is declared first, so "preview" is never read as a bulk run of its own.
        Route::post('/memberships/bulk/preview', [TeamMemberController::class, 'bulkPreview'])->name('crm.memberships.bulk.preview');
        Route::post('/memberships/bulk', [TeamMemberController::class, 'bulk'])->middleware('idempotent:crm-memberships.bulk')->name('crm.memberships.bulk');
    });
