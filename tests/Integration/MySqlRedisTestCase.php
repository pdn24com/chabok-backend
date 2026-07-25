<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class MySqlRedisTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        Redis::connection('cache')->flushdb();
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertTrue(Redis::connection()->ping());
    }

    /** @return array<string, mixed> */
    protected function tenant(string $code = 'HQ-1'): array
    {
        $row = [
            'hq_id' => (string) Str::uuid(),
            'hq_code' => $code,
            'hq_title' => "Tenant {$code}",
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('hq_tenants')->insert($row);

        return $row;
    }

    /** @return array<string, mixed> */
    protected function user(
        string $hqId,
        string $identifier,
        string $password = 'Strong!Pass123',
        bool $mustChange = false,
        string $status = 'ACTIVE',
    ): array {
        $row = [
            'user_id' => (string) Str::uuid(),
            'hq_id' => $hqId,
            'username' => $identifier,
            'normalized_username' => mb_strtolower($identifier),
            'mobile' => null,
            'normalized_mobile' => null,
            'email' => null,
            'normalized_email' => null,
            'first_name' => 'Test',
            'last_name' => 'User',
            'display_name' => 'Test User',
            'status' => $status,
            'must_change_password' => $mustChange,
            'created_by' => null,
            'activated_at' => $status === 'ACTIVE' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('users')->insert($row);
        DB::table('authentication_credentials')->insert([
            'credential_id' => (string) Str::uuid(),
            'user_id' => $row['user_id'],
            'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            'algorithm' => 'argon2id',
            'algorithm_version' => 1,
            'password_changed_at' => now(),
            'failed_attempt_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $row;
    }

    /** @return array{response: \Illuminate\Testing\TestResponse, cookie: string, token: string} */
    protected function login(string $identifier, string $password = 'Strong!Pass123'): array
    {
        $response = $this->withHeader('Origin', 'http://localhost:5173')
            ->withHeader('X-Correlation-ID', '11111111-1111-4111-8111-111111111111')
            ->postJson('/api/v1/auth/login', [
                'identifier' => $identifier,
                'password' => $password,
                'client_type' => 'BRANCH_PANEL',
            ]);
        $response->assertOk();
        $cookie = $response->getCookie((string) config('chabok.refresh_cookie.name'), false);
        $this->assertNotNull($cookie);

        return [
            'response' => $response,
            'cookie' => (string) $cookie->getValue(),
            'token' => (string) $response->json('data.access_token'),
        ];
    }
}
