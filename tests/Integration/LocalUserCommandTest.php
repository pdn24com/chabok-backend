<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\RecordFixtureQuery;

final class LocalUserCommandTest extends MySqlRedisTestCase
{
    public function test_weak_password_requires_the_explicit_local_exception(): void
    {
        putenv('CHABOK_LOCAL_PASSWORD=weak-local-only');

        $this->artisan('chabok:local-user', ['--identifier' => 'local-helper-user'])
            ->assertFailed();
        $this->assertDatabaseMissingPublic('users', ['normalized_username' => 'local-helper-user']);
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
        $user = RecordFixtureQuery::table('users')->where('normalized_username', 'local-helper-user')->first();
        $this->assertNotNull($user);
        $sessionId = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('user_sessions')->insert([
            'session_id' => $sessionId,
            'hq_id' => $user->hq_id,
            'user_id' => $user->user_id,
            'token_family_id' => (string) random_int(1, 2000000000),
            'refresh_token_hash' => hash('sha256', 'local-helper-session'),
            'issued_at' => now(),
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('chabok:local-user', $arguments)->assertSuccessful();
        $user = RecordFixtureQuery::table('users')->where('normalized_username', 'local-helper-user')->first();
        $this->assertNotNull($user);
        $this->assertSame('ACTIVE', $user->status);
        $this->assertSame(0, (int) $user->must_change_password);
        $this->assertSame(1, RecordFixtureQuery::table('users')->where('normalized_username', 'local-helper-user')->count());

        $credential = RecordFixtureQuery::table('authentication_credentials')->where('user_id', $user->user_id)->first();
        $this->assertNotNull($credential);
        $this->assertSame('argon2id', $credential->algorithm);
        $this->assertTrue(password_verify('weak-local-only', $credential->password_hash));

        $roles = RecordFixtureQuery::table('user_role_assignments as ura')
            ->join('roles as r', 'r.id', '=', 'ura.role_id')
            ->where('ura.user_id', $user->user_id)
            ->where('ura.status', 'ACTIVE')
            ->pluck('r.role_code')
            ->sort()
            ->values()
            ->all();
        $this->assertSame(['branch_manager', 'hq_admin', 'manifest_approver'], $roles);
        $hqAdmin = RecordFixtureQuery::table('user_role_assignments as ura')
            ->join('roles as r', 'r.id', '=', 'ura.role_id')
            ->where('ura.user_id', $user->user_id)
            ->where('r.role_code', 'hq_admin')
            ->where('ura.status', 'ACTIVE')
            ->first(['ura.scope_type', 'ura.scope_id']);
        $this->assertNotNull($hqAdmin);
        $this->assertSame('TENANT', $hqAdmin->scope_type);
        $this->assertNull($hqAdmin->scope_id);
        $this->assertDatabaseHasPublic('user_sessions', [
            'session_id' => $sessionId,
            'revoked_reason' => 'LOCAL_CREDENTIAL_RESET',
        ]);

        $this->assertDatabaseHasPublic('nodes', ['node_code' => 'LOCAL-BRANCH', 'status' => 'ACTIVE']);
        foreach (['Foundation', 'IAM', 'Consignment', 'Parcel', 'Manifest'] as $moduleCode) {
            $this->assertDatabaseHasPublic('tenant_module_entitlements', [
                'hq_id' => $user->hq_id,
                'module_code' => $moduleCode,
                'status' => 'ENABLED',
            ]);
        }
    }

    public function test_local_consignment_visual_fixtures_are_scoped_repeatable_and_removable(): void
    {
        putenv('CHABOK_LOCAL_PASSWORD=Strong-Local-Password-123!');
        $this->artisan('chabok:local-user', [
            '--identifier' => 'admin',
            '--display-name' => 'Local Admin',
        ])->assertSuccessful();

        $this->artisan('chabok:local-consignment-fixtures')->assertSuccessful();
        $this->artisan('chabok:local-consignment-fixtures')->assertSuccessful();

        $hqId = (string) RecordFixtureQuery::table('hq_tenants')->where('hq_code', 'LOCAL-HQ')->value('hq_id');
        $nodeId = (string) RecordFixtureQuery::table('nodes')
            ->where('hq_id', $hqId)->where('node_code', 'LOCAL-BRANCH')->value('node_id');
        $fixtures = RecordFixtureQuery::table('consignments')
            ->whereBetween('consignment_number', ['CHB-2406-882016', 'CHB-2406-882149'])->get();
        $this->assertCount(134, $fixtures);
        $this->assertTrue($fixtures->every(
            fn ($row): bool => $row->hq_id === $hqId && $row->pickup_node_id === $nodeId,
        ));
        $this->assertSame(135, RecordFixtureQuery::table('parcels')
            ->whereBetween('parcel_number', ['CHB-2406-882016-01', 'CHB-2406-882149-01'])->count());
        $this->assertSame('تهران مرکزی', RecordFixtureQuery::table('nodes')->where('node_id', $nodeId)->value('node_title'));
        $detailId = (string) $fixtures->firstWhere('consignment_number', 'CHB-2406-882016')->consignment_id;
        $this->assertSame(2, RecordFixtureQuery::table('parcels')->where('consignment_id', $detailId)->count());
        $this->assertSame(1, RecordFixtureQuery::table('consignment_pricing_versions')
            ->where('consignment_id', $detailId)->count());
        $this->assertSame(3, RecordFixtureQuery::table('consignment_pricing_charge_lines')
            ->whereIn(
                'pricing_version_id',
                RecordFixtureQuery::table('consignment_pricing_versions')
                    ->where('consignment_id', $detailId)
                    ->pluck('pricing_version_id'),
            )->count());
        $this->assertSame(4, RecordFixtureQuery::table('consignment_status_events')
            ->where('consignment_id', $detailId)->count());
        $this->assertSame(2, RecordFixtureQuery::table('audit_events')
            ->where(['target_type' => 'CONSIGNMENT', 'target_id' => $detailId])->count());
        $this->assertDatabaseHasPublic('consignments', [
            'consignment_id' => $detailId,
            'cod_enabled' => true,
            'payer' => 'RECEIVER',
            'payment_method' => 'COD',
            'current_status' => 'IR',
        ]);

        $this->artisan('chabok:local-consignment-fixtures', ['--remove' => true])->assertSuccessful();
        $this->assertDatabaseMissingPublic('consignments', ['hq_id' => $hqId, 'pickup_node_id' => $nodeId]);
        $this->assertSame(0, RecordFixtureQuery::table('parcels')
            ->whereBetween('parcel_number', ['CHB-2406-882016-01', 'CHB-2406-882149-01'])->count());
        $this->assertSame(0, RecordFixtureQuery::table('consignment_pricing_versions')
            ->where('consignment_id', $detailId)->count());
        $this->assertSame(0, RecordFixtureQuery::table('consignment_status_events')
            ->where('consignment_id', $detailId)->count());
        $this->assertSame(0, RecordFixtureQuery::table('audit_events')
            ->where(['target_type' => 'CONSIGNMENT', 'target_id' => $detailId])->count());
    }

    public function test_local_consignment_visual_fixtures_preserve_manifest_referenced_aggregates(): void
    {
        putenv('CHABOK_LOCAL_PASSWORD=Strong-Local-Password-123!');
        $this->artisan('chabok:local-user', [
            '--identifier' => 'admin',
            '--display-name' => 'Local Admin',
        ])->assertSuccessful();
        $this->artisan('chabok:local-consignment-fixtures')
            ->expectsOutput('Created 134, refreshed 0, and preserved 0 LOCAL-HQ / LOCAL-BRANCH Consignment visual fixtures.')
            ->assertSuccessful();

        $hqId = (string) RecordFixtureQuery::table('hq_tenants')->where('hq_code', 'LOCAL-HQ')->value('hq_id');
        $nodeId = (string) RecordFixtureQuery::table('nodes')
            ->where('hq_id', $hqId)->where('node_code', 'LOCAL-BRANCH')->value('node_id');
        $userId = (string) RecordFixtureQuery::table('users')
            ->where('hq_id', $hqId)->where('normalized_username', 'admin')->value('user_id');
        $protectedConsignmentId = (string) RecordFixtureQuery::table('consignments')
            ->where('consignment_number', 'CHB-2406-882016')
            ->value('consignment_id');
        $protectedParcelId = (string) RecordFixtureQuery::table('parcels')
            ->where('consignment_id', $protectedConsignmentId)
            ->orderBy('parcel_number')
            ->value('parcel_id');
        $unprotectedConsignmentId = (string) RecordFixtureQuery::table('consignments')
            ->where('consignment_number', 'CHB-2406-882017')
            ->value('consignment_id');

        $manifestId = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('manifests')->insert([
            'manifest_id' => $manifestId,
            'hq_id' => $hqId,
            'manifest_number' => 'MNF-LOCAL-FIXTURE-00001',
            'context_key' => 'PICKUP_RECEPTION:LOCAL-PROTECTED-FIXTURE',
            'manifest_type' => 'INBOUND_RECEPTION',
            'operational_context_type' => 'PICKUP_RECEPTION',
            'node_id' => $nodeId,
            'manifest_status' => 'IR',
            'assigned_driver_id' => null,
            'state' => 'DRAFT',
            'version' => 1,
            'created_by' => $userId,
            'approved_by' => null,
            'closed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        RecordFixtureQuery::table('manifest_parcels')->insert([
            'manifest_parcel_id' => (string) random_int(1, 2000000000),
            'hq_id' => $hqId,
            'manifest_id' => $manifestId,
            'parcel_id' => $protectedParcelId,
            'manifest_parcel_status' => 'PENDING',
            'failure_code' => null,
            'failure_reason' => null,
            'input_source' => 'SCAN',
            'input_value' => 'CHB-2406-882016-01',
            'active_slot' => hash('sha256', "{$hqId}|{$protectedParcelId}|IR"),
            'created_by' => $userId,
            'processed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $manifestBeforeRefresh = (array) RecordFixtureQuery::table('manifests')
            ->where('manifest_id', $manifestId)->first();
        $associationBeforeRefresh = (array) RecordFixtureQuery::table('manifest_parcels')
            ->where('manifest_id', $manifestId)->first();
        RecordFixtureQuery::table('consignments')->where('consignment_id', $unprotectedConsignmentId)
            ->update(['receiver_contact_name' => 'MUST BE REFRESHED']);

        $this->artisan('chabok:local-consignment-fixtures')
            ->expectsOutput('Created 0, refreshed 133, and preserved 1 LOCAL-HQ / LOCAL-BRANCH Consignment visual fixtures.')
            ->assertSuccessful();

        $this->assertSame(
            $manifestBeforeRefresh,
            (array) RecordFixtureQuery::table('manifests')->where('manifest_id', $manifestId)->first(),
        );
        $this->assertSame(
            $associationBeforeRefresh,
            (array) RecordFixtureQuery::table('manifest_parcels')->where('manifest_id', $manifestId)->first(),
        );
        $this->assertDatabaseHasPublic('consignments', [
            'consignment_id' => $protectedConsignmentId,
            'consignment_number' => 'CHB-2406-882016',
        ]);
        $this->assertDatabaseHasPublic('parcels', [
            'parcel_id' => $protectedParcelId,
            'consignment_id' => $protectedConsignmentId,
        ]);
        $this->assertDatabaseMissingPublic('consignments', [
            'consignment_id' => $unprotectedConsignmentId,
            'receiver_contact_name' => 'MUST BE REFRESHED',
        ]);
        $this->assertSame(134, RecordFixtureQuery::table('consignments')
            ->whereBetween('consignment_number', ['CHB-2406-882016', 'CHB-2406-882149'])
            ->distinct()
            ->count('consignment_number'));
        $this->assertSame(0, RecordFixtureQuery::table('consignments')
            ->whereBetween('consignment_number', ['CHB-2406-882016', 'CHB-2406-882149'])
            ->select('consignment_number')
            ->groupBy('consignment_number')
            ->havingRaw('COUNT(*) > 1')
            ->count());
        $this->assertSame(0, RecordFixtureQuery::table('parcels')
            ->whereIn('consignment_id', RecordFixtureQuery::table('consignments')
                ->whereBetween('consignment_number', ['CHB-2406-882016', 'CHB-2406-882149'])
                ->pluck('consignment_id'))
            ->select('parcel_number')
            ->groupBy('parcel_number')
            ->havingRaw('COUNT(*) > 1')
            ->count());

        $this->artisan('chabok:local-consignment-fixtures', ['--remove' => true])
            ->expectsOutput('Removed 133 local Consignment visual fixtures; preserved 1 manifest-referenced fixtures.')
            ->assertSuccessful();

        $this->assertSame(
            $manifestBeforeRefresh,
            (array) RecordFixtureQuery::table('manifests')->where('manifest_id', $manifestId)->first(),
        );
        $this->assertSame(
            $associationBeforeRefresh,
            (array) RecordFixtureQuery::table('manifest_parcels')->where('manifest_id', $manifestId)->first(),
        );
        $this->assertDatabaseHasPublic('consignments', ['consignment_id' => $protectedConsignmentId]);
        $this->assertSame(2, RecordFixtureQuery::table('parcels')
            ->where('consignment_id', $protectedConsignmentId)->count());
        $this->assertSame(1, RecordFixtureQuery::table('consignments')
            ->whereBetween('consignment_number', ['CHB-2406-882016', 'CHB-2406-882149'])
            ->count());
        $this->assertSame(0, RecordFixtureQuery::table('consignments')
            ->whereBetween('consignment_number', ['CHB-2406-882017', 'CHB-2406-882149'])
            ->count());
        $this->assertSame(1, RecordFixtureQuery::table('consignment_pricing_versions')
            ->where('consignment_id', $protectedConsignmentId)->count());
        $this->assertSame(4, RecordFixtureQuery::table('consignment_status_events')
            ->where('consignment_id', $protectedConsignmentId)->count());
        $this->assertSame(2, RecordFixtureQuery::table('audit_events')
            ->where([
                'target_type' => 'CONSIGNMENT',
                'target_id' => $protectedConsignmentId,
            ])->count());

        $installedTriggers = RecordFixtureQuery::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->whereIn('TRIGGER_NAME', [
                'consignment_status_events_immutable_delete',
                'audit_events_prevent_delete',
            ])
            ->pluck('TRIGGER_NAME')
            ->sort()
            ->values()
            ->all();
        $this->assertSame([
            'audit_events_prevent_delete',
            'consignment_status_events_immutable_delete',
        ], $installedTriggers);
        $this->assertDeleteTriggerBlocks(
            'consignment_status_events',
            ['consignment_id' => $protectedConsignmentId],
            'immutable Consignment history',
        );
        $this->assertDeleteTriggerBlocks(
            'audit_events',
            ['target_type' => 'CONSIGNMENT', 'target_id' => $protectedConsignmentId],
            'audit_events is append-only',
        );
    }

    protected function tearDown(): void
    {
        putenv('CHABOK_LOCAL_PASSWORD');
        parent::tearDown();
    }

    /** @param array<string, string> $where */
    private function assertDeleteTriggerBlocks(string $table, array $where, string $message): void
    {
        try {
            RecordFixtureQuery::table($table)->where($where)->limit(1)->delete();
            $this->fail("The {$table} delete trigger must remain effective.");
        } catch (QueryException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }
}
