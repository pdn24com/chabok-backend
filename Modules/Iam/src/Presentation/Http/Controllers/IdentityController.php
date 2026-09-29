<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Presentation\Http\RefreshCookieFactory;
use Modules\Foundation\Presentation\Http\Resources\SessionSummaryResource;
use Modules\Foundation\Presentation\Http\StrictPayload;
use Modules\Iam\Application\UseCases\ActivatePassword\ActivatePasswordHandler;
use Modules\Iam\Application\UseCases\ChangePassword\ChangePasswordHandler;
use Modules\Iam\Application\UseCases\ListSessions\ListSessionsHandler;
use Modules\Iam\Application\UseCases\Login\LoginHandler;
use Modules\Iam\Application\UseCases\Logout\LogoutHandler;
use Modules\Iam\Application\UseCases\LogoutAll\LogoutAllHandler;
use Modules\Iam\Application\UseCases\RefreshSession\RefreshSessionHandler;
use Modules\Iam\Application\UseCases\ResetPassword\ResetPasswordHandler;
use Modules\Iam\Application\UseCases\RevokeOwnedSession\RevokeOwnedSessionHandler;
use Modules\Iam\Application\UseCases\SendOtp\SendOtpHandler;
use Modules\Iam\Application\UseCases\VerifyOtp\VerifyOtpHandler;
use Modules\Iam\Presentation\Http\Requests\ActivatePasswordRequest;
use Modules\Iam\Presentation\Http\Requests\ChangePasswordRequest;
use Modules\Iam\Presentation\Http\Requests\LoginRequest;
use Modules\Iam\Presentation\Http\Requests\LogoutAllRequest;
use Modules\Iam\Presentation\Http\Requests\ResetPasswordRequest;
use Modules\Iam\Presentation\Http\Requests\SendOtpRequest;
use Modules\Iam\Presentation\Http\Requests\VerifyOtpRequest;
use Modules\Iam\Presentation\Http\Resources\IssuedSessionResource;
use Modules\Iam\Presentation\Http\Resources\SendOtpResource;
use Modules\Iam\Presentation\Http\Resources\SessionCredentialsResource;
use Modules\Iam\Presentation\Http\Resources\VerifyOtpResource;
use Modules\Iam\Presentation\Mappers\IdentityCommandMapper;

final class IdentityController
{
    public function login(LoginRequest $request, RefreshCookieFactory $cookies, LoginHandler $loginHandler): JsonResponse
    {
        $input = $request->validated();
        $result = $loginHandler->handle(IdentityCommandMapper::login($request, $input['identifier'], $input['password'], $input['device_id'] ?? null, $input['device_name'] ?? null));

        return ApiResponder::success($request, (new IssuedSessionResource($result))->resolve($request))->withCookie($cookies->create($result->credentials->refreshToken, $result->credentials->refreshExpiresAt));
    }

    public function refresh(Request $request, RefreshCookieFactory $cookies, RefreshSessionHandler $refreshSessionHandler): JsonResponse
    {
        StrictPayload::assertOnly($request, []);
        $result = $refreshSessionHandler->handle(IdentityCommandMapper::refresh($request, (string) $request->cookie((string) config('chabok.refresh_cookie.name'), '')));

        return ApiResponder::success($request, (new SessionCredentialsResource($result))->resolve($request))->withCookie($cookies->create($result->refreshToken, $result->refreshExpiresAt));
    }

    public function logout(Request $request, RefreshCookieFactory $cookies, LogoutHandler $logoutHandler): JsonResponse
    {
        StrictPayload::assertOnly($request, []);
        $logoutHandler->handle(IdentityCommandMapper::logout($request, $request->cookie((string) config('chabok.refresh_cookie.name'))));

        return ApiResponder::success($request, ['success' => true])->withCookie($cookies->clear());
    }

    public function logoutAll(LogoutAllRequest $request, RefreshCookieFactory $cookies, LogoutAllHandler $logoutAllHandler): JsonResponse
    {
        $input = $request->validated();
        $count = $logoutAllHandler->handle(IdentityCommandMapper::logoutAll($request, $input['current_password'] ?? null, $input['step_up_verification_token'] ?? null))->data;

        return ApiResponder::success($request, ['revoked_session_count' => $count])->withCookie($cookies->clear());
    }

    public function sendOtp(SendOtpRequest $request, SendOtpHandler $sendOtpHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new SendOtpResource($sendOtpHandler->handle(IdentityCommandMapper::sendOtp($request, $input['identifier'], $input['purpose']))))->resolve($request));
    }

    public function verifyOtp(VerifyOtpRequest $request, VerifyOtpHandler $verifyOtpHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new VerifyOtpResource($verifyOtpHandler->handle(IdentityCommandMapper::verifyOtp($request, $input['challenge_id'], $input['code']))))->resolve($request));
    }

    public function activatePassword(ActivatePasswordRequest $request, ActivatePasswordHandler $activatePasswordHandler): JsonResponse
    {
        $input = $request->validated();
        $userId = $activatePasswordHandler->handle(IdentityCommandMapper::activatePassword($request, $input['verification_token'] ?? null, $input['invitation_token'] ?? null, $input['new_password']))->data;

        return ApiResponder::success($request, ['user_id' => $userId, 'status' => 'ACTIVE']);
    }

    public function resetPassword(ResetPasswordRequest $request, ResetPasswordHandler $resetPasswordHandler): JsonResponse
    {
        $input = $request->validated();
        $result = $resetPasswordHandler->handle(IdentityCommandMapper::resetPassword($request, $input['verification_token'], $input['new_password']));

        return ApiResponder::success($request, ['revoked_session_count' => $result->revokedSessionCount]);
    }

    public function changePassword(ChangePasswordRequest $request, ChangePasswordHandler $changePasswordHandler): JsonResponse
    {
        $input = $request->validated();
        $changePasswordHandler->handle(IdentityCommandMapper::changePassword($request, $input['current_password'], $input['new_password']));

        return ApiResponder::success($request, ['success' => true]);
    }

    public function sessions(Request $request, ListSessionsHandler $listSessionsHandler): JsonResponse
    {
        return ApiResponder::success($request, SessionSummaryResource::collection($listSessionsHandler->handle(IdentityCommandMapper::listSessions($request)))->resolve($request));
    }

    public function revokeSession(Request $request, RevokeOwnedSessionHandler $revokeOwnedSessionHandler, string $sessionId): JsonResponse
    {
        $revokeOwnedSessionHandler->handle(IdentityCommandMapper::revokeOwnedSession($request, $sessionId));

        return ApiResponder::success($request, ['success' => true]);
    }
}
