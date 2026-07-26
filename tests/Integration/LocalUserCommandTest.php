<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LocalUserCommandTest extends MySqlRedisTestCase
{
    protected function tearDown(): void
    {
        putenv('CHABOK_LOCAL_PASSWORD');
        parent::tearDown();
    }

    public function test_weak_password_requires_the_explicit_local_exception(): void
    {
        putenv('CHABOK_LOCAL_PASSWORD=weak-local-only');

        $this->artisan('chabok:local-user', ['--identifier' => 'local-helper-user'])
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['normalized_username' => 'local-helper-user']);
    }

    public function test_explicit_local_exception_upserts_approved_access_and_argon2id_credential(): void
    {
        putenv('CHABOK_LOCAL_PASSWORD=weak-local-only');
        $arguments = [
            '--identifier' => 'local-helper-user',
            '--display-name' => 'Local Helper User',
            '--allow-insecure-local-password' => true,
        ];

        $this->artisan('chabok:local-user', $arguments)->assertSuccessful();
        $user = DB::table('users')->where('normalized_username', 'local-helper-user')->first();
        $this->assertNotNull($user);
        $sessionId = (string) Str::uuid();
        DB::table('user_sessions')->insert([
            'session_id' => $sessionId,
            'hq_id' => $user->hq_id,
            'user_id' => $user->user_id,
            'token_family_id' => (string) Str::uuid(),
            'refresh_token_hash' => hash('sha256', 'local-helper-session'),
            'issued_at' => now(),
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('chabok:local-user', $arguments)->assertSuccessful();
        $user = DB::table('users')->where('normalized_username', 'local-helper-user')->first();
        $this->assertNotNull($user);
        $this->assertSame('ACTIVE', $user->status);
        $this->assertSame(0, (int) $user->must_change_password);
        $this->assertSame(1, DB::table('users')->where('normalized_username', 'local-helper-user')->count());

        $credential = DB::table('authentication_credentials')->where('user_id', $user->user_id)->first();
        $this->assertNotNull($credential);
        $this->assertSame('argon2id', $credential->algorithm);
        $this->assertTrue(password_verify('weak-local-only', $credential->password_hash));

        $roles = DB::table('user_role_assignments as ura')
            ->join('roles as r', 'r.role_id', '=', 'ura.role_id')
            ->where('ura.user_id', $user->user_id)
            ->where('ura.status', 'ACTIVE')
            ->pluck('r.role_code')
            ->sort()
            ->values()
            ->all();
        $this->assertSame(['branch_manager', 'manifest_approver'], $roles);
        $this->assertDatabaseHas('user_sessions', [
            'session_id' => $sessionId,
            'revoked_reason' => 'LOCAL_CREDENTIAL_RESET',
        ]);

        $this->assertDatabaseHas('nodes', ['node_code' => 'LOCAL-BRANCH', 'status' => 'ACTIVE']);
        foreach (['Foundation', 'IAM', 'Consignment', 'Parcel', 'Manifest'] as $moduleCode) {
            $this->assertDatabaseHas('tenant_module_entitlements', [
                'hq_id' => $user->hq_id,
                'module_code' => $moduleCode,
                'status' => 'ENABLED',
            ]);
        }
    }
}
