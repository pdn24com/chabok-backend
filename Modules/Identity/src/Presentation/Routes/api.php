<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\Identity\Presentation\Http\Controllers\IdentityController;
use Modules\Identity\Presentation\Http\Middleware\AuthenticateLogoutAll;
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
        Route::get('/me/sessions', [IdentityController::class, 'sessions'])->name('me.sessions.index');
        Route::delete('/me/sessions/{sessionId}', [IdentityController::class, 'revokeSession'])->whereUuid('sessionId')->name('me.sessions.revoke');
    });
});
