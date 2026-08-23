<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Authorization\Application\AuthorizationService;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Manifest\Application\ManifestService;
use Modules\Manifest\Domain\ManifestEligibilityReason;

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
        $manifest = $service->create($principal, $node, [
            'manifest_status' => 'IR',
            'destination_node_id' => $node,
        ], (string) Str::uuid());
        $this->assertSame('DRAFT', $manifest['state']);
        $this->assertMatchesRegularExpression('/^MNF-\d{4}-\d{5}$/', $manifest['manifest_number']);

        $eligibleNumber = (string) DB::table('parcels')
            ->where('parcel_id', $eligible)->value('parcel_number');
        $ineligibleNumber = (string) DB::table('parcels')
            ->where('parcel_id', $ineligible)->value('parcel_number');
        $added = $service->add($principal, $node, $manifest['manifest_id'], [
            'expected_version' => 1,
            'input_source' => 'SCAN',
            'identifiers' => [$eligibleNumber, $ineligibleNumber],
        ], (string) Str::uuid());
        $this->assertCount(2, $added['detail']['parcels']);
        $this->assertSame(2, $added['detail']['version']);
        $this->assertSame(1, $added['detail']['bucket_counts']['pending']);
        $this->assertSame(1, $added['detail']['bucket_counts']['failed']);
        $failedOutcome = collect($added['outcomes'])->firstWhere('result', 'FAILED');
        $this->assertSame(ManifestEligibilityReason::StatusNotAllowed, $failedOutcome['reason_code']);
        $this->assertNotSame('', $failedOutcome['presentation']['detail']['fa']);

        $validated = $service->validate(
            $principal,
            $node,
            $manifest['manifest_id'],
            2,
            (string) Str::uuid(),
        );
        $this->assertSame('OPEN', $validated['state']);
        $this->assertSame(1, $validated['bucket_counts']['validated']);
        $this->assertSame(1, $validated['bucket_counts']['failed']);

        $closed = $service->confirm(
            $principal,
            $node,
            $manifest['manifest_id'],
            3,
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
            'failure_code' => ManifestEligibilityReason::StatusNotAllowed,
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
        $manifest = $service->create($principal, $node, [
            'manifest_status' => 'IR',
            'destination_node_id' => $node,
        ], (string) Str::uuid());
        try {
            $service->update($principal, $node, $manifest['manifest_id'], [
                'expected_version' => 99,
                'destination_node_id' => $node,
            ], (string) Str::uuid());
            $this->fail('Stale version must fail.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::VersionConflict, $exception->errorCode);
        }

        $otherNode = $this->node($tenant['hq_id'], 'MAN-N-OTHER');
        foreach (DB::table('user_role_assignments')->where('user_id', $actor['user_id'])->get() as $assignment) {
            DB::table('user_role_assignments')->where('assignment_id', $assignment->assignment_id)->update([
                'scope_type' => 'NODE', 'scope_id' => $node,
                'active_slot' => hash('sha256', "{$actor['user_id']}|{$assignment->role_id}|NODE|{$node}"),
            ]);
        }
        $this->app->make(AuthorizationService::class)->invalidateUser($actor['user_id']);
        try {
            $service->get($principal, $otherNode, $manifest['manifest_id']);
            $this->fail('An out-of-scope node must be denied before resource lookup.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::ScopeAccessDenied, $exception->errorCode);
        }

        $readOnly = $this->user($tenant['hq_id'], 'manifest-read-only');
        $readOnlyRole = (string) DB::table('roles')->where('role_code', 'branch_read_only')->value('role_id');
        DB::table('user_role_assignments')->insert([
            'assignment_id' => (string) Str::uuid(), 'hq_id' => $tenant['hq_id'],
            'user_id' => $readOnly['user_id'], 'role_id' => $readOnlyRole,
            'scope_type' => 'NODE', 'scope_id' => $node, 'includes_descendants' => false,
            'status' => 'ACTIVE',
            'active_slot' => hash('sha256', "{$readOnly['user_id']}|{$readOnlyRole}|NODE|{$node}"),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            $service->create(new AuthenticatedPrincipal(
                $readOnly['user_id'], (string) Str::uuid(), $tenant['hq_id'], false,
            ), $node, ['manifest_status' => 'IR', 'destination_node_id' => $node], (string) Str::uuid());
            $this->fail('Read-only users must not create Manifests.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::PermissionDenied, $exception->errorCode);
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
        [$tenant, $actor, $node] = $this->context('MAN-API', 'manifest-api');
        [, $parcel] = $this->consignment($tenant['hq_id'], $actor['user_id'], $node);
        $parcelNumber = (string) DB::table('parcels')->where('parcel_id', $parcel)->value('parcel_number');
        $login = $this->login('manifest-api');
        $this->withToken($login['token'])->withHeader('X-Node-Id', $node)
            ->getJson('/api/v1/manifests/context-options')->assertOk()
            ->assertJsonPath('data.current_node.node_id', $node)
            ->assertJsonPath('data.contexts.0.context_key', 'PICKUP_RECEPTION');
        $payload = ['manifest_status' => 'IR', 'destination_node_id' => $node];
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

        $manifestId = (string) $first->json('data.manifest_id');
        $addPayload = ['expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$parcelNumber]];
        foreach ([1, 2] as $_) {
            $this->withToken($login['token'])->withHeader('X-Node-Id', $node)
                ->withHeader('Idempotency-Key', 'manifest-add-key-000000001')
                ->postJson("/api/v1/manifests/{$manifestId}/parcels", $addPayload)
                ->assertOk()->assertJsonPath('data.version', 2);
        }
        $validatePayload = ['expected_version' => 2];
        foreach ([1, 2] as $_) {
            $this->withToken($login['token'])->withHeader('X-Node-Id', $node)
                ->withHeader('Idempotency-Key', 'manifest-validate-key-00001')
                ->postJson("/api/v1/manifests/{$manifestId}/validate", $validatePayload)
                ->assertOk()->assertJsonPath('data.version', 3);
        }
        $confirmPayload = ['expected_version' => 3, 'acknowledge_partial_success' => true];
        foreach ([1, 2] as $_) {
            $this->withToken($login['token'])->withHeader('X-Node-Id', $node)
                ->withHeader('Idempotency-Key', 'manifest-confirm-key-0001')
                ->postJson("/api/v1/manifests/{$manifestId}/confirm", $confirmPayload)
                ->assertOk()->assertJsonPath('data.state', 'CLOSED');
        }
        $this->assertSame(1, DB::table('manifest_parcels')->where('manifest_id', $manifestId)->count());
        $this->assertSame(1, DB::table('consignment_status_events')->where([
            'manifest_id' => $manifestId, 'parcel_id' => $parcel,
        ])->count());
        $this->assertSame(1, DB::table('outbox_events')->where([
            'aggregate_id' => $manifestId, 'event_type' => 'manifest.closed',
        ])->count());
    }

    public function test_route_outbound_and_delivery_assignment_use_configured_resources(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-OPS', 'manifest-operations');
        $destination = $this->node($tenant['hq_id'], 'MAN-DEST');
        [$consignmentId, $parcelId, $parcelNumber] = $this->operationalConsignment(
            $tenant['hq_id'], $actor['user_id'], $node, $destination, 'ROU', $node,
        );
        [$planId, $legId] = $this->routedPlan(
            $tenant['hq_id'], $actor['user_id'], $consignmentId, $node, $destination,
        );
        DB::table('parcels')->where('parcel_id', $parcelId)->update([
            'active_route_plan_id' => $planId,
            'active_route_plan_leg_id' => $legId,
        ]);

        $service = $this->app->make(ManifestService::class);
        $options = $service->contextOptions($principal, $node);
        $routeOption = collect($options['contexts'])->firstWhere('context_key', 'ROUTE_OUTBOUND:'.$legId);
        $this->assertNotNull($routeOption);
        $this->assertSame($parcelId, $service->eligible($principal, $node, $this->createFromSelection(
            $service, $principal, $node, $routeOption['selection'],
        )['manifest_id'], [])->items()[0]['parcel_id']);

        $manifest = collect($service->list($principal, $node, [])->items())
            ->first(fn (object $row): bool => (string) $row->manifest_status === 'OF');
        $added = $service->add($principal, $node, (string) $manifest->manifest_id, [
            'expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$parcelNumber],
        ], (string) Str::uuid());
        $open = $service->validate($principal, $node, (string) $manifest->manifest_id, $added['detail']['version'], (string) Str::uuid());
        $closed = $service->confirm($principal, $node, (string) $manifest->manifest_id, $open['version'], (string) Str::uuid());
        $this->assertSame('CLOSED', $closed['state']);
        $this->assertSame('OF', DB::table('parcels')->where('parcel_id', $parcelId)->value('current_status'));
        $this->assertSame('OUTBOUND_CONFIRMED', DB::table('route_plan_legs')->where('route_plan_leg_id', $legId)->value('status'));

        [$deliveryConsignment, $deliveryParcel, $deliveryNumber] = $this->operationalConsignment(
            $tenant['hq_id'], $actor['user_id'], $node, $node, 'IR', $node,
        );
        $driver = $this->driver($tenant['hq_id'], $node, 'DELIVERY');
        $vehicle = $this->vehicle($tenant['hq_id'], $node);
        $delivery = $service->create($principal, $node, [
            'manifest_status' => 'OD', 'origin_node_id' => $node,
            'assigned_driver_id' => $driver, 'assigned_vehicle_id' => $vehicle,
        ], (string) Str::uuid());
        $deliveryAdded = $service->add($principal, $node, $delivery['manifest_id'], [
            'expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$deliveryNumber],
        ], (string) Str::uuid());
        $deliveryOpen = $service->validate($principal, $node, $delivery['manifest_id'], $deliveryAdded['detail']['version'], (string) Str::uuid());
        $deliveryClosed = $service->confirm($principal, $node, $delivery['manifest_id'], $deliveryOpen['version'], (string) Str::uuid());
        $this->assertSame('OD', DB::table('parcels')->where('parcel_id', $deliveryParcel)->value('current_status'));
        $this->assertSame('DELIVERY_DRIVER', DB::table('parcels')->where('parcel_id', $deliveryParcel)->value('current_custody_type'));
        $this->assertDatabaseHas('delivery_tasks', [
            'consignment_id' => $deliveryConsignment, 'manifest_id' => $deliveryClosed['manifest_id'],
            'assigned_driver_id' => $driver, 'status' => 'ASSIGNED',
        ]);
    }

    public function test_zero_success_releases_rows_and_outbox_failure_rolls_back_confirmation(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-TX', 'manifest-transactions');
        [, $eligible, $ineligible] = $this->consignment($tenant['hq_id'], $actor['user_id'], $node);
        $service = $this->app->make(ManifestService::class);
        $failedManifest = $service->create($principal, $node, [
            'manifest_status' => 'IR', 'destination_node_id' => $node,
        ], (string) Str::uuid());
        $failedNumber = (string) DB::table('parcels')->where('parcel_id', $ineligible)->value('parcel_number');
        $failedAdd = $service->add($principal, $node, $failedManifest['manifest_id'], [
            'expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$failedNumber],
        ], (string) Str::uuid());
        $failedOpen = $service->validate($principal, $node, $failedManifest['manifest_id'], $failedAdd['detail']['version'], (string) Str::uuid());
        try {
            $service->confirm($principal, $node, $failedManifest['manifest_id'], $failedOpen['version'], (string) Str::uuid());
            $this->fail('A zero-success confirmation must report a domain rejection.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::ManifestNoSuccessfulParcels, $exception->errorCode);
        }
        $this->assertDatabaseHas('manifests', [
            'manifest_id' => $failedManifest['manifest_id'], 'state' => 'OPEN',
            'version' => $failedOpen['version'] + 1,
        ]);
        $this->assertDatabaseHas('manifest_parcels', [
            'manifest_id' => $failedManifest['manifest_id'], 'parcel_id' => $ineligible,
            'manifest_parcel_status' => 'FAILED', 'active_slot' => null,
        ]);
        $this->assertDatabaseMissing('outbox_events', [
            'aggregate_id' => $failedManifest['manifest_id'], 'event_type' => 'manifest.closed',
        ]);

        $rollbackManifest = $service->create($principal, $node, [
            'manifest_status' => 'IR', 'destination_node_id' => $node,
        ], (string) Str::uuid());
        $eligibleNumber = (string) DB::table('parcels')->where('parcel_id', $eligible)->value('parcel_number');
        $rollbackAdd = $service->add($principal, $node, $rollbackManifest['manifest_id'], [
            'expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$eligibleNumber],
        ], (string) Str::uuid());
        $rollbackOpen = $service->validate($principal, $node, $rollbackManifest['manifest_id'], $rollbackAdd['detail']['version'], (string) Str::uuid());
        $this->app->instance(OutboxWriter::class, new class implements OutboxWriter {
            public function write(?string $hqId, string $aggregateType, string $aggregateId, string $eventType, string $correlationId, array $payload, int $eventVersion = 1, ?string $causationId = null): void
            {
                throw new \RuntimeException('Injected outbox failure.');
            }
        });
        try {
            $this->app->make(ManifestService::class)->confirm(
                $principal, $node, $rollbackManifest['manifest_id'], $rollbackOpen['version'], (string) Str::uuid(),
            );
            $this->fail('Outbox failure must roll back the whole confirmation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected outbox failure.', $exception->getMessage());
        }
        $this->assertDatabaseHas('manifests', [
            'manifest_id' => $rollbackManifest['manifest_id'], 'state' => 'OPEN', 'version' => $rollbackOpen['version'],
        ]);
        $this->assertDatabaseHas('manifest_parcels', [
            'manifest_id' => $rollbackManifest['manifest_id'], 'parcel_id' => $eligible,
            'manifest_parcel_status' => 'VALIDATED',
        ]);
        $this->assertSame('PU', DB::table('parcels')->where('parcel_id', $eligible)->value('current_status'));
        $this->assertDatabaseMissing('audit_events', [
            'target_id' => $rollbackManifest['manifest_id'], 'action_key' => 'MANIFEST_CONFIRMED',
        ]);
    }

    /** @param array<string, mixed> $selection @return array<string, mixed> */
    private function createFromSelection(ManifestService $service, AuthenticatedPrincipal $principal, string $node, array $selection): array
    {
        return $service->create($principal, $node, $selection, (string) Str::uuid());
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

    private function node(string $hqId, string $code): string
    {
        $area = (string) DB::table('areas')->where('hq_id', $hqId)->value('area_id');
        $node = (string) Str::uuid();
        DB::table('nodes')->insert([
            'node_id' => $node, 'hq_id' => $hqId, 'area_id' => $area,
            'node_code' => $code, 'node_title' => $code, 'node_type' => 'BRANCH',
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $node;
    }

    /** @return array{string,string,string} */
    private function operationalConsignment(
        string $hq,
        string $actor,
        string $pickupNode,
        string $deliveryNode,
        string $parcelStatus,
        string $currentNode,
    ): array {
        $id = (string) Str::uuid();
        $number = 'CHB-OPS-'.Str::upper(Str::random(8));
        DB::table('consignments')->insert([
            'consignment_id' => $id, 'hq_id' => $hq, 'consignment_number' => $number,
            'initiator_id' => $actor, 'pickup_node_id' => $pickupNode, 'delivery_node_id' => $deliveryNode,
            'sender_contact_name' => 'Sender', 'sender_mobile' => '09120000001',
            'sender_address_text' => 'Sender address', 'sender_state' => 'Tehran', 'sender_city' => 'Tehran',
            'receiver_contact_name' => 'Receiver', 'receiver_mobile' => '09120000002',
            'receiver_address_text' => 'Receiver address', 'receiver_state' => 'Tehran', 'receiver_city' => 'Tehran',
            'service_type_id' => (string) Str::uuid(), 'shipping_method_id' => (string) Str::uuid(),
            'weight_kg' => 1, 'declared_value_amount' => 1000, 'insurance_enabled' => false,
            'cod_enabled' => false, 'payer' => 'SENDER', 'payment_method' => 'CASH',
            'current_status' => $parcelStatus, 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $parcel = (string) Str::uuid();
        $parcelNumber = $number.'-01';
        DB::table('parcels')->insert([
            'parcel_id' => $parcel, 'hq_id' => $hq, 'consignment_id' => $id,
            'parcel_number' => $parcelNumber, 'current_status' => $parcelStatus,
            'current_node_id' => $currentNode, 'current_custody_type' => 'NODE',
            'current_custodian_id' => $currentNode, 'version' => 1,
            'weight_kg' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$id, $parcel, $parcelNumber];
    }

    /** @return array{string,string} */
    private function routedPlan(string $hq, string $actor, string $consignment, string $origin, string $destination): array
    {
        $definition = (string) Str::uuid();
        $definitionLeg = (string) Str::uuid();
        $definitionVersion = (string) Str::uuid();
        $versionLeg = (string) Str::uuid();
        $plan = (string) Str::uuid();
        $planLeg = (string) Str::uuid();
        DB::table('route_definitions')->insert([
            'route_definition_id' => $definition, 'hq_id' => $hq,
            'route_code' => 'RTE-'.Str::upper(Str::random(6)), 'route_title' => 'Configured route',
            'status' => 'ACTIVE', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('route_definition_legs')->insert([
            'route_definition_leg_id' => $definitionLeg, 'hq_id' => $hq,
            'route_definition_id' => $definition, 'leg_order' => 1,
            'origin_node_id' => $origin, 'destination_node_id' => $destination,
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('route_definition_versions')->insert([
            'route_definition_version_id' => $definitionVersion, 'hq_id' => $hq,
            'route_definition_id' => $definition, 'version_number' => 1, 'status' => 'PUBLISHED',
            'purpose' => 'TRUNK', 'origin_node_id' => $origin, 'destination_node_id' => $destination,
            'priority' => 1, 'version' => 1, 'created_by' => $actor,
            'published_by' => $actor, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('route_definition_version_legs')->insert([
            'route_definition_version_leg_id' => $versionLeg, 'hq_id' => $hq,
            'route_definition_version_id' => $definitionVersion, 'leg_order' => 1,
            'origin_node_id' => $origin, 'destination_node_id' => $destination,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('route_definitions')->where('route_definition_id', $definition)
            ->update(['published_version_id' => $definitionVersion]);
        DB::table('route_plans')->insert([
            'route_plan_id' => $plan, 'hq_id' => $hq, 'consignment_id' => $consignment,
            'route_definition_id' => $definition, 'route_definition_version_id' => $definitionVersion,
            'status' => 'IN_PROGRESS', 'active_slot' => hash('sha256', $hq.'|'.$consignment),
            'version' => 1, 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('route_plan_legs')->insert([
            'route_plan_leg_id' => $planLeg, 'hq_id' => $hq, 'route_plan_id' => $plan,
            'source_route_definition_leg_id' => $definitionLeg,
            'source_route_definition_version_leg_id' => $versionLeg, 'leg_order' => 1,
            'origin_node_id' => $origin, 'destination_node_id' => $destination,
            'status' => 'ROUTED', 'routed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$plan, $planLeg];
    }

    /** @return array{string,string,string} */
    private function consignment(string $hq, string $actor, string $node): array
    {
        $id = (string) Str::uuid();
        $number = 'CHB-TEST-'.Str::upper(Str::random(8));
        $driver = $this->driver($hq, $node, 'PICKUP');
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
                'current_node_id' => null, 'current_custody_type' => 'PICKUP_DRIVER',
                'current_custodian_id' => $driver, 'version' => 1,
                'weight_kg' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('pickup_tasks')->insert([
            'pickup_task_id' => (string) Str::uuid(), 'hq_id' => $hq,
            'consignment_id' => $id, 'node_id' => $node, 'assigned_driver_id' => $driver,
            'status' => 'COMPLETED', 'version' => 1, 'completed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$number, $eligible, $ineligible];
    }

    private function driver(string $hq, string $node, string $capability): string
    {
        $id = (string) Str::uuid();
        DB::table('drivers')->insert([
            'driver_id' => $id, 'hq_id' => $hq,
            'driver_code' => 'DRV-'.Str::upper(Str::random(8)),
            'display_name' => 'Operational Driver', 'home_node_id' => $node,
            'operational_type' => $capability, 'status' => 'ACTIVE',
            'availability_status' => 'AVAILABLE', 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('driver_capabilities')->insert([
            'driver_capability_id' => (string) Str::uuid(), 'hq_id' => $hq,
            'driver_id' => $id, 'capability' => $capability, 'created_at' => now(),
        ]);

        return $id;
    }

    private function vehicle(string $hq, string $node): string
    {
        $id = (string) Str::uuid();
        $registration = 'IR-'.random_int(100000, 999999);
        DB::table('vehicles')->insert([
            'vehicle_id' => $id, 'hq_id' => $hq,
            'vehicle_code' => 'VEH-'.Str::upper(Str::random(8)),
            'registration_number' => $registration, 'plate_number' => $registration,
            'vehicle_type' => 'VAN', 'home_node_id' => $node,
            'status' => 'ACTIVE', 'availability_status' => 'AVAILABLE', 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }
}
