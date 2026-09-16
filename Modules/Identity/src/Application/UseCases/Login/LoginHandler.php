<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\Login;

use Modules\Foundation\Application\Contracts\SecurityMetricRecorder;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Identity\Application\Contracts\PlatformContextValidator;
use Modules\Identity\Application\Repositories\CredentialRepository;
use Modules\Identity\Application\Services\SessionIssuer;
use Modules\User\Application\Repositories\UserRepository;

final readonly class LoginHandler
{
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$Q2hhYm9rRHVtbXlTYWx0MTIzNA$0iWR0A5c96ac21KlSQNKN1XFZGkVjt0RcQbEcgZ36h0';

    public function __construct(
        private UserRepository $users,
        private CredentialRepository $credentials,
        private SecurityMetricRecorder $metrics,
        private PlatformContextValidator $platformContext,
        private SessionIssuer $sessionIssuer,
    )
    {
    }

    public function handle(LoginCommand $command): LoginResult
    {
        return new LoginResult($this->execute($command->identifier, $command->password, $command->deviceId, $command->deviceName, $command->correlationId, $command->ip, $command->userAgent));
    }

    private function execute(
        string $identifier,
        string $password,
        ?string $deviceId,
        ?string $deviceName,
        string $correlationId,
        ?string $ip,
        ?string $userAgent,
    ): array
    {
        $user = $this->users->findByIdentifier($identifier);
        $credential = $user === null ? null : $this->credentials->findForUser($user['user_id']);
        $hash = $credential?->password_hash ?? self::DUMMY_HASH;
        $passwordValid = password_verify($password, $hash);
        if ($user === null || $credential === null || !$passwordValid) {
            $this->metrics->increment('auth.invalid_credentials', ['client' => 'BRANCH_PANEL']);
            throw new ApiException(ApiErrorCode::InvalidCredentials, 401, 'Invalid credentials.');
        }
        if ($user['status'] !== 'ACTIVE') {
            $this->metrics->increment('auth.inactive_rejected', ['client' => 'BRANCH_PANEL']);
            throw new ApiException(ApiErrorCode::Forbidden, 403, 'User is not active.');
        }
        if ($user['hq_id'] === null && !$this->platformContext->hasActivePlatformAssignment((string) $user['user_id'])) {
            throw new ApiException(ApiErrorCode::Forbidden, 403, 'Access denied.');
        }
        return $this->sessionIssuer->createSession($user, $deviceId, $deviceName, $correlationId, $ip, $userAgent);
    }
}
