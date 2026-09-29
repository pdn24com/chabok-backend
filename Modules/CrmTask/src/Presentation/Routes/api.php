<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CrmTask\Presentation\Http\Controllers\TaskController;

Route::prefix('api/v1/tasks')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber('taskId')
    ->group(function (): void {
        Route::get('/', [TaskController::class, 'index'])->name('crm.tasks.index');
        Route::post('/', [TaskController::class, 'store'])->name('crm.tasks.store');
        Route::post('/{taskId}/complete', [TaskController::class, 'complete'])->name('crm.tasks.complete');
        Route::post('/{taskId}/assignments', [TaskController::class, 'assign'])->name('crm.tasks.assignments.store');
        Route::post('/{taskId}/actions', [TaskController::class, 'recordAction'])->name('crm.tasks.actions.store');
    });
