<?php

declare(strict_types=1);

namespace Tests\Integration;

use Modules\Authorization\Application\Services\AuthorizationCacheInvalidator;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use Tests\Support\RecordFixtureQuery;

final class DashboardIntegrationTest extends MySqlRedisTestCase
{
    public function test_dashboard_route_returns_bounded_live_counts_and_truthful_unavailable_values(): void
    {
        [$tenant, $actor, $node] = $this->branchContext('DASH-A', 'dashboard-a');
        $otherNode = $this->node($tenant['hq_id'], 'DASH-A2');
        [$foreignTenant, $foreignActor, $foreignNode] = $this->branchContext(
            'DASH-B',
            'dashboard-b',
        );

        $consignments = [];
        foreach (['CFM', 'NOK', 'OK', 'RO', 'AA'] as $index => $status) {
            $consignments[] = $this->consignment(
                $tenant['hq_id'],
                $actor['user_id'],
                $node,
                $status,
                "DASH-A-{$index}",
                now()->subMinutes(20 - $index),
            );
        }
        $this->consignment(
            $tenant['hq_id'],
            $actor['user_id'],
            $otherNode,
            'CFM',
            'DASH-A-OTHER',
        );
        $this->consignment(
            $foreignTenant['hq_id'],
            $foreignActor['user_id'],
            $foreignNode,
            'CFM',
            'DASH-B-FOREIGN',
        );

        $draft = $this->manifest(
            $tenant['hq_id'],
            $actor['user_id'],
            $node,
            'DRAFT',
            'MNF-DASH-A-1',
        );
        $this->manifest(
            $tenant['hq_id'],
            $actor['user_id'],
            $node,
            'OPEN',
            'MNF-DASH-A-2',
        );
        $this->manifest(
            $tenant['hq_id'],
            $actor['user_id'],
            $node,
            'CLOSED',
            'MNF-DASH-A-3',
        );
        $this->manifest(
            $tenant['hq_id'],
            $actor['user_id'],
            $otherNode,
            'OPEN',
            'MNF-DASH-A-OTHER',
        );
        $this->failedManifestParcel(
            $tenant['hq_id'],
            $actor['user_id'],
            $draft,
            $consignments[0]['parcel_id'],
        );

        $this->audit(
            $tenant['hq_id'],
            $actor['user_id'],
            'CONSIGNMENT_CREATED',
            'CONSIGNMENT',
            $consignments[0]['consignment_id'],
            now()->subMinutes(2),
        );
        $this->audit(
            $tenant['hq_id'],
            $actor['user_id'],
            'MANIFEST_VALIDATED',
            'MANIFEST',
            $draft['manifest_id'],
            now()->subMinute(),
        );
        $this->grantPermissionToUserRole($actor['user_id'], 'fleet.driver.view');
        $this->driver($tenant['hq_id'], $node, 'DASH-A-AVAILABLE', 'AVAILABLE');
        $this->driver($tenant['hq_id'], $node, 'DASH-A-MISSION', 'ON_MISSION');
        $this->driver($tenant['hq_id'], $node, 'DASH-A-MAINTENANCE', 'MAINTENANCE');
        $this->driver($tenant['hq_id'], $node, 'DASH-A-INACTIVE', 'AVAILABLE', 'INACTIVE');
        $this->driver($tenant['hq_id'], $otherNode, 'DASH-A-OTHER-NODE', 'AVAILABLE');
        $this->driver($foreignTenant['hq_id'], $foreignNode, 'DASH-B-FOREIGN', 'AVAILABLE');

        $login = $this->login('dashboard-a');
        $response = $this->withToken($login['token'])
            ->withHeader('X-Node-Id', $node)
            ->getJson('/api/v1/dashboard/operations')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'correlation_id'])
            ->assertJsonPath('data.status', 'PARTIAL')
            ->assertJsonPath('data.node.node_id', $node)
            ->assertJsonPath('data.consignments.total', 5)
            ->assertJsonPath('data.consignments.active', 2)
            ->assertJsonPath('data.consignments.status_counts.NOK', 1)
            ->assertJsonPath('data.consignments.status_counts.OK', 1)
            ->assertJsonPath('data.manifests.total', 3)
            ->assertJsonPath('data.manifests.open', 1)
            ->assertJsonPath('data.manifests.state_counts.DRAFT', 1)
            ->assertJsonPath('data.manifests.state_counts.CLOSED', 1)
            ->assertJsonPath('data.manifests.failed_rows', 1)
            ->assertJsonPath('data.metrics.0.key', 'ACTIVE_CONSIGNMENTS')
            ->assertJsonPath('data.metrics.0.value', 2)
            ->assertJsonPath('data.metrics.1.key', 'OPEN_MANIFESTS')
            ->assertJsonPath('data.metrics.1.value', 1)
            ->assertJsonPath('data.metrics.2.value', null)
            ->assertJsonPath('data.metrics.2.reason_code', 'DATA_NOT_PERSISTED')
            ->assertJsonPath('data.metrics.4.value', null)
            ->assertJsonPath('data.metrics.4.reason_code', 'MODULE_NOT_IMPLEMENTED')
            ->assertJsonPath('data.metrics.7.key', 'ACTIVE_DRIVERS')
            ->assertJsonPath('data.metrics.7.value', 3)
            ->assertJsonPath('data.drivers.status', 'AVAILABLE')
            ->assertJsonPath('data.drivers.total', 3)
            ->assertJsonPath('data.drivers.status_counts.AVAILABLE', 1)
            ->assertJsonPath('data.drivers.status_counts.ON_MISSION', 1)
            ->assertJsonPath('data.filters.0.status', 'UNAVAILABLE')
            ->assertJsonPath(
                'data.filters.0.reason_code',
                'OPERATIONAL_DATE_FILTER_UNSUPPORTED',
            )
            ->assertJsonCount(2, 'data.attention.items')
            ->assertJsonCount(2, 'data.latest_updates.items')
            ->assertJsonPath('data.shortcuts.0.navigation_target', '/consignments')
            ->assertJsonPath('data.shortcuts.4.navigation_target', null)
            ->assertJsonPath('data.shortcuts.5.status', 'AVAILABLE')
            ->assertJsonPath('data.shortcuts.5.navigation_target', '/app/administration/fleet/drivers');

        $serialized = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('before-secret', $serialized);
        $this->assertStringNotContainsString('after-secret', $serialized);
        $this->assertStringNotContainsString('safe-note-secret', $serialized);
        $this->assertStringNotContainsString('receiver_mobile', $serialized);
    }

    public function test_attention_and_updates_are_capped_at_six_without_n_plus_one_projection(): void
    {
        [$tenant, $actor, $node] = $this->branchContext('DASH-LIMIT', 'dashboard-limit');
        for ($index = 0; $index < 9; $index++) {
            $consignment = $this->consignment(
                $tenant['hq_id'],
                $actor['user_id'],
                $node,
                $index % 2 === 0 ? 'NOK' : 'NPU',
                "DASH-LIMIT-{$index}",
                now()->subSeconds($index),
            );
            $this->audit(
                $tenant['hq_id'],
                $actor['user_id'],
                'CONSIGNMENT_UPDATED',
                'CONSIGNMENT',
                $consignment['consignment_id'],
                now()->subSeconds($index),
            );
        }

        $login = $this->login('dashboard-limit');
        $this->withToken($login['token'])
            ->withHeader('X-Node-Id', $node)
            ->getJson('/api/v1/dashboard/operations')
            ->assertOk()
            ->assertJsonCount(6, 'data.attention.items')
            ->assertJsonCount(6, 'data.latest_updates.items');
    }

    public function test_entitlement_and_permission_filter_sections_without_leaking_data(): void
    {
        [$tenant, $actor, $node] = $this->branchContext(
            'DASH-AUTH',
            'dashboard-auth',
        );
        $this->consignment(
            $tenant['hq_id'],
            $actor['user_id'],
            $node,
            'CFM',
            'DASH-AUTH-CONSIGNMENT',
        );
        $this->manifest(
            $tenant['hq_id'],
            $actor['user_id'],
            $node,
            'OPEN',
            'DASH-AUTH-MANIFEST',
        );

        RecordFixtureQuery::table('tenant_module_entitlements')->where([
            'hq_id' => $tenant['hq_id'],
            'module_code' => 'Manifest',
        ])->update(['status' => 'DISABLED']);
        $this->app->make(AuthorizationCacheInvalidator::class)->invalidateUser($actor['user_id']);
        $login = $this->login('dashboard-auth');
        $this->withToken($login['token'])
            ->withHeader('X-Node-Id', $node)
            ->getJson('/api/v1/dashboard/operations')
            ->assertOk()
            ->assertJsonPath('data.consignments.status', 'AVAILABLE')
            ->assertJsonPath('data.consignments.total', 1)
            ->assertJsonPath('data.manifests.status', 'UNAVAILABLE')
            ->assertJsonPath('data.manifests.reason_code', 'ENTITLEMENT_DISABLED')
            ->assertJsonPath('data.manifests.total', null)
            ->assertJsonPath('data.metrics.1.value', null)
            ->assertJsonPath('data.attention.status', 'PARTIAL')
            ->assertJsonPath('data.latest_updates.status', 'PARTIAL');

        RecordFixtureQuery::table('tenant_module_entitlements')->where([
            'hq_id' => $tenant['hq_id'],
            'module_code' => 'Driver',
        ])->update(['status' => 'DISABLED']);
        $this->app->make(AuthorizationCacheInvalidator::class)->invalidateUser($actor['user_id']);
        $driverEntitlementLogin = $this->login('dashboard-auth');
        $this->withToken($driverEntitlementLogin['token'])
            ->withHeader('X-Node-Id', $node)
            ->getJson('/api/v1/dashboard/operations')
            ->assertOk()
            ->assertJsonPath('data.drivers.status', 'UNAVAILABLE')
            ->assertJsonPath('data.drivers.reason_code', 'ENTITLEMENT_DISABLED')
            ->assertJsonPath('data.drivers.total', null)
            ->assertJsonPath('data.shortcuts.5.navigation_target', null);

        $this->assignDashboardOnlyRole($tenant['hq_id'], $actor['user_id']);
        RecordFixtureQuery::table('tenant_module_entitlements')->where([
            'hq_id' => $tenant['hq_id'],
            'module_code' => 'Manifest',
        ])->update(['status' => 'ENABLED']);
        RecordFixtureQuery::table('tenant_module_entitlements')->where([
            'hq_id' => $tenant['hq_id'],
            'module_code' => 'Driver',
        ])->update(['status' => 'ENABLED']);
        $this->app->make(AuthorizationCacheInvalidator::class)->invalidateUser($actor['user_id']);

        $secondLogin = $this->login('dashboard-auth');
        $this->withToken($secondLogin['token'])
            ->withHeader('X-Node-Id', $node)
            ->getJson('/api/v1/dashboard/operations')
            ->assertOk()
            ->assertJsonPath('data.consignments.reason_code', 'PERMISSION_DENIED')
            ->assertJsonPath('data.consignments.total', null)
            ->assertJsonPath('data.manifests.reason_code', 'PERMISSION_DENIED')
            ->assertJsonPath('data.manifests.total', null)
            ->assertJsonPath('data.attention.status', 'UNAVAILABLE')
            ->assertJsonPath('data.latest_updates.status', 'UNAVAILABLE')
            ->assertJsonPath('data.drivers.reason_code', 'PERMISSION_DENIED')
            ->assertJsonPath('data.drivers.total', null)
            ->assertJsonPath('data.shortcuts.5.navigation_target', null);
    }

    public function test_selected_node_scope_is_required_and_enforced(): void
    {
        [$tenant, $actor, $node] = $this->branchContext(
            'DASH-NODE',
            'dashboard-node',
            'NODE',
        );
        $foreignNode = $this->node($tenant['hq_id'], 'DASH-NODE-OTHER');
        $login = $this->login('dashboard-node');

        $this->withToken($login['token'])
            ->getJson('/api/v1/dashboard/operations')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR');
        $this->withToken($login['token'])
            ->withHeader('X-Node-Id', $foreignNode)
            ->getJson('/api/v1/dashboard/operations')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'SCOPE_ACCESS_DENIED');
        $this->withToken($login['token'])
            ->withHeader('X-Node-Id', $node)
            ->getJson('/api/v1/dashboard/operations')
            ->assertOk();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
    }

    /**
     * @return array{
     *   array<string, mixed>,
     *   array<string, mixed>,
     *   string
     * }
     */
    private function branchContext(
        string $code,
        string $username,
        string $scopeType = 'TENANT',
    ): array {
        $tenant = $this->tenant($code);
        $actor = $this->user($tenant['hq_id'], $username);
        foreach ([
            'Foundation', 'Consignment', 'Manifest', 'Audit',
            'Pickup', 'Driver', 'Exception', 'LiveOperations',
        ] as $module) {
            RecordFixtureQuery::table('tenant_module_entitlements')->insert([
                'entitlement_id' => (string) random_int(1, 2000000000),
                'hq_id' => $tenant['hq_id'],
                'module_code' => $module,
                'status' => 'ENABLED',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $node = $this->node($tenant['hq_id'], $code);
        $roleId = (string) RecordFixtureQuery::table('roles')
            ->where('role_code', 'branch_manager')
            ->value('role_id');
        RecordFixtureQuery::table('user_role_assignments')->insert([
            'assignment_id' => (string) random_int(1, 2000000000),
            'hq_id' => $tenant['hq_id'],
            'user_id' => $actor['user_id'],
            'role_id' => $roleId,
            'scope_type' => $scopeType,
            'scope_id' => $scopeType === 'NODE' ? $node : null,
            'includes_descendants' => false,
            'status' => 'ACTIVE',
            'active_slot' => hash(
                'sha256',
                "{$actor['user_id']}|{$roleId}|{$scopeType}|"
                    .($scopeType === 'NODE' ? $node : '-'),
            ),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $actor, $node];
    }

    private function node(string $hqId, string $code): string
    {
        $areaId = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('areas')->insert([
            'area_id' => $areaId,
            'hq_id' => $hqId,
            'area_title' => "Area {$code}",
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $nodeId = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('nodes')->insert([
            'node_id' => $nodeId,
            'hq_id' => $hqId,
            'area_id' => $areaId,
            'node_code' => $code,
            'node_title' => "Node {$code}",
            'node_type' => 'BRANCH',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $nodeId;
    }

    /** @return array{consignment_id: string, parcel_id: string} */
    private function consignment(
        string $hqId,
        string $actorId,
        string $nodeId,
        string $status,
        string $number,
        mixed $at = null,
    ): array {
        $at ??= now();
        $consignmentId = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('consignments')->insert([
            'consignment_id' => $consignmentId,
            'hq_id' => $hqId,
            'consignment_number' => $number,
            'initiator_id' => $actorId,
            'pickup_node_id' => $nodeId,
            'delivery_node_id' => null,
            'pickup_man_id' => null,
            'delivery_man_id' => null,
            'sender_id' => null,
            'receiver_id' => null,
            'sender_contact_name' => 'Sender',
            'sender_mobile' => '09000000000',
            'sender_phone' => null,
            'sender_address_text' => 'Safe sender address',
            'sender_country' => null,
            'sender_state' => 'State',
            'sender_city' => 'City',
            'sender_postal_code' => null,
            'sender_latitude' => null,
            'sender_longitude' => null,
            'receiver_contact_name' => 'Receiver',
            'receiver_mobile' => '09111111111',
            'receiver_phone' => null,
            'receiver_address_text' => 'Safe receiver address',
            'receiver_country' => null,
            'receiver_state' => 'State',
            'receiver_city' => 'City',
            'receiver_postal_code' => null,
            'receiver_latitude' => null,
            'receiver_longitude' => null,
            'service_type_id' => (string) random_int(1, 2000000000),
            'shipping_method_id' => (string) random_int(1, 2000000000),
            'pickup_commitment_at' => null,
            'delivery_commitment_at' => null,
            'weight_kg' => 1,
            'width_cm' => null,
            'length_cm' => null,
            'height_cm' => null,
            'declared_value_amount' => 1000,
            'insurance_enabled' => false,
            'insurance_value_amount' => null,
            'cod_enabled' => false,
            'cod_amount' => null,
            'payer' => 'SENDER',
            'payment_method' => 'CASH',
            'current_status' => $status,
            'version' => 1,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $parcelId = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('parcels')->insert([
            'parcel_id' => $parcelId,
            'hq_id' => $hqId,
            'consignment_id' => $consignmentId,
            'parcel_number' => "{$number}-01",
            'current_status' => $status,
            'weight_kg' => 1,
            'width_cm' => null,
            'length_cm' => null,
            'height_cm' => null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        return ['consignment_id' => $consignmentId, 'parcel_id' => $parcelId];
    }

    /** @return array{manifest_id: string} */
    private function manifest(
        string $hqId,
        string $actorId,
        string $nodeId,
        string $state,
        string $number,
    ): array {
        $id = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('manifests')->insert([
            'manifest_id' => $id,
            'hq_id' => $hqId,
            'manifest_number' => $number,
            'node_id' => $nodeId,
            'manifest_status' => 'IR',
            'manifest_type' => 'INBOUND_RECEPTION',
            'operational_context_type' => 'PICKUP_RECEPTION',
            'context_key' => "IR:PICKUP:{$nodeId}:{$id}",
            'assigned_driver_id' => null,
            'state' => $state,
            'version' => 1,
            'created_by' => $actorId,
            'approved_by' => $state === 'CLOSED' ? $actorId : null,
            'closed_at' => $state === 'CLOSED' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['manifest_id' => $id];
    }

    /** @param array{manifest_id: string} $manifest */
    private function failedManifestParcel(
        string $hqId,
        string $actorId,
        array $manifest,
        string $parcelId,
    ): void {
        RecordFixtureQuery::table('manifest_parcels')->insert([
            'manifest_parcel_id' => (string) random_int(1, 2000000000),
            'hq_id' => $hqId,
            'manifest_id' => $manifest['manifest_id'],
            'parcel_id' => $parcelId,
            'manifest_parcel_status' => 'FAILED',
            'failure_code' => 'STATUS_NOT_ELIGIBLE',
            'failure_reason' => 'Safe test reason',
            'input_source' => 'MANUAL',
            'input_value' => 'SAFE',
            'active_slot' => null,
            'created_by' => $actorId,
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function audit(
        string $hqId,
        string $actorId,
        string $action,
        string $targetType,
        string $targetId,
        mixed $at,
    ): void {
        RecordFixtureQuery::table('audit_events')->insert([
            'audit_id' => (string) random_int(1, 2000000000),
            'hq_id' => $hqId,
            'initiator_id' => $actorId,
            'action_key' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before_snapshot' => json_encode(['secret' => 'before-secret']),
            'after_snapshot' => json_encode(['secret' => 'after-secret']),
            'safe_note' => 'safe-note-secret',
            'ip_address_hash' => hash('sha256', '127.0.0.1'),
            'user_agent_hash' => hash('sha256', 'test'),
            'source_client' => 'BRANCH_PANEL',
            'correlation_id' => (string) random_int(1, 2000000000),
            'created_at' => $at,
        ]);
    }

    private function driver(
        string $hqId,
        string $nodeId,
        string $code,
        string $availability,
        string $status = 'ACTIVE',
    ): void {
        RecordFixtureQuery::table('drivers')->insert([
            'driver_id' => (string) random_int(1, 2000000000),
            'hq_id' => $hqId,
            'user_id' => null,
            'driver_code' => $code,
            'display_name' => "Driver {$code}",
            'mobile' => null,
            'home_node_id' => $nodeId,
            'operational_type' => 'MULTI',
            'status' => $status,
            'availability_status' => $availability,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantPermissionToUserRole(string $userId, string $permissionCode): void
    {
        $roleId = (string) RecordFixtureQuery::table('user_role_assignments')
            ->where('user_id', $userId)
            ->value('role_id');
        $permissionId = (string) RecordFixtureQuery::table('permissions')
            ->where('permission_code', $permissionCode)
            ->value('permission_id');
        RecordFixtureQuery::table('role_permissions')->insert([
            'role_permission_id' => (string) random_int(1, 2000000000),
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }

    private function assignDashboardOnlyRole(string $hqId, string $userId): void
    {
        $roleId = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('roles')->insert([
            'role_id' => $roleId,
            'hq_id' => $hqId,
            'owner_key' => $hqId,
            'role_code' => 'dashboard_only',
            'role_title' => 'Dashboard only',
            'description' => null,
            'role_kind' => 'CUSTOM',
            'is_cloneable' => false,
            'status' => 'ACTIVE',
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (['branch_panel.access', 'node_context.switch'] as $permissionCode) {
            $permissionId = (string) RecordFixtureQuery::table('permissions')
                ->where('permission_code', $permissionCode)
                ->value('permission_id');
            RecordFixtureQuery::table('role_permissions')->insert([
                'role_permission_id' => (string) random_int(1, 2000000000),
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        }
        RecordFixtureQuery::table('user_role_assignments')->where('user_id', $userId)->update([
            'role_id' => $roleId,
            'active_slot' => hash('sha256', "{$userId}|{$roleId}|TENANT|-"),
            'updated_at' => now(),
        ]);
    }
}
