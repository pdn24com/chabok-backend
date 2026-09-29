<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\AccessTokenServiceInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Contracts\IdentifierGeneratorInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Application\Contracts\SessionIssuerInterface;
use Modules\Iam\Application\Contracts\SessionSettingsInterface;
use Modules\Iam\Application\Dto\IdentityDto;
use Modules\Iam\Application\Dto\IssuedSessionDto;
use Modules\Iam\Application\Dto\SessionCredentialsDto;
use Modules\Iam\Application\Repositories\SessionRepositoryInterface;
use Modules\Iam\Domain\Support\OpaqueToken;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final readonly class SessionIssuer implements SessionIssuerInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
        private SessionSettingsInterface $sessionSettings,
        private AccessTokenServiceInterface $accessTokenService,
        private AuditWriterInterface $auditWriter,
        private AccessContextResolverInterface $accessContextResolver,
        private SessionRepositoryInterface $sessionRepository,
    ) {}

    public function createSession(
        UserRecord $user,
        ?string $deviceId,
        ?string $deviceName,
        string $correlationId,
        ?string $ip,
        ?string $userAgent,
    ): IssuedSessionDto {
        return $this->connection->transaction(function () use ($user, $deviceId, $deviceName, $correlationId, $ip, $userAgent): IssuedSessionDto {
            $raw = OpaqueToken::generate();
            $familyId = $this->identifierGenerator->token();
            $expiresAt = $this->clock->now()->modify(sprintf('%+d seconds', $this->sessionSettings->refreshTtlSeconds()));
            $sessionId = (string) $this->sessionRepository->create([

                'hq_id' => $user->hq_id,
                'user_id' => $user->user_id,
                'token_family_id' => $familyId,
                'refresh_token_hash' => OpaqueToken::hash($raw),
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'ip_address_hash' => IdentityDto::fingerprint($ip),
                'user_agent_hash' => IdentityDto::fingerprint($userAgent),
                'issued_at' => $this->clock->now(),
                'expires_at' => $expiresAt,
                'last_seen_at' => $this->clock->now(),
            ])->getKey();
            $principal = new AuthenticatedPrincipal($user->user_id, $sessionId, $user->hq_id, (bool) $user->must_change_password);
            $token = $this->accessTokenService->issue($principal);
            $this->auditWriter->write($user->hq_id, $user->user_id, 'AUTH_LOGIN', 'SESSION', $sessionId, $correlationId);

            return new IssuedSessionDto(
                $sessionId,
                $user,
                new SessionCredentialsDto($token, $raw, $expiresAt),
                $this->accessContextResolver->resolve($principal),
            );
        }, attempts: 3);
    }
}
