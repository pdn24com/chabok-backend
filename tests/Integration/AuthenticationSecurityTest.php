<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\Crypt;
use Modules\Foundation\Application\Contracts\AccessTokenServiceInterface;
use Modules\Foundation\Application\Ports\AccessSessionValidatorInterface;
use Modules\Iam\Domain\Support\OpaqueToken;
use Tests\Support\RecordFixtureQuery;

final class AuthenticationSecurityTest extends MySqlRedisTestCase
{
    public function test_unknown_user_and_wrong_password_are_externally_equivalent(): void
    {
        $tenant = $this->tenant();
        $this->user($tenant['hq_id'], 'known-user');
        $headers = [
            'Origin' => 'http://localhost:5173',
            'X-Correlation-ID' => '1025467437',
        ];

        $wrong = $this->withHeaders($headers)->postJson('/api/v1/auth/login', [
            'identifier' => 'known-user', 'password' => 'wrong',
        ]);
        $unknown = $this->withHeaders($headers)->postJson('/api/v1/auth/login', [
            'identifier' => 'unknown-user', 'password' => 'wrong',
        ]);

        $wrong->assertStatus(401)->assertJsonPath('error_code', 'INVALID_CREDENTIALS');
        $unknown->assertStatus(401)->assertExactJson($wrong->json());
    }

