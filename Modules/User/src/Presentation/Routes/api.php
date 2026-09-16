<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\User\Presentation\Http\Controllers\UserController;
Route::prefix('api/v1')->middleware(['api', 'access.auth', 'node.access', 'password.changed'])->group(function (): void {
    Route::get('/me', [UserController::class, 'me'])->name('me.show');
    Route::patch('/me/profile', [UserController::class, 'updateSelf'])->name('me.profile.update');
});
Route::prefix('api/v1/iam')->middleware('api')->middleware(['access.auth', 'node.access', 'password.changed'])->group(function (): void {
    Route::get('/users', [UserController::class, 'index'])->name('iam.users.index');
    Route::post('/users', [UserController::class, 'store'])->middleware('idempotent:iam.users.create')->name('iam.users.store');
    Route::post('/users/{userId}/operational-profile', [UserController::class, 'operationalProfile'])->whereUuid('userId')->middleware('idempotent:iam.users.operational-profile')->name('iam.users.operational-profile');
    Route::get('/users/{userId}', [UserController::class, 'show'])->whereUuid('userId')->name('iam.users.show');
    Route::patch('/users/{userId}', [UserController::class, 'update'])->whereUuid('userId')->name('iam.users.update');
    Route::post('/users/{userId}/suspend', [UserController::class, 'suspend'])->whereUuid('userId')->name('iam.users.suspend');
    Route::post('/users/{userId}/activate', [UserController::class, 'activate'])->whereUuid('userId')->name('iam.users.activate');
    Route::post('/users/{userId}/deactivate', [UserController::class, 'deactivate'])->whereUuid('userId')->name('iam.users.deactivate');
    Route::post('/users/{userId}/invite', [UserController::class, 'invite'])->middleware('idempotent:iam.users.invite')->whereUuid('userId')->name('iam.users.invite');
    Route::post('/users/{userId}/temporary-password', [UserController::class, 'temporaryPassword'])->whereUuid('userId')->name('iam.users.temporary-password');
    Route::post('/users/{userId}/revoke-sessions', [UserController::class, 'revokeSessions'])->whereUuid('userId')->name('iam.users.revoke-sessions');
});
