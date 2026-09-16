<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Presentation\Http\Requests\ChangePasswordRequest;
use Modules\Identity\Presentation\Http\Requests\ResetPasswordRequest;
use Modules\Identity\Presentation\Http\Requests\ActivatePasswordRequest;
use Modules\Identity\Presentation\Http\Requests\VerifyOtpRequest;
use Modules\Identity\Presentation\Http\Requests\SendOtpRequest;
use Modules\Identity\Presentation\Http\Requests\LogoutAllRequest;
use Modules\Identity\Presentation\Http\Requests\LoginRequest;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Presentation\Http\StrictPayload;
use Modules\Foundation\Presentation\Http\RefreshCookieFactory;
use Modules\Identity\Presentation\Mappers\IdentityCommandMapper;
use Modules\Identity\Application\UseCases\Login\LoginHandler;
use Modules\Identity\Application\UseCases\RefreshSession\RefreshSessionHandler;
use Modules\Identity\Application\UseCases\Logout\LogoutHandler;
use Modules\Identity\Application\UseCases\LogoutAll\LogoutAllHandler;
use Modules\Identity\Application\UseCases\SendOtp\SendOtpHandler;
use Modules\Identity\Application\UseCases\VerifyOtp\VerifyOtpHandler;
use Modules\Identity\Application\UseCases\ActivatePassword\ActivatePasswordHandler;
use Modules\Identity\Application\UseCases\ResetPassword\ResetPasswordHandler;
use Modules\Identity\Application\UseCases\ChangePassword\ChangePasswordHandler;
use Modules\Identity\Application\UseCases\ListSessions\ListSessionsHandler;
use Modules\Identity\Application\UseCases\RevokeOwnedSession\RevokeOwnedSessionHandler;

final readonly class IdentityController
{
    public function __construct(
        private LoginHandler $loginHandler,
        private RefreshSessionHandler $refreshHandler,
        private LogoutHandler $logoutHandler,
        private LogoutAllHandler $logoutAllHandler,
        private SendOtpHandler $sendOtpHandler,
        private VerifyOtpHandler $verifyOtpHandler,
        private ActivatePasswordHandler $activatePasswordHandler,
        private ResetPasswordHandler $resetPasswordHandler,
        private ChangePasswordHandler $changePasswordHandler,
        private ListSessionsHandler $listSessionsHandler,
        private RevokeOwnedSessionHandler $revokeOwnedSessionHandler,
        private RefreshCookieFactory $cookies,
    )
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $input = $request->validated();
        $result = $this->loginHandler->handle(IdentityCommandMapper::login($request, $input['identifier'], $input['password'], $input['device_id'] ?? null, $input['device_name'] ?? null))->data;
        return ApiResponder::success($request, $result['payload'])->withCookie($this->cookies->create($result['refresh_token'], $result['refresh_expires_at']));
    }

    public function refresh(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, []);
        $result = $this->refreshHandler->handle(IdentityCommandMapper::refresh($request, (string) $request->cookie((string) config('chabok.refresh_cookie.name'), '')))->data;
        return ApiResponder::success($request, $result['payload'])->withCookie($this->cookies->create($result['refresh_token'], $result['refresh_expires_at']));
    }

    public function logout(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, []);
        $this->logoutHandler->handle(IdentityCommandMapper::logout($request, $request->cookie((string) config('chabok.refresh_cookie.name'))));
        return ApiResponder::success($request, ['success' => true])->withCookie($this->cookies->clear());
    }

    public function logoutAll(LogoutAllRequest $request): JsonResponse
    {
        $input = $request->validated();
        $count = $this->logoutAllHandler->handle(IdentityCommandMapper::logoutAll($request, $input['current_password'] ?? null, $input['step_up_verification_token'] ?? null))->data;
        return ApiResponder::success($request, ['revoked_session_count' => $count])->withCookie($this->cookies->clear());
    }

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->sendOtpHandler->handle(IdentityCommandMapper::sendOtp($request, $input['identifier'], $input['purpose']))->data);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->verifyOtpHandler->handle(IdentityCommandMapper::verifyOtp($request, $input['challenge_id'], $input['code']))->data);
    }

    public function activatePassword(ActivatePasswordRequest $request): JsonResponse
    {
        $input = $request->validated();
        $userId = $this->activatePasswordHandler->handle(IdentityCommandMapper::activatePassword($request, $input['verification_token'] ?? null, $input['invitation_token'] ?? null, $input['new_password']))->data;
        return ApiResponder::success($request, ['user_id' => $userId, 'status' => 'ACTIVE']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $input = $request->validated();
        $result = $this->resetPasswordHandler->handle(IdentityCommandMapper::resetPassword($request, $input['verification_token'], $input['new_password']))->data;
        return ApiResponder::success($request, ['revoked_session_count' => $result['revoked_session_count']]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $input = $request->validated();
        $this->changePasswordHandler->handle(IdentityCommandMapper::changePassword($request, $input['current_password'], $input['new_password']));
        return ApiResponder::success($request, ['success' => true]);
    }

    public function sessions(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->listSessionsHandler->handle(IdentityCommandMapper::listSessions($request))->data);
    }

    public function revokeSession(Request $request, string $sessionId): JsonResponse
    {
        $this->revokeOwnedSessionHandler->handle(IdentityCommandMapper::revokeOwnedSession($request, $sessionId));
        return ApiResponder::success($request, ['success' => true]);
    }
}