    public function test_login_and_rotation_never_expose_refresh_token_and_reuse_revokes_family(): void
    {
        $tenant = $this->tenant();
        $this->user($tenant['hq_id'], 'rotate-user');
        $login = $this->login('rotate-user');

        $login['response']->assertJsonMissing(['refresh_token'])
            ->assertJsonPath('data.expires_in', 300)
            ->assertCookie((string) config('chabok.refresh_cookie.name'));
        $cookie = $login['response']->getCookie((string) config('chabok.refresh_cookie.name'), false);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
        $this->assertSame('/api/v1/auth', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
        $this->assertEqualsWithDelta(
            time() + 2_592_000,
            $cookie->getExpiresTime(),
            5,
        );
        $this->assertDatabaseHasPublic('user_sessions', [
            'refresh_token_hash' => hash('sha256', $login['cookie']),
        ]);

        $rotated = $this->withCredentials()
            ->withUnencryptedCookie((string) config('chabok.refresh_cookie.name'), $login['cookie'])
            ->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/refresh');
        $rotated->assertOk()->assertJsonMissing(['refresh_token']);
        $nextCookie = (string) $rotated->getCookie((string) config('chabok.refresh_cookie.name'), false)->getValue();
        $this->assertNotSame($login['cookie'], $nextCookie);

        $reuse = $this->withCredentials()
            ->withUnencryptedCookie((string) config('chabok.refresh_cookie.name'), $login['cookie'])
            ->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/refresh');
        $reuse->assertStatus(401)->assertJsonPath('error_code', 'AUTHENTICATION_REQUIRED');

        $family = $this->withCredentials()
            ->withUnencryptedCookie((string) config('chabok.refresh_cookie.name'), $nextCookie)
            ->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/refresh');
        $family->assertStatus(401)->assertJsonPath('error_code', 'AUTHENTICATION_REQUIRED');
        $this->assertDatabaseHasPublic('audit_events', ['action_key' => 'SECURITY_REFRESH_REUSE_DETECTED']);
        $this->assertStringNotContainsString($login['cookie'], (string) RecordFixtureQuery::table('audit_events')->value('safe_note'));
    }

    public function test_three_refresh_rotations_keep_the_session_active_and_replace_access_tokens(): void
    {
        $tenant = $this->tenant();
        $this->user($tenant['hq_id'], 'multi-cycle-user');
        $login = $this->login('multi-cycle-user');
        $cookieName = (string) config('chabok.refresh_cookie.name');
        $cookie = $login['cookie'];
        $accessToken = $login['token'];

        for ($cycle = 1; $cycle <= 3; $cycle++) {
            $response = $this->withCredentials()
                ->withUnencryptedCookie($cookieName, $cookie)
                ->withHeader('Origin', 'http://localhost:5173')
                ->postJson('/api/v1/auth/refresh');

            $response->assertOk()
                ->assertJsonPath('data.expires_in', 300)
                ->assertJsonMissing(['refresh_token']);
            $nextAccessToken = (string) $response->json('data.access_token');
            $nextCookie = (string) $response->getCookie($cookieName, false)->getValue();
            $this->assertNotSame($accessToken, $nextAccessToken);
            $this->assertNotSame($cookie, $nextCookie);
            $accessToken = $nextAccessToken;
            $cookie = $nextCookie;
        }

        $this->assertDatabaseHasPublic('user_sessions', [
            'session_id' => $login['response']->json('data.session_id'),
            'rotation_counter' => 3,
            'revoked_at' => null,
        ]);
        $this->withToken($accessToken)->getJson('/api/v1/me')->assertOk();
    }

    public function test_logout_rejects_foreign_origin_and_valid_logout_clears_cookie_without_access_token(): void
    {
        $tenant = $this->tenant();
        $this->user($tenant['hq_id'], 'logout-user');
        $login = $this->login('logout-user');
        $name = (string) config('chabok.refresh_cookie.name');

        $foreign = $this->withCredentials()->withUnencryptedCookie($name, $login['cookie'])
            ->withHeader('Origin', 'https://evil.example')
            ->postJson('/api/v1/auth/logout');
        $foreign->assertStatus(403)->assertJsonPath('error_code', 'ORIGIN_NOT_ALLOWED');
        $this->assertNull($foreign->getCookie($name, false));

        $valid = $this->withCredentials()->withUnencryptedCookie($name, $login['cookie'])
            ->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/logout');
        $valid->assertOk();
        $cleared = $valid->getCookie($name, false);
        $this->assertNotNull($cleared);
        $this->assertLessThan(time(), $cleared->getExpiresTime());
        $this->assertSame('/api/v1/auth', $cleared->getPath());

        $this->withCredentials()->withUnencryptedCookie($name, $login['cookie'])
            ->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/refresh')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'AUTHENTICATION_REQUIRED');
        $this->assertDatabaseHasPublic('user_sessions', [
            'session_id' => $login['response']->json('data.session_id'),
            'revoked_reason' => 'LOGOUT',
        ]);
    }

    public function test_logout_all_accepts_valid_refresh_session_rejects_foreign_origin_and_clears_cookie(): void
    {
        $tenant = $this->tenant();
        $this->user($tenant['hq_id'], 'logout-all-user');
        $login = $this->login('logout-all-user');
        $name = (string) config('chabok.refresh_cookie.name');

        $this->withCredentials()->withUnencryptedCookie($name, $login['cookie'])
            ->withHeader('Origin', 'https://evil.example')
            ->postJson('/api/v1/auth/logout-all', ['current_password' => 'Strong!Pass123'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ORIGIN_NOT_ALLOWED');

        $response = $this->withCredentials()->withUnencryptedCookie($name, $login['cookie'])
            ->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/logout-all', ['current_password' => 'Strong!Pass123']);
        $response->assertOk()->assertJsonPath('data.revoked_session_count', 1);
        $cleared = $response->getCookie($name, false);
        $this->assertNotNull($cleared);
        $this->assertLessThan(time(), $cleared->getExpiresTime());
    }

    public function test_forced_password_blocks_other_routes_and_change_clears_state(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant['hq_id'], 'forced-user', mustChange: true);
        $login = $this->login('forced-user');
        $claims = $this->app->make(AccessTokenServiceInterface::class)
            ->decode($login['token']);
        $principal = $this->app->make(AccessSessionValidatorInterface::class)
            ->validate($claims);
        $this->assertTrue($principal->mustChangePassword);

        $this->withToken($login['token'])->getJson('/api/v1/me')
            ->assertStatus(403)->assertJsonPath('error_code', 'PASSWORD_CHANGE_REQUIRED');

        $this->withToken($login['token'])->postJson('/api/v1/auth/password/change', [
            'current_password' => 'Strong!Pass123',
            'new_password' => 'New!StrongPass456',
        ])->assertOk();

        $this->assertDatabaseHasPublic('users', [
            'user_id' => $user['user_id'], 'must_change_password' => false,
        ]);
        $this->withToken($login['token'])->getJson('/api/v1/me')->assertOk();
    }

    public function test_known_and_unknown_recovery_requests_are_safe_and_comparable(): void
    {
        $tenant = $this->tenant();
        $this->user($tenant['hq_id'], 'recover-user');
        $headers = ['X-Correlation-ID' => '129434130'];

        $known = $this->withHeaders($headers)->postJson('/api/v1/auth/otp/send', [
            'identifier' => 'recover-user', 'purpose' => 'PASSWORD_RESET',
        ]);
        $unknown = $this->withHeaders($headers)->postJson('/api/v1/auth/otp/send', [
            'identifier' => 'missing-user', 'purpose' => 'PASSWORD_RESET',
        ]);

        $known->assertOk()->assertJsonStructure(['data' => ['challenge_id', 'expires_in'], 'meta', 'correlation_id']);
        $unknown->assertOk()->assertJsonStructure(['data' => ['challenge_id', 'expires_in'], 'meta', 'correlation_id']);
        $this->assertSame($known->json('data.expires_in'), $unknown->json('data.expires_in'));
        $this->assertDatabaseHasPublic('otp_challenges', ['challenge_id' => $known->json('data.challenge_id')]);
        $this->assertDatabaseHasPublic('otp_challenges', ['challenge_id' => $unknown->json('data.challenge_id'), 'user_id' => null]);
        $this->assertSame(2, RecordFixtureQuery::table('otp_challenges')->count());
        $this->assertSame(1, RecordFixtureQuery::table('outbox_events')->where('event_type', 'identity.otp.delivery.requested')->count());
    }

    public function test_otp_verification_and_password_reset_revoke_sessions(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant['hq_id'], 'otp-user');
        $login = $this->login('otp-user');
        $sent = $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => 'otp-user', 'purpose' => 'PASSWORD_RESET',
        ])->assertOk();
        $payload = json_decode((string) RecordFixtureQuery::table('outbox_events')
            ->where('aggregate_id', $sent->json('data.challenge_id'))->value('payload'), true);
        $code = Crypt::decryptString($payload['delivery_ciphertext']);

