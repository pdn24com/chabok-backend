<?php

declare(strict_types=1);

namespace Modules\Identity\Application;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Identity\Application\Data\IdentityData;
use Modules\Identity\Application\Services\SessionLifecycle;
use Modules\Identity\Application\UseCases\ActivatePassword\ActivatePasswordCommand;
use Modules\Identity\Application\UseCases\ActivatePassword\ActivatePasswordHandler;
use Modules\Identity\Application\UseCases\ChangePassword\ChangePasswordCommand;
use Modules\Identity\Application\UseCases\ChangePassword\ChangePasswordHandler;
use Modules\Identity\Application\UseCases\CreateInvitation\CreateInvitationCommand;
use Modules\Identity\Application\UseCases\CreateInvitation\CreateInvitationHandler;
use Modules\Identity\Application\UseCases\ListSessions\ListSessionsCommand;
use Modules\Identity\Application\UseCases\ListSessions\ListSessionsHandler;
use Modules\Identity\Application\UseCases\Login\LoginCommand;
use Modules\Identity\Application\UseCases\Login\LoginHandler;
use Modules\Identity\Application\UseCases\LogoutAll\LogoutAllCommand;
use Modules\Identity\Application\UseCases\LogoutAll\LogoutAllHandler;
use Modules\Identity\Application\UseCases\Logout\LogoutCommand;
use Modules\Identity\Application\UseCases\Logout\LogoutHandler;
use Modules\Identity\Application\UseCases\ProvisionPassword\ProvisionPasswordCommand;
use Modules\Identity\Application\UseCases\ProvisionPassword\ProvisionPasswordHandler;
use Modules\Identity\Application\UseCases\RefreshSession\RefreshSessionCommand;
use Modules\Identity\Application\UseCases\RefreshSession\RefreshSessionHandler;
use Modules\Identity\Application\UseCases\ResetPassword\ResetPasswordCommand;
use Modules\Identity\Application\UseCases\ResetPassword\ResetPasswordHandler;
use Modules\Identity\Application\UseCases\RevokeOwnedSession\RevokeOwnedSessionCommand;
use Modules\Identity\Application\UseCases\RevokeOwnedSession\RevokeOwnedSessionHandler;
use Modules\Identity\Application\UseCases\SendOtp\SendOtpCommand;
use Modules\Identity\Application\UseCases\SendOtp\SendOtpHandler;
use Modules\Identity\Application\UseCases\VerifyOtp\VerifyOtpCommand;
use Modules\Identity\Application\UseCases\VerifyOtp\VerifyOtpHandler;
use Modules\User\Application\Contracts\IdentityProvisioner;
use Modules\User\Application\Contracts\UserSessionManager;
/** Internal compatibility facade and cross-module IAM port implementation. */

final readonly class IdentityService implements IdentityProvisioner, UserSessionManager
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
        private ProvisionPasswordHandler $provisionPasswordHandler,
        private CreateInvitationHandler $createInvitationHandler,
        private ListSessionsHandler $listSessionsHandler,
        private RevokeOwnedSessionHandler $revokeOwnedSessionHandler,
        private SessionLifecycle $sessionLifecycle,
    )
    {
    }

    public function login(
        string $identifier,
        string $password,
        ?string $deviceId,
        ?string $deviceName,
        string $correlationId,
        ?string $ip,
        ?string $userAgent,
    ): array
    {
        return $this->loginHandler->handle(new LoginCommand($identifier, $password, $deviceId, $deviceName, $correlationId, $ip, $userAgent))->data;
    }

    public function refresh(string $rawToken, string $correlationId, ?string $ip, ?string $userAgent): array
    {
        return $this->refreshHandler->handle(new RefreshSessionCommand($rawToken, $correlationId, $ip, $userAgent))->data;
    }

    public function logout(?string $rawToken, string $correlationId): void
    {
        $this->logoutHandler->handle(new LogoutCommand($rawToken, $correlationId));
    }

    public function logoutAll(
        AuthenticatedPrincipal $actor,
        ?string $currentPassword,
        ?string $verificationToken,
        string $correlationId,
    ): int
    {
        return $this->logoutAllHandler->handle(new LogoutAllCommand($actor, $currentPassword, $verificationToken, $correlationId))->data;
    }

    public function sendOtp(string $identifier, string $purpose, string $correlationId, ?string $ip): array
    {
        return $this->sendOtpHandler->handle(new SendOtpCommand($identifier, $purpose, $correlationId, $ip))->data;
    }

    public function verifyOtp(string $challengeId, string $code): array
    {
        return $this->verifyOtpHandler->handle(new VerifyOtpCommand($challengeId, $code))->data;
    }

    public function activatePassword(?string $verificationToken, ?string $invitationToken, string $newPassword, string $correlationId): string
    {
        return $this->activatePasswordHandler->handle(new ActivatePasswordCommand($verificationToken, $invitationToken, $newPassword, $correlationId))->data;
    }

    public function resetPassword(string $verificationToken, string $newPassword, string $correlationId): array
    {
        return $this->resetPasswordHandler->handle(new ResetPasswordCommand($verificationToken, $newPassword, $correlationId))->data;
    }

    public function changePassword(AuthenticatedPrincipal $actor, string $currentPassword, string $newPassword, string $correlationId): void
    {
        $this->changePasswordHandler->handle(new ChangePasswordCommand($actor, $currentPassword, $newPassword, $correlationId));
    }

    public function provisionPassword(string $userId, string $password): void
    {
        $this->provisionPasswordHandler->handle(new ProvisionPasswordCommand($userId, $password));
    }

    public function createInvitation(array $user, string $channel, ?string $actorId, string $correlationId): void
    {
        $this->createInvitationHandler->handle(new CreateInvitationCommand($user, $channel, $actorId, $correlationId));
    }

    public function listSessions(string $userId): array
    {
        return $this->listSessionsHandler->handle(new ListSessionsCommand($userId))->data;
    }

    public function revokeOwnedSession(AuthenticatedPrincipal $actor, string $sessionId, string $correlationId): void
    {
        $this->revokeOwnedSessionHandler->handle(new RevokeOwnedSessionCommand($actor, $sessionId, $correlationId));
    }

    public function revokeUserSessions(string $userId, string $reason): int
    {
        return $this->sessionLifecycle->revokeUserSessions($userId, $reason);
    }

    public function publicUser(array $user): array
    {
        return IdentityData::publicUser($user);
    }
}
