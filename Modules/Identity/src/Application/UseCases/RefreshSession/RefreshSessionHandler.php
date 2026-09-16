<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\RefreshSession;

use Modules\Foundation\Application\Contracts\AccessTokenService;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\SecurityMetricRecorder;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Identity\Application\Contracts\SessionRegistry;
use Modules\Identity\Application\Data\IdentityData;
use Modules\Identity\Application\Repositories\SessionRepository;
use Modules\Identity\Application\Services\SessionLifecycle;
use Modules\Identity\Domain\OpaqueToken;
use Modules\User\Application\Repositories\UserRepository;

final readonly class RefreshSessionHandler
{
    public function __construct(
        private TransactionManager $transactions,
        private SessionRepository $sessionRepository,
        private SessionRegistry $sessionRegistry,
        private SecurityMetricRecorder $metrics,
        private SessionLifecycle $sessionLifecycle,
        private AuditWriter $audit,
        private Clock $clock,
        private UserRepository $users,
        private AccessTokenService $accessTokens,
    )
    {
    }

    public function handle(RefreshSessionCommand $command): RefreshSessionResult
    {
        return new RefreshSessionResult($this->execute($command->rawToken, $command->correlationId, $command->ip, $command->userAgent));
    }

    private function execute(string $rawToken, string $correlationId, ?string $ip, ?string $userAgent): array
    {
        if ($rawToken === '') {
            IdentityData::authenticationRequired();
        }
        $hash = OpaqueToken::hash($rawToken);
        $result = $this->transactions->run(function () use ($hash, $correlationId, $ip, $userAgent): array {
            $session = $this->sessionRepository->findByRefreshHashForUpdate($hash);
            if ($session === null) {
                $familyId = $this->sessionRegistry->rotatedFamily($hash);
                if ($familyId !== null) {
                    $this->metrics->increment('auth.refresh_reuse', ['client' => 'BRANCH_PANEL']);
                    $this->sessionLifecycle->revokeFamily($familyId, 'REFRESH_REUSE');
                    $this->audit->write(null, null, 'SECURITY_REFRESH_REUSE_DETECTED', 'TOKEN_FAMILY', $familyId, $correlationId, safeNote: 'Rotated refresh token was reused; family revoked.', ipAddress: $ip, userAgent: $userAgent, sourceClient: 'BRANCH_PANEL');
                    return ['reuse_detected' => true];
                }
                IdentityData::authenticationRequired();
            }
            if ($session->revoked_at !== null || strtotime((string) $session->expires_at) <= $this->clock->now()->getTimestamp()) {
                IdentityData::authenticationRequired();
            }
            $user = $this->users->findById((string) $session->user_id);
            if ($user === null || $user['status'] !== 'ACTIVE') {
                IdentityData::authenticationRequired();
            }
            $rawNext = OpaqueToken::generate();
            $nextHash = OpaqueToken::hash($rawNext);
            $remainingTtl = max(1, strtotime((string) $session->expires_at) - $this->clock->now()->getTimestamp());
            $this->sessionRepository->update($session->session_id, [
                'refresh_token_hash' => $nextHash,
                'rotation_counter' => (int) $session->rotation_counter + 1,
                'rotated_at' => $this->clock->now(),
                'last_seen_at' => $this->clock->now(),
                'ip_address_hash' => IdentityData::fingerprint($ip),
                'user_agent_hash' => IdentityData::fingerprint($userAgent),
                'updated_at' => $this->clock->now(),
            ]);
            $this->sessionRegistry->rememberRotatedToken($hash, (string) $session->token_family_id, $remainingTtl);
            $principal = new AuthenticatedPrincipal((string) $session->user_id, (string) $session->session_id, $session->hq_id === null ? null : (string) $session->hq_id, (bool) $user['must_change_password']);
            $accessToken = $this->accessTokens->issue($principal);
            return [
                'payload' => ['access_token' => $accessToken['token'], 'expires_in' => $accessToken['expires_in']],
                'refresh_token' => $rawNext,
                'refresh_expires_at' => new \DateTimeImmutable((string) $session->expires_at),
            ];
        });
        if (($result['reuse_detected'] ?? false) === true) {
            IdentityData::authenticationRequired();
        }
        return $result;
    }
}