        $verified = $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_id' => $sent->json('data.challenge_id'), 'code' => $code,
        ])->assertOk();
        $this->postJson('/api/v1/auth/password/reset', [
            'verification_token' => $verified->json('data.verification_token'),
            'new_password' => 'Reset!StrongPass789',
        ])->assertOk();

        $this->assertDatabaseHasPublic('user_sessions', [
            'user_id' => $user['user_id'], 'revoked_reason' => 'PASSWORD_RESET',
        ]);
        $this->withToken($login['token'])->getJson('/api/v1/me')
            ->assertStatus(401)->assertJsonPath('error_code', 'AUTHENTICATION_REQUIRED');
    }

    public function test_invalid_otp_uses_contract_status_and_commits_attempt_decrement(): void
    {
        $tenant = $this->tenant();
        $this->user($tenant['hq_id'], 'otp-attempt-user');
        $sent = $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => 'otp-attempt-user',
            'purpose' => 'PASSWORD_RESET',
        ])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_id' => $sent->json('data.challenge_id'),
            'code' => '000000',
        ])->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR');

        $this->assertDatabaseHasPublic('otp_challenges', [
            'challenge_id' => $sent->json('data.challenge_id'),
            'remaining_attempts' => 4,
            'status' => 'PENDING',
        ]);
    }

    public function test_password_activation_requires_exactly_one_proof(): void
    {
        $none = $this->postJson('/api/v1/auth/password/activate', [
            'new_password' => 'Activate!Pass123',
        ]);
        $both = $this->postJson('/api/v1/auth/password/activate', [
            'verification_token' => 'one',
            'invitation_token' => 'two',
            'new_password' => 'Activate!Pass123',
        ]);

        $none->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR');
        $both->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    public function test_expired_invitation_is_rejected_and_marked_expired(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant['hq_id'], 'expired-invite-user', status: 'INVITED');
        RecordFixtureQuery::table('authentication_credentials')->where('user_id', $user['user_id'])->delete();
        $raw = OpaqueToken::generate();
        $invitationId = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('user_invitations')->insert([
            'invitation_id' => $invitationId,
            'hq_id' => $tenant['hq_id'],
            'user_id' => $user['user_id'],
            'channel' => 'EMAIL',
            'normalized_recipient' => 'expired@example.com',
            'token_hash' => OpaqueToken::hash($raw),
            'status' => 'PENDING',
            'sent_at' => now()->subDays(3),
            'expires_at' => now()->subDay(),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        $this->postJson('/api/v1/auth/password/activate', [
            'invitation_token' => $raw,
            'new_password' => 'Activated!Pass456',
        ])->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR');

        $this->assertDatabaseHasPublic('user_invitations', [
            'invitation_id' => $invitationId,
            'status' => 'EXPIRED',
        ]);
        $this->assertDatabaseHasPublic('users', [
            'user_id' => $user['user_id'],
            'status' => 'INVITED',
        ]);
    }

    public function test_unvalidated_platform_context_is_fail_closed_until_s0_04(): void
    {
        $userId = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('users')->insert([
            'user_id' => $userId,
            'hq_id' => null,
            'username' => 'platform-user',
            'normalized_username' => 'platform-user',
            'first_name' => 'Platform',
            'last_name' => 'User',
            'display_name' => 'Platform User',
            'status' => 'ACTIVE',
            'must_change_password' => false,
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        RecordFixtureQuery::table('authentication_credentials')->insert([
            'credential_id' => (string) random_int(1, 2000000000),
            'user_id' => $userId,
            'password_hash' => password_hash('Strong!Pass123', PASSWORD_ARGON2ID),
            'password_changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/login', [
                'identifier' => 'platform-user',
                'password' => 'Strong!Pass123',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'FORBIDDEN');
        $this->assertDatabaseCount('user_sessions', 0);
    }

    public function test_node_selection_without_switch_permission_is_denied_before_scope(): void
    {
        $tenant = $this->tenant();
        $this->user($tenant['hq_id'], 'node-user');
        $login = $this->login('node-user');

        $this->withToken($login['token'])
            ->withHeader('X-Node-Id', (string) random_int(1, 2000000000))
            ->getJson('/api/v1/me')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'PERMISSION_DENIED');
    }

    public function test_login_rate_limit_is_enforced_by_redis(): void
    {
        for ($attempt = 1; $attempt <= 11; $attempt++) {
            $response = $this->withHeader('Origin', 'http://localhost:5173')
                ->postJson('/api/v1/auth/login', [
                    'identifier' => 'rate-limited-login',
                    'password' => 'wrong',
                ]);
        }

        $response->assertStatus(429)->assertJsonPath('error_code', 'RATE_LIMITED');
    }

    public function test_recovery_otp_send_rate_limit_is_enforced_by_redis(): void
    {
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $response = $this->postJson('/api/v1/auth/otp/send', [
                'identifier' => 'rate-limited-recovery',
                'purpose' => 'PASSWORD_RESET',
            ]);
        }

        $response->assertStatus(429)->assertJsonPath('error_code', 'RATE_LIMITED');
    }

    public function test_refresh_rate_limit_is_enforced_by_redis(): void
    {
        for ($attempt = 1; $attempt <= 31; $attempt++) {
            $response = $this->withHeader('Origin', 'http://localhost:5173')
                ->postJson('/api/v1/auth/refresh');
        }

        $response->assertStatus(429)->assertJsonPath('error_code', 'RATE_LIMITED');
    }

    public function test_password_flow_rate_limit_is_enforced_by_redis(): void
    {
        for ($attempt = 1; $attempt <= 11; $attempt++) {
            $response = $this->postJson('/api/v1/auth/password/activate', [
                'new_password' => 'Valid!Password123',
            ]);
        }

        $response->assertStatus(429)->assertJsonPath('error_code', 'RATE_LIMITED');
    }
}
