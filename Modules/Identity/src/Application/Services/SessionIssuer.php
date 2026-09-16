<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Services;

use Modules\Foundation\Application\Contracts\AccessTokenService;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\IdentifierGenerator;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Identity\Application\Contracts\SessionSettings;
use Modules\Identity\Application\Data\IdentityData;
use Modules\Identity\Application\Repositories\SessionRepository;
use Modules\Identity\Domain\OpaqueToken;

final readonly class SessionIssuer
{
    public function __construct(
        private TransactionManager $transactions,
        private IdentifierGenerator $ids,
        private Clock $clock,
        private SessionSettings $settings,
        private SessionRepository $sessionRepository,
        private AccessTokenService $accessTokens,
        private AuditWriter $audit,
        private AuthorizationContextResolver $authorizationContext,
    )
    {
    }

    public function createSession(
        array $user,
        ?string $deviceId,
        ?string $deviceName,
        string $correlationId,
        ?string $ip,
        ?string $userAgent,
    ): array
    {
        return $this->transactions->run(function () use ($user, $deviceId, $deviceName, $correlationId, $ip, $userAgent): array {
            $raw = OpaqueToken::generate();
            $sessionId = $this->ids->uuid();
            $familyId = $this->ids->uuid();
            $expiresAt = $this->clock->now()->modify(sprintf('%+d seconds', $this->settings->refreshTtlSeconds()));
            $this->sessionRepository->insert([
                'session_id' => $sessionId,
                'hq_id' => $user['hq_id'],
                'user_id' => $user['user_id'],
                'token_family_id' => $familyId,
                'refresh_token_hash' => OpaqueToken::hash($raw),
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'ip_address_hash' => IdentityData::fingerprint($ip),
                'user_agent_hash' => IdentityData::fingerprint($userAgent),
                'issued_at' => $this->clock->now(),
                'expires_at' => $expiresAt,
                'last_seen_at' => $this->clock->now(),
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $principal = new AuthenticatedPrincipal($user['user_id'], $sessionId, $user['hq_id'], (bool) $user['must_change_password']);
            $token = $this->accessTokens->issue($principal);
            $this->audit->write($user['hq_id'], $user['user_id'], 'AUTH_LOGIN', 'SESSION', $sessionId, $correlationId);
            return [
                'payload' => [
                    'access_token' => $token['token'],
                    'expires_in' => $token['expires_in'],
                    'session_id' => $sessionId,
                    'must_change_password' => (bool) $user['must_change_password'],
                    'user' => IdentityData::publicUser($user),
                    'context' => $this->authorizationContext->resolve($principal),
                ],
                'refresh_token' => $raw,
                'refresh_expires_at' => $expiresAt,
            ];
        });
    }
}
