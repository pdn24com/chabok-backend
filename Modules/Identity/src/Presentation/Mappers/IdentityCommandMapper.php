<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Mappers;

use Illuminate\Http\Request;
use Modules\Identity\Application\UseCases\Login\LoginCommand;
use Modules\Identity\Application\UseCases\RefreshSession\RefreshSessionCommand;
use Modules\Identity\Application\UseCases\Logout\LogoutCommand;
use Modules\Identity\Application\UseCases\LogoutAll\LogoutAllCommand;
use Modules\Identity\Application\UseCases\SendOtp\SendOtpCommand;
use Modules\Identity\Application\UseCases\VerifyOtp\VerifyOtpCommand;
use Modules\Identity\Application\UseCases\ActivatePassword\ActivatePasswordCommand;
use Modules\Identity\Application\UseCases\ResetPassword\ResetPasswordCommand;
use Modules\Identity\Application\UseCases\ChangePassword\ChangePasswordCommand;
use Modules\Identity\Application\UseCases\ListSessions\ListSessionsCommand;
use Modules\Identity\Application\UseCases\RevokeOwnedSession\RevokeOwnedSessionCommand;

final class IdentityCommandMapper
{
    public static function login(Request $request, string $identifier, string $password, ?string $deviceId, ?string $deviceName): LoginCommand
    {
        return new LoginCommand($identifier, $password, $deviceId, $deviceName, (string) $request->attributes->get('correlation_id'), $request->ip(), $request->userAgent());
    }

    public static function refresh(Request $request, string $rawToken): RefreshSessionCommand
    {
        return new RefreshSessionCommand($rawToken, (string) $request->attributes->get('correlation_id'), $request->ip(), $request->userAgent());
    }

    public static function logout(Request $request, ?string $rawToken): LogoutCommand
    {
        return new LogoutCommand($rawToken, (string) $request->attributes->get('correlation_id'));
    }

    public static function logoutAll(Request $request, ?string $currentPassword, ?string $verificationToken): LogoutAllCommand
    {
        return new LogoutAllCommand($request->attributes->get('principal'), $currentPassword, $verificationToken, (string) $request->attributes->get('correlation_id'));
    }

    public static function sendOtp(Request $request, string $identifier, string $purpose): SendOtpCommand
    {
        return new SendOtpCommand($identifier, $purpose, (string) $request->attributes->get('correlation_id'), $request->ip());
    }

    public static function verifyOtp(Request $request, string $challengeId, string $code): VerifyOtpCommand
    {
        return new VerifyOtpCommand($challengeId, $code);
    }

    public static function activatePassword(Request $request, ?string $verificationToken, ?string $invitationToken, string $newPassword): ActivatePasswordCommand
    {
        return new ActivatePasswordCommand($verificationToken, $invitationToken, $newPassword, (string) $request->attributes->get('correlation_id'));
    }

    public static function resetPassword(Request $request, string $verificationToken, string $newPassword): ResetPasswordCommand
    {
        return new ResetPasswordCommand($verificationToken, $newPassword, (string) $request->attributes->get('correlation_id'));
    }

    public static function changePassword(Request $request, string $currentPassword, string $newPassword): ChangePasswordCommand
    {
        return new ChangePasswordCommand($request->attributes->get('principal'), $currentPassword, $newPassword, (string) $request->attributes->get('correlation_id'));
    }

    public static function listSessions(Request $request): ListSessionsCommand
    {
        return new ListSessionsCommand($request->attributes->get('principal')->userId);
    }

    public static function revokeOwnedSession(Request $request, string $sessionId): RevokeOwnedSessionCommand
    {
        return new RevokeOwnedSessionCommand($request->attributes->get('principal'), $sessionId, (string) $request->attributes->get('correlation_id'));
    }
}
