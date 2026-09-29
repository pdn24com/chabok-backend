<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\Iam\Presentation\Http\Controllers\IdentityController;
use Modules\Iam\Presentation\Http\Controllers\UserController;
use Modules\Iam\Presentation\Http\Middleware\AuthenticateLogoutAll;

Route::prefix('api/v1')->middleware('api')->group(function (): void {
    Route::middleware('throttle:auth-login')->post('/auth/login', [IdentityController::class, 'login'])->name('auth.login');
    Route::middleware(['origin.exact', 'throttle:auth-refresh'])->post('/auth/refresh', [IdentityController::class, 'refresh'])->name('auth.refresh');
    Route::middleware('origin.exact')->post('/auth/logout', [IdentityController::class, 'logout'])->name('auth.logout');
    Route::middleware(['origin.exact', AuthenticateLogoutAll::class])->post('/auth/logout-all', [IdentityController::class, 'logoutAll'])->name('auth.logout-all');
    Route::middleware('throttle:auth-otp-send')->post('/auth/otp/send', [IdentityController::class, 'sendOtp'])->name('auth.otp.send');
    Route::middleware('throttle:auth-otp-verify')->post('/auth/otp/verify', [IdentityController::class, 'verifyOtp'])->name('auth.otp.verify');
    Route::middleware('throttle:auth-password')->group(function (): void {
        Route::post('/auth/password/activate', [IdentityController::class, 'activatePassword'])->name('auth.password.activate');
        Route::post('/auth/password/reset', [IdentityController::class, 'resetPassword'])->name('auth.password.reset');
    });
    Route::middleware(['access.auth', 'node.access', 'password.changed'])->group(function (): void {
        Route::post('/auth/password/change', [IdentityController::class, 'changePassword'])->withoutMiddleware('password.changed')->name('auth.password.change');
        Route::get('/me', [UserController::class, 'me'])->name('me.show');
        Route::patch('/me/profile', [UserController::class, 'updateSelf'])->name('me.profile.update');
        Route::get('/me/sessions', [IdentityController::class, 'sessions'])->name('me.sessions.index');
        Route::delete('/me/sessions/{sessionId}', [IdentityController::class, 'revokeSession'])->whereNumber('sessionId')->name('me.sessions.revoke');
    });
});
Route::prefix('api/v1/iam')
    ->middleware('api')
    ->middleware(['access.auth', 'node.access', 'password.changed'])
    ->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name('iam.users.index');
        Route::post('/users', [UserController::class, 'store'])->middleware('idempotent:iam.users.create')->name('iam.users.store');
        Route::post('/users/{userId}/operational-profile', [UserController::class, 'operationalProfile'])
            ->whereNumber('userId')
            ->middleware('idempotent:iam.users.operational-profile')
            ->name('iam.users.operational-profile');
        Route::get('/users/{userId}', [UserController::class, 'show'])->whereNumber('userId')->name('iam.users.show');
        Route::patch('/users/{userId}', [UserController::class, 'update'])->whereNumber('userId')->name('iam.users.update');
        Route::post('/users/{userId}/suspend', [UserController::class, 'suspend'])->whereNumber('userId')->name('iam.users.suspend');
        Route::post('/users/{userId}/activate', [UserController::class, 'activate'])->whereNumber('userId')->name('iam.users.activate');
        Route::post('/users/{userId}/deactivate', [UserController::class, 'deactivate'])->whereNumber('userId')->name('iam.users.deactivate');
        Route::post('/users/{userId}/invite', [UserController::class, 'invite'])
            ->middleware('idempotent:iam.users.invite')
            ->whereNumber('userId')
            ->name('iam.users.invite');
        Route::post('/users/{userId}/temporary-password', [UserController::class, 'temporaryPassword'])->whereNumber('userId')->name('iam.users.temporary-password');
        Route::post('/users/{userId}/revoke-sessions', [UserController::class, 'revokeSessions'])->whereNumber('userId')->name('iam.users.revoke-sessions');
    });
