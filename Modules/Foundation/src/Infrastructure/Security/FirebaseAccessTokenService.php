<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AccessTokenService;
use Modules\Foundation\Domain\AccessTokenClaims;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Throwable;

final class FirebaseAccessTokenService implements AccessTokenService
{
    public function issue(AuthenticatedPrincipal $principal): array
    {
        $now = time();
        $ttl = max(60, (int) config('chabok.access_token.ttl_seconds'));
        $expiresAt = $now + $ttl;

        $payload = [
            'iss' => (string) config('chabok.access_token.issuer'),
            'aud' => (string) config('chabok.access_token.audience'),
            'sub' => $principal->userId,
            'sid' => $principal->sessionId,
            'hq_id' => $principal->hqId,
            'must_change_password' => $principal->mustChangePassword,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $expiresAt,
            'jti' => (string) Str::uuid(),
        ];

        return [
            'token' => JWT::encode($payload, $this->secret(), 'HS256'),
            'expires_in' => $ttl,
        ];
    }

    public function decode(string $token): AccessTokenClaims
    {
        try {
            $claims = (array) JWT::decode($token, new Key($this->secret(), 'HS256'));

            if (
                ($claims['iss'] ?? null) !== config('chabok.access_token.issuer') ||
                ($claims['aud'] ?? null) !== config('chabok.access_token.audience')
            ) {
                throw new ApiException(
                    ApiErrorCode::AuthenticationRequired,
                    401,
                    'Authentication required.',
                );
            }

            return new AccessTokenClaims(
                userId: (string) ($claims['sub'] ?? ''),
                sessionId: (string) ($claims['sid'] ?? ''),
                hqId: isset($claims['hq_id']) ? (string) $claims['hq_id'] : null,
                mustChangePassword: (bool) ($claims['must_change_password'] ?? false),
                tokenId: (string) ($claims['jti'] ?? ''),
                expiresAt: (int) ($claims['exp'] ?? 0),
            );
        } catch (ApiException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ApiException(
                ApiErrorCode::AuthenticationRequired,
                401,
                'Authentication required.',
            );
        }
    }

    private function secret(): string
    {
        $secret = (string) config('chabok.access_token.secret');

        if (strlen($secret) < 32) {
            throw new \LogicException('CHABOK_JWT_SECRET must contain at least 32 bytes.');
        }

        return $secret;
    }
}
