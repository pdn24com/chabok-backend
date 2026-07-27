<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Authorization\Application\AuthorizationService;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Manifest\Application\ManifestService;

final class ManifestIntegrationTest extends MySqlRedisTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
    }

    public function test_partial_success_confirmation_is_atomic_audited_and_retry_safe(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-A', 'manifest-manager');
        [$consignment, $eligible, $ineligible] = $this->consignment(
            $tenant['hq_id'],
            $actor['user_id'],
            $node,
        );
        $service = $this->app->make(ManifestService::class);
        $manifest = $service->create($principal, $node, ['manifest_status' => 'IR'], (string) Str::uuid());
        $this->assertSame('DRAFT', $manifest['state']);
        $this->assertMatchesRegularExpression('/^MNF-\d{4}-\d{5}$/', $manifest['manifest_number']);

        $eligibleNumber = (string) DB::table('parcels')
            ->where('parcel_id', $eligible)->value('parcel_number');
        $ineligibleNumber = (string) DB::table('parcels')
            ->where('parcel_id', $ineligible)->value('parcel_number');
        $added = $service->add($principal, $node, $manifest['manifest_id'], [
            'expected_version' => 1,
            'input_source' => 'SCAN',
            'identifiers' => [$eligibleNumber],
        ], (string) Str::uuid());
        $this->assertCount(1, $added['detail']['parcels']);
        $this->assertSame(2, $added['detail']['version']);

        $validated = $service->validate(
            $principal,
            $node,
            $manifest['manifest_id'],
            2,
            (string) Str::uuid(),
        );
        $this->assertSame('OPEN', $validated['state']);
        $this->assertSame(1, $validated['bucket_counts']['validated']);
        $this->assertSame(0, $validated['bucket_counts']['failed']);

        $insertedAfterValidation = $service->add($principal, $node, $manifest['manifest_id'], [
            'expected_version' => 3,
            'input_source' => 'SCAN',
            'identifiers' => [$ineligibleNumber],
        ], (string) Str::uuid());
        $this->assertSame('OPEN', $insertedAfterValidation['detail']['state']);
        $this->assertSame(1, $insertedAfterValidation['detail']['bucket_counts']['pending']);
        $this->assertSame(1, $insertedAfterValidation['detail']['bucket_counts']['validated']);

        $closed = $service->confirm(
            $principal,
            $node,
            $manifest['manifest_id'],
            4,
            (string) Str::uuid(),
        );
        $this->assertSame('CLOSED', $closed['state']);
        $this->assertSame(1, $closed['bucket_counts']['succeeded']);
        $this->assertSame(1, $closed['bucket_counts']['failed']);
        $this->assertSame('IR', DB::table('parcels')->where('parcel_id', $eligible)->value('current_status'));
        $this->assertSame('CFM', DB::table('parcels')->where('parcel_id', $ineligible)->value('current_status'));
        $this->assertDatabaseMissing('manifest_parcels', [
            'manifest_id' => $manifest['manifest_id'],
            'manifest_parcel_status' => 'PENDING',
        ]);
        $this->assertDatabaseHas('manifest_parcels', [
            'manifest_id' => $manifest['manifest_id'],
            'parcel_id' => $ineligible,
            'manifest_parcel_status' => 'FAILED',
            'failure_code' => 'INVALID_STATUS_TRANSITION',
            'active_slot' => null,
        ]);
        $this->assertSame(
            0,
            DB::table('manifest_parcels')
                ->where('manifest_id', $manifest['manifest_id'])
                ->whereNotNull('active_slot')
                ->count(),
        );
        $this->assertDatabaseHas('consignment_status_events', [
            'parcel_id' => $eligible,
            'manifest_id' => $manifest['manifest_id'],
            'new_status' => 'IR',
        ]);
        $this->assertDatabaseHas('audit_events', ['action_key' => 'MANIFEST_CONFIRMED']);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'manifest.closed']);
    }

    public function test_version_scope_entitlement_and_read_only_fail_without_mutation(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-N', 'manifest-negative');
        $service = $this->app->make(ManifestService::class);
        $manifest = $service->create($principal, $node, ['manifest_status' => 'OF'], (string) Str::uuid());
        try {
            $service->update($principal, $node, $manifest['manifest_id'], [
                'expected_version' => 99,
                'assigned_driver_id' => null,
            ], (string) Str::uuid());
            $this->fail('Stale version must fail.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::VersionConflict, $exception->errorCode);
        }

        [, , $foreignNode, $foreignPrincipal] = $this->context('MAN-B', 'manifest-foreign');
        try {
            $service->get($foreignPrincipal, $foreignNode, $manifest['manifest_id']);
            $this->fail('Cross-tenant guessed identifier must not resolve.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::ResourceNotFound, $exception->errorCode);
        }

        DB::table('tenant_module_entitlements')->where([
            'hq_id' => $tenant['hq_id'], 'module_code' => 'Manifest',
        ])->update(['status' => 'DISABLED']);
        $this->app->make(AuthorizationService::class)->invalidateUser($actor['user_id']);
        try {
            $service->get($principal, $node, $manifest['manifest_id']);
            $this->fail('Disabled entitlement must fail.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::EntitlementDisabled, $exception->errorCode);
        }
        $this->assertDatabaseCount('manifest_parcels', 0);
    }

    public function test_real_routes_use_envelopes_and_idempotent_create(): void
    {
        [, , $node] = $this->context('MAN-API', 'manifest-api');
        $login = $this->login('manifest-api');
        $payload = ['manifest_status' => 'IR'];
        $first = $this->withToken($login['token'])->withHeader('X-Node-Id', $node)
            ->withHeader('Idempotency-Key', 'manifest-create-key-000001')
            ->postJson('/api/v1/manifests', $payload)
            ->assertCreated()->assertJsonStructure(['data', 'meta', 'correlation_id']);
        $this->withToken($login['token'])->withHeader('X-Node-Id', $node)
            ->withHeader('Idempotency-Key', 'manifest-create-key-000001')
            ->postJson('/api/v1/manifests', $payload)
            ->assertCreated()->assertJsonPath('data.manifest_id', $first->json('data.manifest_id'));
        $this->withToken($login['token'])->withHeader('X-Node-Id', $node)
            ->getJson('/api/v1/manifests')->assertOk()->assertJsonPath('meta.pagination.total', 1);
    }

    /** @return array{array<string,mixed>,array<string,mixed>,string,AuthenticatedPrincipal} */
    private function context(string $code, string $username): array
    {
        $tenant = $this->tenant($code);
        $actor = $this->user($tenant['hq_id'], $username);
        foreach (['Foundation', 'Manifest'] as $module) {
            DB::table('tenant_module_entitlements')->insert([
                'entitlement_id' => (string) Str::uuid(), 'hq_id' => $tenant['hq_id'],
                'module_code' => $module, 'status' => 'ENABLED', 'activated_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $area = (string) Str::uuid();
        DB::table('areas')->insert([
            'area_id' => $area, 'hq_id' => $tenant['hq_id'], 'area_title' => $code,
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $node = (string) Str::uuid();
        DB::table('nodes')->insert([
            'node_id' => $node, 'hq_id' => $tenant['hq_id'], 'area_id' => $area,
            'node_code' => $code, 'node_title' => $code, 'node_type' => 'BRANCH',
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $role = (string) DB::table('roles')->where('role_code', 'branch_manager')->value('role_id');
        $approver = (string) DB::table('roles')->where('role_code', 'manifest_approver')->value('role_id');
        foreach ([$role, $approver] as $roleId) {
            DB::table('user_role_assignments')->insert([
                'assignment_id' => (string) Str::uuid(), 'hq_id' => $tenant['hq_id'],
                'user_id' => $actor['user_id'], 'role_id' => $roleId, 'scope_type' => 'TENANT',
                'scope_id' => null, 'includes_descendants' => false, 'status' => 'ACTIVE',
                'active_slot' => hash('sha256', "{$actor['user_id']}|{$roleId}|TENANT|-"),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return [$tenant, $actor, $node, new AuthenticatedPrincipal(
            $actor['user_id'],
            (string) Str::uuid(),
            $tenant['hq_id'],
            false,
        )];
    }

    /** @return array{string,string,string} */
    private function consignment(string $hq, string $actor, string $node): array
    {
        $id = (string) Str::uuid();
        $number = 'CHB-TEST-'.Str::upper(Str::random(8));
        DB::table('consignments')->insert([
            'consignment_id' => $id, 'hq_id' => $hq, 'consignment_number' => $number,
            'initiator_id' => $actor, 'pickup_node_id' => $node, 'sender_contact_name' => 'Sender',
            'sender_mobile' => '09120000001', 'sender_address_text' => 'Sender address',
            'sender_state' => 'Tehran', 'sender_city' => 'Tehran',
            'receiver_contact_name' => 'Receiver', 'receiver_mobile' => '09120000002',
            'receiver_address_text' => 'Receiver address', 'receiver_state' => 'Tehran',
            'receiver_city' => 'Tehran', 'service_type_id' => (string) Str::uuid(),
            'shipping_method_id' => (string) Str::uuid(), 'weight_kg' => 2,
            'declared_value_amount' => 1000, 'insurance_enabled' => false,
            'cod_enabled' => false, 'payer' => 'SENDER', 'payment_method' => 'CASH',
            'current_status' => 'PU', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $eligible = (string) Str::uuid();
        $ineligible = (string) Str::uuid();
        foreach ([[$eligible, 'PU', '01'], [$ineligible, 'CFM', '02']] as [$parcel, $status, $suffix]) {
            DB::table('parcels')->insert([
                'parcel_id' => $parcel, 'hq_id' => $hq, 'consignment_id' => $id,
                'parcel_number' => "{$number}-{$suffix}", 'current_status' => $status,
                'weight_kg' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return [$number, $eligible, $ineligible];
    }
}
