<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\Login;

use Modules\Foundation\Application\Contracts\SecurityMetricRecorderInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Contracts\SessionIssuerInterface;
use Modules\Iam\Application\Contracts\UserIdentifierResolverInterface;
use Modules\Iam\Application\Dto\IssuedSessionDto;
use Modules\Iam\Application\Ports\PlatformContextValidatorInterface;
use Modules\Iam\Application\Repositories\CredentialRepositoryInterface;

final readonly class LoginHandler
{
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$Q2hhYm9rRHVtbXlTYWx0MTIzNA$0iWR0A5c96ac21KlSQNKN1XFZGkVjt0RcQbEcgZ36h0';

    public function __construct(
        private UserIdentifierResolverInterface $userIdentifierResolver,
        private SecurityMetricRecorderInterface $securityMetricRecorder,
        private PlatformContextValidatorInterface $platformContextValidator,
        private SessionIssuerInterface $sessionIssuer,
        private CredentialRepositoryInterface $credentialRepository,
    ) {}

    public function handle(LoginCommand $command): IssuedSessionDto
    {
        $identifier = $command->identifier;
        $password = $command->password;
        $deviceId = $command->deviceId;
        $deviceName = $command->deviceName;
        $correlationId = $command->correlationId;
        $ip = $command->ip;
        $userAgent = $command->userAgent;
        $user = $this->userIdentifierResolver->findByIdentifier($identifier);
        $credential = $user === null ? null : $this->credentialRepository->findForUser((string) $user->user_id);
        $hash = $credential?->password_hash ?? self::DUMMY_HASH;
        $passwordValid = password_verify($password, $hash);
        if ($user === null || $credential === null || ! $passwordValid) {
            $this->securityMetricRecorder->increment('auth.invalid_credentials', ['client' => SourceClient::BranchPanel->value]);
            throw new ApiException(ApiErrorCode::InvalidCredentials, 401, 'iam.invalid_credentials');
        }
        if ($user->status !== 'ACTIVE') {
            $this->securityMetricRecorder->increment('auth.inactive_rejected', ['client' => SourceClient::BranchPanel->value]);
            throw new ApiException(ApiErrorCode::Forbidden, 403, 'iam.user_is_not_active');
        }
        if ($user->hq_id === null && ! $this->platformContextValidator->hasActivePlatformAssignment((string) $user->user_id)) {
            throw new ApiException(ApiErrorCode::Forbidden, 403, 'common.access_denied');
        }

        return $this->sessionIssuer->createSession($user, $deviceId, $deviceName, $correlationId, $ip, $userAgent);
    }
}
