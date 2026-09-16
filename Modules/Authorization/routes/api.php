<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Authorization\Infrastructure\Http\AuthorizationController;

Route::prefix('api/v1')
    ->middleware(['api', 'access.auth', 'node.access', 'password.changed'])
    ->group(function (): void {
        Route::get('/me/context', [AuthorizationController::class, 'context'])->name('me.context');
        Route::get('/context/nodes', [AuthorizationController::class, 'nodes'])->name('context.nodes');
    });

Route::prefix('api/v1/iam')
    ->middleware(['api', 'access.auth', 'node.access', 'password.changed'])
    ->group(function (): void {
        Route::get('/assignment-options', [AuthorizationController::class, 'assignmentOptions'])->name('iam.assignment-options');
        Route::post('/roles', [AuthorizationController::class, 'createRole'])
            ->middleware('idempotent:iam.roles.create')->name('iam.roles.store');
        Route::get('/roles', [AuthorizationController::class, 'roles'])->name('iam.roles.index');
        Route::get('/roles/{roleId}', [AuthorizationController::class, 'role'])
            ->whereUuid('roleId')->name('iam.roles.show');
        Route::patch('/roles/{roleId}', [AuthorizationController::class, 'updateRole'])
            ->whereUuid('roleId')->name('iam.roles.update');
        Route::post('/roles/{roleId}/clone', [AuthorizationController::class, 'cloneRole'])
            ->whereUuid('roleId')->name('iam.roles.clone');
        Route::put('/roles/{roleId}/permissions', [AuthorizationController::class, 'replacePermissions'])
            ->whereUuid('roleId')->name('iam.roles.permissions.replace');
        Route::get('/permissions', [AuthorizationController::class, 'permissions'])->name('iam.permissions.index');
        Route::post('/users/{userId}/role-assignments', [AuthorizationController::class, 'createAssignments'])
            ->whereUuid('userId')->name('iam.assignments.store');
        Route::patch('/users/{userId}/role-assignments/{assignmentId}', [AuthorizationController::class, 'updateAssignment'])->whereUuid('userId')->whereUuid('assignmentId')->name('iam.assignments.update');
        Route::delete('/users/{userId}/role-assignments/{assignmentId}', [AuthorizationController::class, 'revokeAssignment'])
            ->whereUuid('userId')->whereUuid('assignmentId')->name('iam.assignments.revoke');
        Route::get('/module-entitlements', [AuthorizationController::class, 'entitlements'])
            ->name('iam.entitlements.index');
    });
