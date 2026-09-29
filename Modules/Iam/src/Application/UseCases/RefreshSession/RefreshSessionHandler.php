<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\RefreshSession;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\AccessTokenServiceInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Contracts\SecurityMetricRecorderInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Application\Contracts\SessionLifecycleInterface;
use Modules\Iam\Application\Contracts\SessionRegistryInterface;
use Modules\Iam\Application\Dto\IdentityDto;
use Modules\Iam\Application\Dto\SessionCredentialsDto;
use Modules\Iam\Application\Repositories\SessionRepositoryInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Domain\Support\OpaqueToken;

final readonly class RefreshSessionHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private SessionRegistryInterface $sessionRegistry,
        private SecurityMetricRecorderInterface $securityMetricRecorder,
        private SessionLifecycleInterface $sessionLifecycle,
        private AuditWriterInterface $auditWriter,
        private ClockInterface $clock,
        private AccessTokenServiceInterface $accessTokenService,
        private SessionRepositoryInterface $sessionRepository,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(RefreshSessionCommand $command): SessionCredentialsDto
    {
        $rawToken = $command->rawToken;
        $correlationId = $command->correlationId;
        $ip = $command->ip;
        $userAgent = $command->userAgent;
        if ($rawToken === '') {
            IdentityDto::authenticationRequired();
        }
        $hash = OpaqueToken::hash($rawToken);
        $result = $this->connection->transaction(function () use ($hash, $correlationId, $ip, $userAgent): ?SessionCredentialsDto {
            $session = $this->sessionRepository->lockByRefreshToken($hash);
            if ($session === null) {
                $familyId = $this->sessionRegistry->rotatedFamily($hash);
                if ($familyId !== null) {
                    $this->securityMetricRecorder->increment('auth.refresh_reuse', ['client' => SourceClient::BranchPanel->value]);
                    $this->sessionLifecycle->revokeFamily($familyId, 'REFRESH_REUSE');
                    $this->auditWriter->write(null, null, 'SECURITY_REFRESH_REUSE_DETECTED', 'TOKEN_FAMILY', $familyId, $correlationId, safeNote: 'Rotated refresh token was reused; family revoked.', ipAddress: $ip, userAgent: $userAgent, sourceClient: SourceClient::BranchPanel->value);

                    return null;
                }
                IdentityDto::authenticationRequired();
            }
            if ($session->revoked_at !== null || strtotime((string) $session->expires_at) <= $this->clock->now()->getTimestamp()) {
                IdentityDto::authenticationRequired();
            }
            $user = $this->userRepository->find((string) $session->user_id);
            if ($user === null || $user->status !== 'ACTIVE') {
                IdentityDto::authenticationRequired();
            }
            $rawNext = OpaqueToken::generate();
            $nextHash = OpaqueToken::hash($rawNext);
            $remainingTtl = max(1, strtotime((string) $session->expires_at) - $this->clock->now()->getTimestamp());
            $this->sessionRepository->apply($session, [
                'refresh_token_hash' => $nextHash,
                'rotation_counter' => (int) $session->rotation_counter + 1,
                'rotated_at' => $this->clock->now(),
                'last_seen_at' => $this->clock->now(),
                'ip_address_hash' => IdentityDto::fingerprint($ip),
                'user_agent_hash' => IdentityDto::fingerprint($userAgent),
            ]);
            $this->sessionRegistry->rememberRotatedToken($hash, (string) $session->token_family_id, $remainingTtl);
            $principal = new AuthenticatedPrincipal((string) $session->user_id, (string) $session->session_id, $session->hq_id === null ? null : (string) $session->hq_id, (bool) $user->must_change_password);
            $accessToken = $this->accessTokenService->issue($principal);

            return new SessionCredentialsDto($accessToken, $rawNext, new DateTimeImmutable((string) $session->expires_at));
        }, attempts: 3);
        if ($result === null) {
            IdentityDto::authenticationRequired();
        }

        return $result;
    }
}
