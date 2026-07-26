<?php

declare(strict_types=1);

namespace Modules\Identity\Application;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AccessTokenService;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\SecurityMetricRecorder;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Identity\Domain\OpaqueToken;
use Modules\Identity\Domain\PasswordPolicy;
use Modules\Identity\Application\Contracts\PlatformContextValidator;
use Modules\Identity\Infrastructure\Security\RedisSessionRegistry;
use Modules\User\Application\Contracts\IdentityProvisioner;
use Modules\User\Application\Contracts\UserSessionManager;
use Modules\User\Application\Contracts\UserStore;

final readonly class IdentityService implements IdentityProvisioner, UserSessionManager
{
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$Q2hhYm9rRHVtbXlTYWx0MTIzNA$0iWR0A5c96ac21KlSQNKN1XFZGkVjt0RcQbEcgZ36h0';

    public function __construct(
        private UserStore $users,
        private PasswordPolicy $passwordPolicy,
        private AccessTokenService $accessTokens,
        private RedisSessionRegistry $sessionRegistry,
        private TransactionManager $transactions,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
        private PlatformContextValidator $platformContext,
        private AuthorizationContextResolver $authorizationContext,
        private SecurityMetricRecorder $metrics,
    ) {}

    /** @return array{payload: array<string, mixed>, refresh_token: string, refresh_expires_at: \DateTimeImmutable} */
    public function login(
        string $identifier,
        string $password,
        ?string $deviceId,
        ?string $deviceName,
        string $correlationId,
        ?string $ip,
        ?string $userAgent,
    ): array {
        $user = $this->users->findByIdentifier($identifier);
        $credential = $user === null ? null : DB::table('authentication_credentials')
            ->where('user_id', $user['user_id'])->first();
        $hash = $credential?->password_hash ?? self::DUMMY_HASH;
        $passwordValid = password_verify($password, $hash);

        if ($user === null || $credential === null || ! $passwordValid) {
            $this->metrics->increment('auth.invalid_credentials', ['client' => 'BRANCH_PANEL']);
            throw new ApiException(ApiErrorCode::InvalidCredentials, 401, 'Invalid credentials.');
        }
        if ($user['status'] !== 'ACTIVE') {
            $this->metrics->increment('auth.inactive_rejected', ['client' => 'BRANCH_PANEL']);
            throw new ApiException(ApiErrorCode::Forbidden, 403, 'User is not active.');
        }
        if ($user['hq_id'] === null && ! $this->platformContext->hasActivePlatformAssignment((string) $user['user_id'])) {
            throw new ApiException(ApiErrorCode::Forbidden, 403, 'Access denied.');
        }

        return $this->createSession($user, $deviceId, $deviceName, $correlationId, $ip, $userAgent);
    }

    /** @return array{payload: array<string, mixed>, refresh_token: string, refresh_expires_at: \DateTimeImmutable} */
    public function refresh(
        string $rawToken,
        string $correlationId,
        ?string $ip,
        ?string $userAgent,
    ): array {
        if ($rawToken === '') {
            $this->authenticationRequired();
        }
        $hash = OpaqueToken::hash($rawToken);

        $result = $this->transactions->run(function () use ($hash, $correlationId, $ip, $userAgent): array {
            $session = DB::table('user_sessions')
                ->where('refresh_token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                $familyId = $this->sessionRegistry->rotatedFamily($hash);
                if ($familyId !== null) {
                    $this->metrics->increment('auth.refresh_reuse', ['client' => 'BRANCH_PANEL']);
                    $this->revokeFamily($familyId, 'REFRESH_REUSE');
                    $this->audit->write(
                        null,
                        null,
                        'SECURITY_REFRESH_REUSE_DETECTED',
                        'TOKEN_FAMILY',
                        $familyId,
                        $correlationId,
                        safeNote: 'Rotated refresh token was reused; family revoked.',
                        ipAddress: $ip,
                        userAgent: $userAgent,
                        sourceClient: 'BRANCH_PANEL',
                    );

                    return ['reuse_detected' => true];
                }
                $this->authenticationRequired();
            }

            if ($session->revoked_at !== null || strtotime((string) $session->expires_at) <= time()) {
                $this->authenticationRequired();
            }

            $user = $this->users->findById((string) $session->user_id);
            if ($user === null || $user['status'] !== 'ACTIVE') {
                $this->authenticationRequired();
            }

            $rawNext = OpaqueToken::generate();
            $nextHash = OpaqueToken::hash($rawNext);
            $remainingTtl = max(1, strtotime((string) $session->expires_at) - time());
            DB::table('user_sessions')->where('session_id', $session->session_id)->update([
                'refresh_token_hash' => $nextHash,
                'rotation_counter' => (int) $session->rotation_counter + 1,
                'rotated_at' => now(),
                'last_seen_at' => now(),
                'ip_address_hash' => $this->fingerprint($ip),
                'user_agent_hash' => $this->fingerprint($userAgent),
                'updated_at' => now(),
            ]);
            $this->sessionRegistry->rememberRotatedToken($hash, (string) $session->token_family_id, $remainingTtl);

            $principal = new AuthenticatedPrincipal(
                (string) $session->user_id,
                (string) $session->session_id,
                $session->hq_id === null ? null : (string) $session->hq_id,
                (bool) $user['must_change_password'],
            );

            $accessToken = $this->accessTokens->issue($principal);

            return [
                'payload' => [
                    'access_token' => $accessToken['token'],
                    'expires_in' => $accessToken['expires_in'],
                ],
                'refresh_token' => $rawNext,
                'refresh_expires_at' => new \DateTimeImmutable((string) $session->expires_at),
            ];
        });

        if (($result['reuse_detected'] ?? false) === true) {
            $this->authenticationRequired();
        }

        return $result;
    }

    public function logout(?string $rawToken, string $correlationId): void
    {
        if ($rawToken === null || $rawToken === '') {
            return;
        }
        $this->transactions->run(function () use ($rawToken, $correlationId): void {
            $session = DB::table('user_sessions')
                ->where('refresh_token_hash', OpaqueToken::hash($rawToken))
                ->lockForUpdate()
                ->first();
            if ($session === null) {
                return;
            }
            $this->revokeSession((string) $session->session_id, 'LOGOUT');
            $this->audit->write(
                $session->hq_id === null ? null : (string) $session->hq_id,
                (string) $session->user_id,
                'AUTH_LOGOUT',
                'SESSION',
                (string) $session->session_id,
                $correlationId,
            );
        });
    }

    public function logoutAll(
        AuthenticatedPrincipal $actor,
        ?string $currentPassword,
        ?string $verificationToken,
        string $correlationId,
    ): int {
        if ($currentPassword === null && $verificationToken === null) {
            throw new ApiException(
                ApiErrorCode::ValidationError,
                422,
                'The request is invalid.',
                ['current_password' => ['Exactly one step-up proof is required.']],
            );
        }

        return $this->transactions->run(function () use ($actor, $currentPassword, $verificationToken, $correlationId): int {
            if ($currentPassword !== null) {
                $credential = DB::table('authentication_credentials')
                    ->where('user_id', $actor->userId)->lockForUpdate()->first();
                if ($credential === null || ! password_verify($currentPassword, (string) $credential->password_hash)) {
                    throw new ApiException(ApiErrorCode::InvalidCredentials, 401, 'Invalid credentials.');
                }
            } else {
                $this->consumeVerificationToken((string) $verificationToken, 'PASSWORD_RESET', $actor->userId);
            }

            $count = $this->revokeUserSessions($actor->userId, 'LOGOUT_ALL');
            $this->audit->write($actor->hqId, $actor->userId, 'AUTH_LOGOUT_ALL', 'USER', $actor->userId, $correlationId);

            return $count;
        });
    }

    /** @return array{challenge_id: string, expires_in: int} */
    public function sendOtp(
        string $identifier,
        string $purpose,
        string $correlationId,
        ?string $ip,
    ): array {
        $user = $this->users->findByIdentifier($identifier);
        $this->metrics->increment('auth.otp_requested', ['purpose' => $purpose]);
        $challengeId = (string) Str::uuid();
        $code = (string) random_int(100000, 999999);
        $ttl = 600;
        $destinationFingerprint = hash('sha256', mb_strtolower(trim($identifier)));

        return $this->transactions->run(function () use (
            $challengeId,
            $user,
            $destinationFingerprint,
            $purpose,
            $code,
            $ttl,
            $ip,
            $correlationId,
        ): array {
            DB::table('otp_challenges')->insert([
                'challenge_id' => $challengeId,
                'user_id' => $user['user_id'] ?? null,
                'destination_fingerprint' => $destinationFingerprint,
                'purpose' => $purpose,
                'code_hash' => password_hash($code, PASSWORD_ARGON2ID),
                'expires_at' => now()->addSeconds($ttl),
                'remaining_attempts' => 5,
                'status' => 'PENDING',
                'request_ip_hash' => $this->fingerprint($ip),
                'created_at' => now(),
            ]);

            if ($user !== null) {
                $this->outbox->write(
                    $user['hq_id'],
                    'OTP_CHALLENGE',
                    $challengeId,
                    'identity.otp.delivery.requested',
                    $correlationId,
                    [
                        'challenge_id' => $challengeId,
                        'purpose' => $purpose,
                        'delivery_ciphertext' => Crypt::encryptString($code),
                        'destination_fingerprint' => $destinationFingerprint,
                    ],
                );
            } else {
                // Deliberately comparable cryptographic work for unknown identifiers.
                Crypt::encryptString($code);
            }

            return ['challenge_id' => $challengeId, 'expires_in' => $ttl];
        });
    }

    /** @return array{verification_token: string, expires_in: int} */
    public function verifyOtp(string $challengeId, string $code): array
    {
        $result = $this->transactions->run(function () use ($challengeId, $code): array {
            $challenge = DB::table('otp_challenges')
                ->where('challenge_id', $challengeId)
                ->lockForUpdate()
                ->first();
            if (
                $challenge === null
                || $challenge->status !== 'PENDING'
                || strtotime((string) $challenge->expires_at) <= time()
                || ! password_verify($code, (string) $challenge->code_hash)
            ) {
                if ($challenge !== null && $challenge->status === 'PENDING') {
                    if (strtotime((string) $challenge->expires_at) <= time()) {
                        DB::table('otp_challenges')->where('challenge_id', $challengeId)->update([
                            'status' => 'EXPIRED',
                        ]);
                    } else {
                        $attempts = max(0, (int) $challenge->remaining_attempts - 1);
                        DB::table('otp_challenges')->where('challenge_id', $challengeId)->update([
                            'remaining_attempts' => $attempts,
                            'status' => $attempts === 0 ? 'LOCKED' : 'PENDING',
                        ]);
                    }
                }

                return ['invalid' => true];
            }
            if ($challenge->user_id === null) {
                return ['invalid' => true];
            }

            $raw = OpaqueToken::generate();
            $ttl = 600;
            DB::table('otp_challenges')->where('challenge_id', $challengeId)->update([
                'verification_token_hash' => OpaqueToken::hash($raw),
                'status' => 'VERIFIED',
                'verified_at' => now(),
                'expires_at' => now()->addSeconds($ttl),
            ]);

            return ['verification_token' => $raw, 'expires_in' => $ttl];
        });

        if (($result['invalid'] ?? false) === true) {
            throw new ApiException(
                ApiErrorCode::ValidationError,
                422,
                'Verification code is invalid.',
            );
        }

        return $result;
    }

    public function activatePassword(
        ?string $verificationToken,
        ?string $invitationToken,
        string $newPassword,
        string $correlationId,
    ): string {
        if (($verificationToken === null) === ($invitationToken === null)) {
            throw new ApiException(
                ApiErrorCode::ValidationError,
                422,
                'The request is invalid.',
                ['verification_token' => ['Exactly one activation proof is required.']],
            );
        }
        $this->passwordPolicy->assertValid($newPassword);

        $result = $this->transactions->run(function () use ($verificationToken, $invitationToken, $newPassword, $correlationId): string|array {
            if ($verificationToken !== null) {
                $userId = $this->consumeVerificationToken($verificationToken, 'ACTIVATION');
            } else {
                $hash = OpaqueToken::hash((string) $invitationToken);
                $invitation = DB::table('user_invitations')->where('token_hash', $hash)->lockForUpdate()->first();
                if ($invitation === null || $invitation->status !== 'PENDING') {
                    return ['invalid_proof' => true];
                }
                if (strtotime((string) $invitation->expires_at) <= time()) {
                    DB::table('user_invitations')->where('invitation_id', $invitation->invitation_id)->update([
                        'status' => 'EXPIRED', 'updated_at' => now(),
                    ]);

                    return ['invalid_proof' => true];
                }
                $userId = (string) $invitation->user_id;
                DB::table('user_invitations')->where('invitation_id', $invitation->invitation_id)->update([
                    'status' => 'ACCEPTED', 'accepted_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->upsertPassword($userId, $newPassword);
            DB::table('users')->where('user_id', $userId)->update([
                'status' => 'ACTIVE', 'must_change_password' => false,
                'activated_at' => now(), 'updated_at' => now(),
            ]);
            $user = $this->users->findById($userId);
            $this->audit->write($user['hq_id'] ?? null, $userId, 'USER_ACTIVATED', 'USER', $userId, $correlationId);

            return $userId;
        });

        if (is_array($result)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Activation proof is invalid.');
        }

        return $result;
    }

    /** @return array{user_id: string, revoked_session_count: int} */
    public function resetPassword(string $verificationToken, string $newPassword, string $correlationId): array
    {
        $this->passwordPolicy->assertValid($newPassword);

        return $this->transactions->run(function () use ($verificationToken, $newPassword, $correlationId): array {
            $userId = $this->consumeVerificationToken($verificationToken, 'PASSWORD_RESET');
            $this->upsertPassword($userId, $newPassword);
            DB::table('users')->where('user_id', $userId)->update([
                'must_change_password' => false, 'updated_at' => now(),
            ]);
            $revoked = $this->revokeUserSessions($userId, 'PASSWORD_RESET');
            $user = $this->users->findById($userId);
            $this->audit->write($user['hq_id'] ?? null, $userId, 'PASSWORD_RESET', 'USER', $userId, $correlationId);

            return ['user_id' => $userId, 'revoked_session_count' => $revoked];
        });
    }

    public function changePassword(
        AuthenticatedPrincipal $actor,
        string $currentPassword,
        string $newPassword,
        string $correlationId,
    ): void {
        $this->passwordPolicy->assertValid($newPassword);
        $credential = DB::table('authentication_credentials')->where('user_id', $actor->userId)->first();
        if ($credential === null || ! password_verify($currentPassword, (string) $credential->password_hash)) {
            throw new ApiException(ApiErrorCode::InvalidCredentials, 401, 'Invalid credentials.');
        }

        $this->transactions->run(function () use ($actor, $newPassword, $correlationId): void {
            $this->upsertPassword($actor->userId, $newPassword);
            DB::table('users')->where('user_id', $actor->userId)->update([
                'must_change_password' => false, 'updated_at' => now(),
            ]);
            DB::table('user_sessions')->where('user_id', $actor->userId)
                ->where('session_id', '<>', $actor->sessionId)
                ->whereNull('revoked_at')
                ->get()->each(fn ($session) => $this->revokeSession((string) $session->session_id, 'PASSWORD_CHANGED'));
            $this->audit->write($actor->hqId, $actor->userId, 'PASSWORD_CHANGED', 'USER', $actor->userId, $correlationId);
        });
    }

    public function provisionPassword(string $userId, string $password): void
    {
        $this->passwordPolicy->assertValid($password);
        $this->upsertPassword($userId, $password);
    }

    public function createInvitation(array $user, string $channel, ?string $actorId, string $correlationId): void
    {
        $recipient = $channel === 'SMS' ? ($user['normalized_mobile'] ?? null) : ($user['normalized_email'] ?? null);
        if (! is_string($recipient) || $recipient === '') {
            throw new ApiException(
                ApiErrorCode::ValidationError,
                422,
                'The request is invalid.',
                [strtolower($channel) => ['The requested invitation channel is unavailable.']],
            );
        }

        $this->transactions->run(function () use ($user, $channel, $actorId, $correlationId, $recipient): void {
            DB::table('users')->where('user_id', $user['user_id'])->lockForUpdate()->first();
            DB::table('user_invitations')->where('user_id', $user['user_id'])
                ->where('channel', $channel)->where('status', 'PENDING')
                ->update(['status' => 'SUPERSEDED', 'updated_at' => now()]);

            $raw = OpaqueToken::generate();
            $invitationId = (string) Str::uuid();
            DB::table('user_invitations')->insert([
                'invitation_id' => $invitationId,
                'hq_id' => $user['hq_id'],
                'user_id' => $user['user_id'],
                'channel' => $channel,
                'normalized_recipient' => $recipient,
                'token_hash' => OpaqueToken::hash($raw),
                'status' => 'PENDING',
                'sent_at' => now(),
                'expires_at' => now()->addDays(2),
                'created_by' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->outbox->write(
                $user['hq_id'],
                'USER_INVITATION',
                $invitationId,
                'identity.invitation.delivery.requested',
                $correlationId,
                [
                    'invitation_id' => $invitationId,
                    'channel' => $channel,
                    'recipient_fingerprint' => hash('sha256', $recipient),
                    'delivery_ciphertext' => Crypt::encryptString($raw),
                ],
            );
        });
    }

    public function revokeUserSessions(string $userId, string $reason): int
    {
        $sessions = DB::table('user_sessions')->where('user_id', $userId)->whereNull('revoked_at')->get();
        foreach ($sessions as $session) {
            $this->revokeSession((string) $session->session_id, $reason);
        }

        return $sessions->count();
    }

    /** @return list<array<string, mixed>> */
    public function listSessions(string $userId): array
    {
        return DB::table('user_sessions')->where('user_id', $userId)->orderByDesc('issued_at')->get()
            ->map(fn ($row): array => [
                'session_id' => $row->session_id,
                'device_id' => $row->device_id,
                'device_name' => $row->device_name,
                'issued_at' => $this->iso((string) $row->issued_at),
                'expires_at' => $this->iso((string) $row->expires_at),
                'last_seen_at' => $this->iso((string) ($row->last_seen_at ?? $row->issued_at)),
                'revoked' => $row->revoked_at !== null,
            ])->all();
    }

    public function revokeOwnedSession(
        AuthenticatedPrincipal $actor,
        string $sessionId,
        string $correlationId,
    ): void
    {
        $this->transactions->run(function () use ($actor, $sessionId, $correlationId): void {
            $session = DB::table('user_sessions')->where('session_id', $sessionId)
                ->where('user_id', $actor->userId)->lockForUpdate()->first();
            if ($session === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            $this->revokeSession($sessionId, 'USER_REVOKED');
            $this->audit->write(
                $actor->hqId,
                $actor->userId,
                'SESSION_REVOKED',
                'SESSION',
                $sessionId,
                $correlationId,
            );
        });
    }

    /** @param array<string, mixed> $user */
    private function createSession(
        array $user,
        ?string $deviceId,
        ?string $deviceName,
        string $correlationId,
        ?string $ip,
        ?string $userAgent,
    ): array {
        return $this->transactions->run(function () use ($user, $deviceId, $deviceName, $correlationId, $ip, $userAgent): array {
            $raw = OpaqueToken::generate();
            $sessionId = (string) Str::uuid();
            $familyId = (string) Str::uuid();
            $expiresAt = now()->addSeconds((int) config('chabok.refresh_cookie.ttl_seconds'));
            DB::table('user_sessions')->insert([
                'session_id' => $sessionId,
                'hq_id' => $user['hq_id'],
                'user_id' => $user['user_id'],
                'token_family_id' => $familyId,
                'refresh_token_hash' => OpaqueToken::hash($raw),
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'ip_address_hash' => $this->fingerprint($ip),
                'user_agent_hash' => $this->fingerprint($userAgent),
                'issued_at' => now(),
                'expires_at' => $expiresAt,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $principal = new AuthenticatedPrincipal(
                $user['user_id'],
                $sessionId,
                $user['hq_id'],
                (bool) $user['must_change_password'],
            );
            $token = $this->accessTokens->issue($principal);
            $this->audit->write($user['hq_id'], $user['user_id'], 'AUTH_LOGIN', 'SESSION', $sessionId, $correlationId);

            return [
                'payload' => [
                    'access_token' => $token['token'],
                    'expires_in' => $token['expires_in'],
                    'session_id' => $sessionId,
                    'must_change_password' => (bool) $user['must_change_password'],
                    'user' => $this->publicUser($user),
                    'context' => $this->authorizationContext->resolve($principal),
                ],
                'refresh_token' => $raw,
                'refresh_expires_at' => \DateTimeImmutable::createFromMutable($expiresAt),
            ];
        });
    }

    private function consumeVerificationToken(string $raw, string $purpose, ?string $expectedUserId = null): string
    {
        $row = DB::table('otp_challenges')
            ->where('verification_token_hash', OpaqueToken::hash($raw))
            ->where('purpose', $purpose)
            ->lockForUpdate()
            ->first();
        if (
            $row === null || $row->status !== 'VERIFIED'
            || strtotime((string) $row->expires_at) <= time()
            || ($expectedUserId !== null && $row->user_id !== $expectedUserId)
        ) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Verification proof is invalid.');
        }
        DB::table('otp_challenges')->where('challenge_id', $row->challenge_id)->update([
            'status' => 'CONSUMED', 'consumed_at' => now(),
        ]);

        return (string) $row->user_id;
    }

    private function upsertPassword(string $userId, string $password): void
    {
        DB::table('authentication_credentials')->upsert([[
            'credential_id' => (string) Str::uuid(),
            'user_id' => $userId,
            'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            'algorithm' => 'argon2id',
            'algorithm_version' => 1,
            'password_changed_at' => now(),
            'failed_attempt_count' => 0,
            'locked_until' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['user_id'], [
            'password_hash', 'algorithm', 'algorithm_version', 'password_changed_at',
            'failed_attempt_count', 'locked_until', 'updated_at',
        ]);
    }

    private function revokeSession(string $sessionId, string $reason): void
    {
        DB::table('user_sessions')->where('session_id', $sessionId)->whereNull('revoked_at')->update([
            'revoked_at' => now(), 'revoked_reason' => $reason, 'updated_at' => now(),
        ]);
        $this->sessionRegistry->invalidate($sessionId);
    }

    private function revokeFamily(string $familyId, string $reason): void
    {
        $sessions = DB::table('user_sessions')->where('token_family_id', $familyId)->get();
        foreach ($sessions as $session) {
            $this->revokeSession((string) $session->session_id, $reason);
        }
    }

    private function authenticationRequired(): never
    {
        throw new ApiException(ApiErrorCode::AuthenticationRequired, 401, 'Authentication required.');
    }

    private function fingerprint(?string $value): ?string
    {
        return $value === null || $value === '' ? null : hash('sha256', $value);
    }

    /** @param array<string, mixed> $user */
    public function publicUser(array $user): array
    {
        $public = array_intersect_key($user, array_flip([
            'user_id', 'hq_id', 'username', 'mobile', 'email', 'first_name',
            'last_name', 'display_name', 'status', 'must_change_password',
        ]));
        if (array_key_exists('must_change_password', $public)) {
            $public['must_change_password'] = (bool) $public['must_change_password'];
        }

        return $public;
    }

    private function iso(string $value): string
    {
        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format(DATE_ATOM);
    }
}
