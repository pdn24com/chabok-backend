<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Authorization\Application\AuthorizationService;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Manifest\Application\ManifestService;
use Modules\Manifest\Domain\ManifestEligibilityReason;
use Modules\Consignment\Application\ConsignmentService;
use Modules\Operations\Application\DeliveryTaskService;

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
            'expected_version' => 0,
            'manifest_status' => 'IR',
            'context_key' => 'IR:PICKUP:'.$node,
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
            'expected_version' => 0,
            'manifest_status' => 'IR',
            'context_key' => 'IR:PICKUP:'.$node,
        ], (string) Str::uuid());
        try {
            $service->update($principal, $node, $manifest['manifest_id'], [
                'expected_version' => 99,
                'context_key' => 'IR:PICKUP:'.$node,
            ], (string) Str::uuid());
            $this->fail('Stale version must fail.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::ManifestVersionConflict, $exception->errorCode);
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
            ), $node, ['expected_version' => 0, 'manifest_status' => 'IR', 'context_key' => 'IR:PICKUP:'.$node], (string) Str::uuid());
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
            ->assertJsonFragment(['context_key' => 'IR:PICKUP:'.$node]);
        $payload = ['expected_version' => 0, 'manifest_status' => 'IR', 'context_key' => 'IR:PICKUP:'.$node];
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
        $routeOption = collect($options['contexts'])->firstWhere('context_key', 'OF:'.$legId);
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
        $secondDeliveryParcel = (string) Str::uuid();
        $secondDeliveryNumber = $deliveryNumber.'-SECOND';
        DB::table('parcels')->insert([
            'parcel_id' => $secondDeliveryParcel, 'hq_id' => $tenant['hq_id'],
            'consignment_id' => $deliveryConsignment, 'parcel_number' => $secondDeliveryNumber,
            'current_status' => 'IR', 'current_node_id' => $node,
            'current_custody_type' => 'NODE', 'current_custodian_id' => $node,
            'version' => 1, 'weight_kg' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $driver = $this->driver($tenant['hq_id'], $node, 'DELIVERY');
        $vehicle = $this->vehicle($tenant['hq_id'], $node);
        $this->app->make(DeliveryTaskService::class)->ensurePending(
            $principal,
            $node,
            $deliveryConsignment,
        );
        $delivery = $service->create($principal, $node, [
            'expected_version' => 0, 'manifest_status' => 'OD',
            'context_key' => 'OD:'.$node, 'assigned_driver_id' => $driver,
        ], (string) Str::uuid());
        $deliveryAdded = $service->add($principal, $node, $delivery['manifest_id'], [
            'expected_version' => 1, 'input_source' => 'SCAN',
            'identifiers' => [$deliveryNumber, $secondDeliveryNumber],
        ], (string) Str::uuid());
        $deliveryOpen = $service->validate($principal, $node, $delivery['manifest_id'], $deliveryAdded['detail']['version'], (string) Str::uuid());
        $deliveryClosed = $service->confirm($principal, $node, $delivery['manifest_id'], $deliveryOpen['version'], (string) Str::uuid());
        $this->assertSame('OD', DB::table('parcels')->where('parcel_id', $deliveryParcel)->value('current_status'));
        $this->assertSame('OD', DB::table('parcels')->where('parcel_id', $secondDeliveryParcel)->value('current_status'));
        $this->assertSame('DELIVERY_DRIVER', DB::table('parcels')->where('parcel_id', $deliveryParcel)->value('current_custody_type'));
        $this->assertDatabaseHas('delivery_tasks', [
            'consignment_id' => $deliveryConsignment, 'manifest_id' => $deliveryClosed['manifest_id'],
            'assigned_driver_id' => $driver, 'status' => 'IN_PROGRESS',
        ]);
    }

    public function test_zero_success_releases_rows_and_outbox_failure_rolls_back_confirmation(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-TX', 'manifest-transactions');
        [, $eligible, $ineligible] = $this->consignment($tenant['hq_id'], $actor['user_id'], $node);
        $service = $this->app->make(ManifestService::class);
        $failedManifest = $service->create($principal, $node, [
            'expected_version' => 0, 'manifest_status' => 'IR', 'context_key' => 'IR:PICKUP:'.$node,
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
            'expected_version' => 0, 'manifest_status' => 'IR', 'context_key' => 'IR:PICKUP:'.$node,
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

    public function test_audit_failure_rolls_back_status_custody_manifest_and_outbox(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-AUDIT-TX', 'manifest-audit-rollback');
        [, $eligible] = $this->consignment($tenant['hq_id'], $actor['user_id'], $node);
        $number = (string) DB::table('parcels')->where('parcel_id', $eligible)->value('parcel_number');
        $service = $this->app->make(ManifestService::class);
        $manifest = $service->create($principal, $node, [
            'expected_version' => 0, 'manifest_status' => 'IR', 'context_key' => 'IR:PICKUP:'.$node,
        ], (string) Str::uuid());
        $added = $service->add($principal, $node, $manifest['manifest_id'], [
            'expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$number],
        ], (string) Str::uuid());
        $open = $service->validate(
            $principal, $node, $manifest['manifest_id'], $added['detail']['version'], (string) Str::uuid(),
        );
        $this->app->instance(AuditWriter::class, new class implements AuditWriter {
            public function write(
                ?string $hqId,
                ?string $initiatorId,
                string $action,
                string $targetType,
                ?string $targetId,
                string $correlationId,
                ?array $before = null,
                ?array $after = null,
                ?string $safeNote = null,
                ?string $ipAddress = null,
                ?string $userAgent = null,
                ?string $sourceClient = null,
            ): void {
                throw new \RuntimeException('Injected audit failure.');
            }
        });

        try {
            $this->app->make(ManifestService::class)->confirm(
                $principal, $node, $manifest['manifest_id'], $open['version'], (string) Str::uuid(),
            );
            $this->fail('Audit failure must roll back the whole confirmation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected audit failure.', $exception->getMessage());
        }

        $this->assertDatabaseHas('manifests', [
            'manifest_id' => $manifest['manifest_id'], 'state' => 'OPEN', 'version' => $open['version'],
        ]);
        $this->assertDatabaseHas('manifest_parcels', [
            'manifest_id' => $manifest['manifest_id'], 'parcel_id' => $eligible,
            'manifest_parcel_status' => 'VALIDATED',
        ]);
        $this->assertSame('PU', DB::table('parcels')->where('parcel_id', $eligible)->value('current_status'));
        $this->assertDatabaseMissing('parcel_custody_events', ['manifest_id' => $manifest['manifest_id']]);
        $this->assertDatabaseMissing('outbox_events', [
            'aggregate_id' => $manifest['manifest_id'], 'event_type' => 'manifest.closed',
        ]);
    }

    public function test_context_contract_exposes_all_eleven_manifest_targets_with_related_nodes_and_without_raw_operational_ids(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-MATRIX', 'manifest-matrix');
        $options = $this->app->make(ManifestService::class)->contextOptions($principal, $node);

        $this->assertSame(
            ['PD', 'PU', 'NPU', 'IR', 'ROU', 'OF', 'OS', 'CI', 'OD', 'OK', 'NOK'],
            array_column($options['transition_contracts'], 'target_status'),
        );
        foreach ($options['contexts'] as $option) {
            $this->assertSame(
                [],
                array_intersect(
                    ['origin_node_id', 'destination_node_id', 'route_plan_id', 'route_plan_leg_id'],
                    array_keys($option['selection']),
                ),
            );
            $this->assertSame(0, $option['selection']['expected_version']);
            $this->assertNotEmpty($option['related_node']['node_title']);
            $this->assertContains($option['related_node_role'], ['SOURCE', 'DESTINATION', 'COUNTERPARTY']);
        }
    }

    public function test_pickup_driver_is_reusable_across_partial_pd_manifests_and_aggregate_reaches_full(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-PD-REUSE', 'manifest-pd-reuse');
        [$consignment, $firstParcel, $firstNumber] = $this->operationalConsignment(
            $tenant['hq_id'], $actor['user_id'], $node, $node, 'CFM', $node,
        );
        $numbers = [$firstNumber];
        foreach ([2, 3, 4] as $suffix) {
            $parcelId = (string) Str::uuid();
            $parcelNumber = substr($firstNumber, 0, -2).sprintf('%02d', $suffix);
            DB::table('parcels')->insert([
                'parcel_id' => $parcelId, 'hq_id' => $tenant['hq_id'],
                'consignment_id' => $consignment, 'parcel_number' => $parcelNumber,
                'current_status' => 'CFM', 'current_node_id' => $node,
                'current_custody_type' => 'NODE', 'current_custodian_id' => $node,
                'version' => 1, 'weight_kg' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $numbers[] = $parcelNumber;
        }
        $driver = $this->driver($tenant['hq_id'], $node, 'PICKUP');
        $service = $this->app->make(ManifestService::class);

        $first = $service->create($principal, $node, [
            'expected_version' => 0, 'manifest_status' => 'PD',
            'context_key' => 'PD:'.$node, 'assigned_driver_id' => $driver,
        ], (string) Str::uuid());
        $added = $service->add($principal, $node, $first['manifest_id'], [
            'expected_version' => 1, 'input_source' => 'SCAN',
            'identifiers' => array_slice($numbers, 0, 3),
        ], (string) Str::uuid());
        $open = $service->validate($principal, $node, $first['manifest_id'], $added['detail']['version'], (string) Str::uuid());
        $service->confirm($principal, $node, $first['manifest_id'], $open['version'], (string) Str::uuid());

        $aggregate = DB::table('consignments')->where('consignment_id', $consignment)->first();
        $this->assertSame('PD', $aggregate->current_status);
        $this->assertSame('PARTIAL', $aggregate->aggregate_mode);
        $partialCounts = json_decode((string) $aggregate->parcel_status_counts, true);
        $this->assertSame(1, $partialCounts['CFM']);
        $this->assertSame(3, $partialCounts['PD']);
        $this->assertSame('ON_MISSION', DB::table('drivers')->where('driver_id', $driver)->value('availability_status'));

        $pickupDrivers = collect($service->contextOptions($principal, $node)['drivers']);
        $visibleDriver = $pickupDrivers->firstWhere('driver_id', $driver);
        $this->assertNotNull($visibleDriver);
        $this->assertSame('ON_MISSION', $visibleDriver['availability_status']);

        $second = $this->closeSingleManifest($service, $principal, $node, [
            'expected_version' => 0, 'manifest_status' => 'PD',
            'context_key' => 'PD:'.$node, 'assigned_driver_id' => $driver,
        ], $numbers[3]);
        $this->assertSame('CLOSED', $second['state']);

        $aggregate = DB::table('consignments')->where('consignment_id', $consignment)->first();
        $this->assertSame('PD', $aggregate->current_status);
        $this->assertSame('FULL', $aggregate->aggregate_mode);
        $this->assertSame(['PD' => 4], json_decode((string) $aggregate->parcel_status_counts, true));
        $this->assertSame('ON_MISSION', DB::table('drivers')->where('driver_id', $driver)->value('availability_status'));
        $detail = $this->app->make(ConsignmentService::class)->get($principal, $node, $consignment);
        $this->assertSame('FULL', $detail['aggregate']['mode']);
        $this->assertSame(4, $detail['aggregate']['target_count']);
        $this->assertDatabaseHas('consignment_status_events', [
            'consignment_id' => $consignment,
            'manifest_id' => $first['manifest_id'],
            'new_status' => 'PD',
            'aggregate_mode' => 'PARTIAL',
        ]);
        $this->assertNotNull($firstParcel);
    }

    public function test_transition_matrix_executes_all_ordinary_targets_and_reviewed_nok(): void
    {
        [$tenant, $actor, $origin, $principal] = $this->context('MAN-MATRIX', 'manifest-matrix');
        $destination = $this->node($tenant['hq_id'], 'MAN-MATRIX-DEST');
        [$consignment, $parcel, $number] = $this->operationalConsignment(
            $tenant['hq_id'], $actor['user_id'], $origin, $destination, 'CFM', $origin,
        );
        $pickupDriver = $this->driver($tenant['hq_id'], $origin, 'PICKUP');
        $service = $this->app->make(ManifestService::class);

        $this->closeSingleManifest($service, $principal, $origin, [
            'expected_version' => 0, 'manifest_status' => 'PD',
            'context_key' => 'PD:'.$origin, 'assigned_driver_id' => $pickupDriver,
        ], $number);
        $this->assertSame('PD', DB::table('parcels')->where('parcel_id', $parcel)->value('current_status'));

        $pickupTask = DB::table('pickup_tasks')->where('consignment_id', $consignment)->first();
        $this->closeSingleManifest($service, $principal, $origin, [
            'expected_version' => 0, 'manifest_status' => 'PU',
            'context_key' => 'PICKUP:'.$pickupTask->pickup_task_id.':PU',
            'assigned_driver_id' => $pickupDriver,
        ], $number);
        $this->assertSame('PU', DB::table('parcels')->where('parcel_id', $parcel)->value('current_status'));

        $this->closeSingleManifest($service, $principal, $origin, [
            'expected_version' => 0, 'manifest_status' => 'IR',
            'context_key' => 'IR:PICKUP:'.$origin,
        ], $number);
        $this->assertSame('IR', DB::table('parcels')->where('parcel_id', $parcel)->value('current_status'));

        [$plan, $leg] = $this->routedPlan(
            $tenant['hq_id'], $actor['user_id'], $consignment, $origin, $destination,
        );
        $this->closeSingleManifest($service, $principal, $origin, [
            'expected_version' => 0, 'manifest_status' => 'ROU',
            'context_key' => 'ROU:'.$origin,
        ], $number);
        $this->assertSame('ROU', DB::table('parcels')->where('parcel_id', $parcel)->value('current_status'));

        $of = $this->closeSingleManifest($service, $principal, $origin, [
            'expected_version' => 0, 'manifest_status' => 'OF',
            'context_key' => 'OF:'.$leg,
        ], $number);
        $this->assertSame('OF', DB::table('parcels')->where('parcel_id', $parcel)->value('current_status'));

        $linehaulDriver = $this->driver($tenant['hq_id'], $origin, 'LINEHAUL');
        $vehicle = $this->vehicle($tenant['hq_id'], $origin);
        $os = $this->closeSingleManifest($service, $principal, $origin, [
            'expected_version' => 0, 'manifest_status' => 'OS',
            'context_key' => 'OS:OF:'.$of['manifest_id'],
            'assigned_driver_id' => $linehaulDriver, 'assigned_vehicle_id' => $vehicle,
        ], $number);
        $this->assertSame('OS', DB::table('parcels')->where('parcel_id', $parcel)->value('current_status'));

        $this->closeSingleManifest($service, $principal, $destination, [
            'expected_version' => 0, 'manifest_status' => 'IR',
            'context_key' => 'IR:OS:'.$os['manifest_id'],
            'assigned_driver_id' => $linehaulDriver, 'assigned_vehicle_id' => $vehicle,
        ], $number);
        $this->assertSame('IR', DB::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $this->assertSame('COMPLETED', DB::table('route_plans')->where('route_plan_id', $plan)->value('status'));

        $deliveryDriver = $this->driver($tenant['hq_id'], $destination, 'DELIVERY');
        $this->closeSingleManifest($service, $principal, $destination, [
            'expected_version' => 0, 'manifest_status' => 'OD',
            'context_key' => 'OD:'.$destination, 'assigned_driver_id' => $deliveryDriver,
        ], $number);
        $this->assertSame('OD', DB::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $deliveryTask = DB::table('delivery_tasks')->where('consignment_id', $consignment)->first();

        $this->closeSingleManifest($service, $principal, $destination, [
            'expected_version' => 0, 'manifest_status' => 'OK',
            'context_key' => 'DELIVERY:'.$deliveryTask->delivery_task_id.':OK',
            'assigned_driver_id' => $deliveryDriver,
        ], $number);
        $this->assertSame('OK', DB::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $this->assertSame('RECIPIENT', DB::table('parcels')->where('parcel_id', $parcel)->value('current_custody_type'));

        [$nokConsignment, $nokParcel, $nokNumber] = $this->operationalConsignment(
            $tenant['hq_id'], $actor['user_id'], $destination, $destination, 'OD', $destination,
        );
        $nokDriver = $this->driver($tenant['hq_id'], $destination, 'DELIVERY');
        DB::table('parcels')->where('parcel_id', $nokParcel)->update([
            'current_node_id' => null, 'current_custody_type' => 'DELIVERY_DRIVER',
            'current_custodian_id' => $nokDriver,
        ]);
        $nokTask = (string) Str::uuid();
        DB::table('delivery_tasks')->insert([
            'delivery_task_id' => $nokTask, 'hq_id' => $tenant['hq_id'],
            'consignment_id' => $nokConsignment, 'node_id' => $destination,
            'assigned_driver_id' => $nokDriver, 'status' => 'IN_PROGRESS',
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $nok = $service->create($principal, $destination, [
            'expected_version' => 0, 'manifest_status' => 'NOK',
            'context_key' => 'DELIVERY:'.$nokTask.':NOK', 'assigned_driver_id' => $nokDriver,
        ], (string) Str::uuid());
        $nokAdded = $service->add($principal, $destination, $nok['manifest_id'], [
            'expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$nokNumber],
        ], (string) Str::uuid());
        $nokOpen = $service->validate(
            $principal, $destination, $nok['manifest_id'], $nokAdded['detail']['version'], (string) Str::uuid(),
        );
        $service->confirm(
            $principal, $destination, $nok['manifest_id'], $nokOpen['version'], (string) Str::uuid(),
            'RECIPIENT_UNAVAILABLE', 'Recipient could not receive the Parcel.',
        );
        $pending = $service->exception($principal, $destination, $nok['manifest_id']);
        $reviewer = $this->user($tenant['hq_id'], 'manifest-matrix-reviewer');
        $this->assignManifestRoles($tenant['hq_id'], $reviewer['user_id']);
        $reviewerPrincipal = new AuthenticatedPrincipal(
            $reviewer['user_id'], (string) Str::uuid(), $tenant['hq_id'], false,
        );
        $service->approveException(
            $reviewerPrincipal, $destination, $nok['manifest_id'], $pending['manifest_version'],
            $pending['current_exception']['version'], null, (string) Str::uuid(),
        );
        $this->assertSame('NOK', DB::table('parcels')->where('parcel_id', $nokParcel)->value('current_status'));
    }

    public function test_intermediate_os_to_ci_to_of_and_final_os_to_ir_follow_ordered_route_evidence(): void
    {
        [$tenant, $actor, $origin, $principal] = $this->context('MAN-CI', 'manifest-ci');
        $intermediate = $this->node($tenant['hq_id'], 'MAN-CI-MID');
        $destination = $this->node($tenant['hq_id'], 'MAN-CI-FINAL');
        [$consignment, $parcel, $number] = $this->operationalConsignment(
            $tenant['hq_id'], $actor['user_id'], $origin, $destination, 'ROU', $origin,
        );
        [$plan, $firstLeg, $secondLeg] = $this->routedTwoLegPlan(
            $tenant['hq_id'], $actor['user_id'], $consignment, $origin, $intermediate, $destination,
        );
        DB::table('parcels')->where('parcel_id', $parcel)->update([
            'active_route_plan_id' => $plan,
            'active_route_plan_leg_id' => $firstLeg,
        ]);
        $service = $this->app->make(ManifestService::class);

        $firstOf = $this->closeSingleManifest($service, $principal, $origin, [
            'expected_version' => 0, 'manifest_status' => 'OF',
            'context_key' => 'OF:'.$firstLeg,
        ], $number);
        $firstDriver = $this->driver($tenant['hq_id'], $origin, 'LINEHAUL');
        $firstVehicle = $this->vehicle($tenant['hq_id'], $origin);
        $firstOs = $this->closeSingleManifest($service, $principal, $origin, [
            'expected_version' => 0, 'manifest_status' => 'OS',
            'context_key' => 'OS:OF:'.$firstOf['manifest_id'],
            'assigned_driver_id' => $firstDriver,
            'assigned_vehicle_id' => $firstVehicle,
        ], $number);

        $midOptions = $service->contextOptions($principal, $intermediate);
        $ciOption = collect($midOptions['contexts'])->firstWhere('context_key', 'CI:OS:'.$firstOs['manifest_id']);
        $this->assertNotNull($ciOption);
        $this->assertSame($origin, $ciOption['related_node']['node_id']);
        $this->assertSame('SOURCE', $ciOption['related_node_role']);
        $this->assertNull(collect($midOptions['contexts'])->firstWhere('context_key', 'IR:OS:'.$firstOs['manifest_id']));
        $ci = $this->closeSingleManifest($service, $principal, $intermediate, $ciOption['selection'], $number);
        $this->assertSame('CI', DB::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $this->assertSame($intermediate, DB::table('parcels')->where('parcel_id', $parcel)->value('current_node_id'));
        $this->assertSame($secondLeg, DB::table('parcels')->where('parcel_id', $parcel)->value('active_route_plan_leg_id'));
        $this->assertSame('RECEIVED', DB::table('route_plan_legs')->where('route_plan_leg_id', $firstLeg)->value('status'));
        $this->assertSame('TRANSIT_UNLOAD', $ci['manifest_type']);

        $secondOfOptions = $service->contextOptions($principal, $intermediate);
        $secondOfOption = collect($secondOfOptions['contexts'])->firstWhere('context_key', 'OF:'.$secondLeg);
        $this->assertNotNull($secondOfOption);
        $this->assertSame($destination, $secondOfOption['related_node']['node_id']);
        $secondOf = $this->closeSingleManifest($service, $principal, $intermediate, $secondOfOption['selection'], $number);
        $secondDriver = $this->driver($tenant['hq_id'], $intermediate, 'LINEHAUL');
        $secondVehicle = $this->vehicle($tenant['hq_id'], $intermediate);
        $secondOs = $this->closeSingleManifest($service, $principal, $intermediate, [
            'expected_version' => 0, 'manifest_status' => 'OS',
            'context_key' => 'OS:OF:'.$secondOf['manifest_id'],
            'assigned_driver_id' => $secondDriver,
            'assigned_vehicle_id' => $secondVehicle,
        ], $number);

        $finalOptions = $service->contextOptions($principal, $destination);
        $irOption = collect($finalOptions['contexts'])->firstWhere('context_key', 'IR:OS:'.$secondOs['manifest_id']);
        $this->assertNotNull($irOption);
        $this->assertSame($intermediate, $irOption['related_node']['node_id']);
        $this->assertNull(collect($finalOptions['contexts'])->firstWhere('context_key', 'CI:OS:'.$secondOs['manifest_id']));
        $this->closeSingleManifest($service, $principal, $destination, $irOption['selection'], $number);
        $this->assertSame('IR', DB::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $this->assertSame('COMPLETED', DB::table('route_plans')->where('route_plan_id', $plan)->value('status'));
        $this->assertDatabaseHas('delivery_tasks', [
            'consignment_id' => $consignment,
            'node_id' => $destination,
            'status' => 'PENDING',
        ]);
        $this->assertSame(['RECEIVED', 'RECEIVED'], DB::table('route_plan_legs')
            ->whereIn('route_plan_leg_id', [$firstLeg, $secondLeg])->orderBy('leg_order')->pluck('status')->all());
    }

    public function test_npu_exception_submit_self_review_reject_resubmit_and_approve_is_fail_closed(): void
    {
        [$tenant, $submitter, $node, $principal] = $this->context('MAN-EX', 'manifest-exception-submitter');
        [$consignment, $parcel, $number] = $this->operationalConsignment(
            $tenant['hq_id'], $submitter['user_id'], $node, $node, 'PD', $node,
        );
        $driver = $this->driver($tenant['hq_id'], $node, 'PICKUP');
        $task = (string) Str::uuid();
        DB::table('pickup_tasks')->insert([
            'pickup_task_id' => $task, 'hq_id' => $tenant['hq_id'],
            'consignment_id' => $consignment, 'node_id' => $node,
            'assigned_driver_id' => $driver, 'status' => 'ASSIGNED', 'version' => 1,
            'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('parcels')->where('parcel_id', $parcel)->update([
            'current_node_id' => null, 'current_custody_type' => 'PICKUP_DRIVER',
            'current_custodian_id' => $driver,
        ]);

        $service = $this->app->make(ManifestService::class);
        $manifest = $service->create($principal, $node, [
            'expected_version' => 0, 'manifest_status' => 'NPU',
            'context_key' => "PICKUP:{$task}:NPU", 'assigned_driver_id' => $driver,
        ], (string) Str::uuid());
        $added = $service->add($principal, $node, $manifest['manifest_id'], [
            'expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$number],
        ], (string) Str::uuid());
        $open = $service->validate($principal, $node, $manifest['manifest_id'], $added['detail']['version'], (string) Str::uuid());
        $pending = $service->confirm(
            $principal, $node, $manifest['manifest_id'], $open['version'], (string) Str::uuid(),
            'CUSTOMER_UNAVAILABLE', 'Customer did not attend the pickup window.',
        );
        $this->assertSame('OPEN', $pending['state']);
        $this->assertSame('PD', DB::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $state = $service->exception($principal, $node, $manifest['manifest_id']);
        $this->assertSame('PENDING', $state['current_exception']['status']);
        $this->assertDatabaseMissing('outbox_events', [
            'aggregate_id' => $manifest['manifest_id'], 'event_type' => 'manifest.closed',
        ]);

        try {
            $service->approveException(
                $principal, $node, $manifest['manifest_id'], $state['manifest_version'],
                $state['current_exception']['version'], null, (string) Str::uuid(),
            );
            $this->fail('The submitter must not approve their own Exception.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::ExceptionReviewerConflict, $exception->errorCode);
        }

        $reviewer = $this->user($tenant['hq_id'], 'manifest-exception-reviewer');
        $this->assignManifestRoles($tenant['hq_id'], $reviewer['user_id']);
        $reviewerPrincipal = new AuthenticatedPrincipal(
            $reviewer['user_id'], (string) Str::uuid(), $tenant['hq_id'], false,
        );
        $rejected = $service->rejectException(
            $reviewerPrincipal, $node, $manifest['manifest_id'], $state['manifest_version'],
            $state['current_exception']['version'], 'Evidence needs correction.', (string) Str::uuid(),
        );
        $rejectedState = $service->exception($principal, $node, $manifest['manifest_id']);
        $this->assertSame('REJECTED', $rejectedState['current_exception']['status']);
        $this->assertSame('OPEN', $rejected['state']);

        $resubmitted = $service->resubmitException(
            $principal, $node, $manifest['manifest_id'], $rejectedState['manifest_version'],
            $rejectedState['current_exception']['version'], 'CUSTOMER_UNAVAILABLE',
            'Corrected operational evidence.', (string) Str::uuid(),
        );
        $resubmittedState = $service->exception($principal, $node, $manifest['manifest_id']);
        $this->assertSame(2, $resubmittedState['current_exception']['submission_sequence']);
        $this->assertCount(1, $resubmittedState['previous_attempts']);
        $closed = $service->approveException(
            $reviewerPrincipal, $node, $manifest['manifest_id'], $resubmitted['version'],
            $resubmittedState['current_exception']['version'], 'Approved after correction.', (string) Str::uuid(),
        );
        $this->assertSame('CLOSED', $closed['state']);
        $this->assertSame('NPU', DB::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $this->assertDatabaseHas('operational_exception_history', ['action' => 'APPROVED']);
    }

    public function test_multi_consignment_os_and_ir_share_physical_context_and_preserve_per_consignment_evidence(): void
    {
        [$tenant, $actor, $origin, $principal] = $this->context('MAN-MOVE', 'manifest-movement');
        $destination = $this->node($tenant['hq_id'], 'MAN-MOVE-DEST');
        [$first, $second, $physicalLeg] = $this->compatibleMovementConsignments(
            $tenant['hq_id'], $actor['user_id'], $origin, $destination,
        );
        [$incompatibleConsignment, $incompatibleParcel, $incompatibleNumber] = $this->operationalConsignment(
            $tenant['hq_id'], $actor['user_id'], $origin, $destination, 'OF', $origin,
        );
        [$incompatiblePlan, $incompatibleLeg] = $this->routedPlan(
            $tenant['hq_id'], $actor['user_id'], $incompatibleConsignment, $origin, $destination,
        );
        DB::table('route_plan_legs')->where('route_plan_leg_id', $incompatibleLeg)->update([
            'status' => 'OUTBOUND_CONFIRMED', 'updated_at' => now(),
        ]);
        DB::table('parcels')->where('parcel_id', $incompatibleParcel)->update([
            'active_route_plan_id' => $incompatiblePlan, 'active_route_plan_leg_id' => $incompatibleLeg,
        ]);
        $sourceOutboundManifest = $this->closedOutboundEvidence(
            $tenant['hq_id'], $actor['user_id'], $origin, $destination, [$first, $second],
        );
        $driver = $this->driver($tenant['hq_id'], $origin, 'LINEHAUL');
        $vehicle = $this->vehicle($tenant['hq_id'], $origin);
        $service = $this->app->make(ManifestService::class);
        $key = 'OS:OF:'.$sourceOutboundManifest;
        $manifest = $service->create($principal, $origin, [
            'expected_version' => 0, 'manifest_status' => 'OS', 'context_key' => $key,
            'assigned_driver_id' => $driver, 'assigned_vehicle_id' => $vehicle,
        ], (string) Str::uuid());
        $added = $service->add($principal, $origin, $manifest['manifest_id'], [
            'expected_version' => 1, 'input_source' => 'SCAN',
            'identifiers' => [$first['number'], $second['number'], $incompatibleNumber],
        ], (string) Str::uuid());
        $open = $service->validate($principal, $origin, $manifest['manifest_id'], $added['detail']['version'], (string) Str::uuid());
        $departed = $service->confirm($principal, $origin, $manifest['manifest_id'], $open['version'], (string) Str::uuid());
        $this->assertSame(2, $departed['bucket_counts']['succeeded']);
        $this->assertSame(1, $departed['bucket_counts']['failed']);
        $this->assertDatabaseHas('manifest_parcels', [
            'manifest_id' => $manifest['manifest_id'], 'parcel_id' => $incompatibleParcel,
            'manifest_parcel_status' => 'FAILED',
            'failure_code' => ManifestEligibilityReason::PreviousMovementMismatch,
        ]);
        $this->assertSame(2, DB::table('manifest_parcels')->where('manifest_id', $manifest['manifest_id'])->distinct()->count('route_plan_leg_id'));
        $this->assertSame(['IN_TRANSIT'], DB::table('route_plan_legs')->whereIn('route_plan_leg_id', [$first['leg'], $second['leg']])->distinct()->pluck('status')->all());
        $this->assertSame('ON_MISSION', DB::table('drivers')->where('driver_id', $driver)->value('availability_status'));
        $this->assertSame('ON_MISSION', DB::table('vehicles')->where('vehicle_id', $vehicle)->value('availability_status'));

        $inbound = $service->create($principal, $destination, [
            'expected_version' => 0, 'manifest_status' => 'IR',
            'context_key' => 'IR:OS:'.$manifest['manifest_id'],
            'assigned_driver_id' => $driver, 'assigned_vehicle_id' => $vehicle,
        ], (string) Str::uuid());
        $inboundAdded = $service->add($principal, $destination, $inbound['manifest_id'], [
            'expected_version' => 1, 'input_source' => 'SCAN',
            'identifiers' => [$first['number'], $second['number']],
        ], (string) Str::uuid());
        $inboundOpen = $service->validate($principal, $destination, $inbound['manifest_id'], $inboundAdded['detail']['version'], (string) Str::uuid());
        $received = $service->confirm($principal, $destination, $inbound['manifest_id'], $inboundOpen['version'], (string) Str::uuid());
        $this->assertSame(2, $received['bucket_counts']['succeeded']);
        $this->assertSame(['RECEIVED'], DB::table('route_plan_legs')->whereIn('route_plan_leg_id', [$first['leg'], $second['leg']])->distinct()->pluck('status')->all());
        $this->assertSame('AVAILABLE', DB::table('drivers')->where('driver_id', $driver)->value('availability_status'));
        $this->assertSame('AVAILABLE', DB::table('vehicles')->where('vehicle_id', $vehicle)->value('availability_status'));

        $detail = $this->app->make(ConsignmentService::class)->get($principal, $destination, $first['consignment']);
        $this->assertNotEmpty($detail['journey']['route_legs']);
        $this->assertSame(['OS', 'IR'], array_column($detail['journey']['movement_manifests'], 'manifest_status'));
        $this->assertSame(['DEPARTED', 'RECEIVED'], array_column($detail['journey']['movement_manifests'], 'event_type'));
        $this->assertSame([$driver, $driver], array_column($detail['journey']['movement_manifests'], 'assigned_driver_id'));
        $this->assertSame([$vehicle, $vehicle], array_column($detail['journey']['movement_manifests'], 'assigned_vehicle_id'));
        $this->assertSame(['CLOSED', 'CLOSED'], array_column($detail['journey']['movement_manifests'], 'state'));
        $this->assertArrayNotHasKey('transport_run_id', $detail['journey']['movement_manifests'][0]);
        $this->assertArrayNotHasKey('transport_runs', $detail['journey']);
        $this->assertArrayNotHasKey('active_transport_run_id', $detail['parcels'][0]);
        $this->assertNotEmpty($detail['status_timeline']);
        $this->assertNotEmpty($detail['journey']['custody_timeline']);
        $this->assertFalse(Schema::hasTable('transport_runs'));
        $this->assertFalse(Schema::hasTable('transport_run_parcels'));
        $this->assertFalse(Schema::hasTable('transport_run_history'));
        $this->assertFalse(Schema::hasColumn('parcels', 'active_transport_run_id'));
        $this->assertFalse(Schema::hasColumn('manifests', 'transport_run_id'));
        $this->assertFalse(Schema::hasColumn('parcel_custody_events', 'transport_run_id'));
    }

    /** @param array<string, mixed> $selection @return array<string, mixed> */
    private function createFromSelection(ManifestService $service, AuthenticatedPrincipal $principal, string $node, array $selection): array
    {
        return $service->create($principal, $node, $selection, (string) Str::uuid());
    }

    /** @param array<string,mixed> $selection @return array<string,mixed> */
    private function closeSingleManifest(
        ManifestService $service,
        AuthenticatedPrincipal $principal,
        string $node,
        array $selection,
        string $parcelNumber,
    ): array {
        $manifest = $service->create($principal, $node, $selection, (string) Str::uuid());
        $added = $service->add($principal, $node, $manifest['manifest_id'], [
            'expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$parcelNumber],
        ], (string) Str::uuid());
        $open = $service->validate(
            $principal, $node, $manifest['manifest_id'], $added['detail']['version'], (string) Str::uuid(),
        );

        return $service->confirm(
            $principal, $node, $manifest['manifest_id'], $open['version'], (string) Str::uuid(),
        );
    }

    /** @return array{array<string,mixed>,array<string,mixed>,string,AuthenticatedPrincipal} */
    private function context(string $code, string $username): array
    {
        $tenant = $this->tenant($code);
        $actor = $this->user($tenant['hq_id'], $username);
        foreach (['Foundation', 'Consignment', 'Manifest', 'Parcel', 'Driver', 'LiveOperations'] as $module) {
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
        $dispatcher = (string) DB::table('roles')->where('role_code', 'dispatcher')->value('role_id');
        $exceptionReviewer = (string) DB::table('roles')->where('role_code', 'exception_reviewer')->value('role_id');
        foreach ([$role, $approver, $dispatcher, $exceptionReviewer] as $roleId) {
            DB::table('user_role_assignments')->insert([
                'assignment_id' => (string) Str::uuid(), 'hq_id' => $tenant['hq_id'],
                'user_id' => $actor['user_id'], 'role_id' => $roleId, 'scope_type' => 'TENANT',
                'scope_id' => null, 'includes_descendants' => false, 'status' => 'ACTIVE',
                'active_slot' => hash('sha256', "{$actor['user_id']}|{$roleId}|TENANT|-"),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->app->make(AuthorizationService::class)->invalidateUser($actor['user_id']);
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

    private function assignManifestRoles(string $hqId, string $userId): void
    {
        foreach (['branch_manager', 'manifest_approver', 'dispatcher', 'exception_reviewer'] as $roleCode) {
            $roleId = (string) DB::table('roles')->where('role_code', $roleCode)->value('role_id');
            DB::table('user_role_assignments')->insert([
                'assignment_id' => (string) Str::uuid(), 'hq_id' => $hqId,
                'user_id' => $userId, 'role_id' => $roleId, 'scope_type' => 'TENANT',
                'scope_id' => null, 'includes_descendants' => false, 'status' => 'ACTIVE',
                'active_slot' => hash('sha256', "{$userId}|{$roleId}|TENANT|-"),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->app->make(AuthorizationService::class)->invalidateUser($userId);
    }

    /** @param list<array<string,string>> $items */
    private function closedOutboundEvidence(
        string $hq,
        string $actor,
        string $origin,
        string $destination,
        array $items,
    ): string {
        $manifestId = (string) Str::uuid();
        $firstLeg = DB::table('route_plan_legs as leg')
            ->join('route_plans as plan', 'plan.route_plan_id', '=', 'leg.route_plan_id')
            ->where('leg.route_plan_leg_id', $items[0]['leg'])
            ->first(['leg.source_route_definition_version_leg_id', 'plan.route_definition_version_id']);
        DB::table('manifests')->insert([
            'manifest_id' => $manifestId,
            'hq_id' => $hq,
            'manifest_number' => 'MNF-TEST-'.Str::upper(Str::random(10)),
            'node_id' => $origin,
            'origin_node_id' => $origin,
            'destination_node_id' => $destination,
            'manifest_status' => 'OF',
            'manifest_type' => 'OUTBOUND_TRANSFER',
            'operational_context_type' => 'OUTBOUND_CONFIRMATION',
            'context_key' => 'OF:PHYSICAL:'.$manifestId,
            'route_definition_version_id' => $firstLeg->route_definition_version_id,
            'route_definition_version_leg_id' => $firstLeg->source_route_definition_version_leg_id,
            'state' => 'CLOSED',
            'version' => 1,
            'created_by' => $actor,
            'approved_by' => $actor,
            'closed_at' => now(),
            'operation_recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ($items as $item) {
            $leg = DB::table('route_plan_legs as leg')
                ->join('route_plans as plan', 'plan.route_plan_id', '=', 'leg.route_plan_id')
                ->where('leg.route_plan_leg_id', $item['leg'])
                ->first(['leg.*', 'plan.route_definition_version_id']);
            DB::table('manifest_parcels')->insert([
                'manifest_parcel_id' => (string) Str::uuid(),
                'hq_id' => $hq,
                'manifest_id' => $manifestId,
                'parcel_id' => $item['parcel'],
                'source_status' => 'ROU',
                'origin_node_id' => $origin,
                'destination_node_id' => $destination,
                'route_plan_id' => $item['plan'],
                'route_definition_version_id' => $leg->route_definition_version_id,
                'route_plan_leg_id' => $item['leg'],
                'route_definition_version_leg_id' => $leg->source_route_definition_version_leg_id,
                'manifest_parcel_status' => 'SUCCEEDED',
                'input_source' => 'SCAN',
                'input_value' => $item['number'],
                'created_by' => $actor,
                'processed_at' => now(),
                'evidence_recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $manifestId;
    }

    /** @return array{array<string,string>,array<string,string>,string} */
    private function compatibleMovementConsignments(
        string $hq,
        string $actor,
        string $origin,
        string $destination,
    ): array {
        [$firstConsignment, $firstParcel, $firstNumber] = $this->operationalConsignment(
            $hq, $actor, $origin, $destination, 'OF', $origin,
        );
        [$secondConsignment, $secondParcel, $secondNumber] = $this->operationalConsignment(
            $hq, $actor, $origin, $destination, 'OF', $origin,
        );
        [$firstPlan, $firstLeg] = $this->routedPlan(
            $hq, $actor, $firstConsignment, $origin, $destination,
        );
        DB::table('route_plan_legs')->where('route_plan_leg_id', $firstLeg)->update([
            'status' => 'OUTBOUND_CONFIRMED', 'updated_at' => now(),
        ]);
        $planEvidence = DB::table('route_plans')->where('route_plan_id', $firstPlan)->first();
        $legEvidence = DB::table('route_plan_legs')->where('route_plan_leg_id', $firstLeg)->first();

        $secondPlan = (string) Str::uuid();
        $secondLeg = (string) Str::uuid();
        DB::table('route_plans')->insert([
            'route_plan_id' => $secondPlan, 'hq_id' => $hq,
            'consignment_id' => $secondConsignment,
            'route_definition_id' => $planEvidence->route_definition_id,
            'route_definition_version_id' => $planEvidence->route_definition_version_id,
            'status' => 'IN_PROGRESS',
            'active_slot' => hash('sha256', $hq.'|'.$secondConsignment.'|ACTIVE'),
            'version' => 1, 'created_by' => $actor,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('route_plan_legs')->insert([
            'route_plan_leg_id' => $secondLeg, 'hq_id' => $hq,
            'route_plan_id' => $secondPlan,
            'source_route_definition_leg_id' => $legEvidence->source_route_definition_leg_id,
            'source_route_definition_version_leg_id' => $legEvidence->source_route_definition_version_leg_id,
            'leg_order' => 1, 'origin_node_id' => $origin,
            'destination_node_id' => $destination, 'status' => 'OUTBOUND_CONFIRMED',
            'routed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('parcels')->where('parcel_id', $firstParcel)->update([
            'active_route_plan_id' => $firstPlan, 'active_route_plan_leg_id' => $firstLeg,
        ]);
        DB::table('parcels')->where('parcel_id', $secondParcel)->update([
            'active_route_plan_id' => $secondPlan, 'active_route_plan_leg_id' => $secondLeg,
        ]);

        return [
            ['consignment' => $firstConsignment, 'parcel' => $firstParcel, 'number' => $firstNumber, 'plan' => $firstPlan, 'leg' => $firstLeg],
            ['consignment' => $secondConsignment, 'parcel' => $secondParcel, 'number' => $secondNumber, 'plan' => $secondPlan, 'leg' => $secondLeg],
            (string) $legEvidence->source_route_definition_version_leg_id,
        ];
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
    private function routedTwoLegPlan(
        string $hq,
        string $actor,
        string $consignment,
        string $origin,
        string $intermediate,
        string $destination,
    ): array {
        $definition = (string) Str::uuid();
        $definitionVersion = (string) Str::uuid();
        $plan = (string) Str::uuid();
        $definitionLegs = [(string) Str::uuid(), (string) Str::uuid()];
        $versionLegs = [(string) Str::uuid(), (string) Str::uuid()];
        $planLegs = [(string) Str::uuid(), (string) Str::uuid()];
        DB::table('route_definitions')->insert([
            'route_definition_id' => $definition, 'hq_id' => $hq,
            'route_code' => 'RTE-'.Str::upper(Str::random(6)), 'route_title' => 'Two-leg configured route',
            'status' => 'ACTIVE', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('route_definition_versions')->insert([
            'route_definition_version_id' => $definitionVersion, 'hq_id' => $hq,
            'route_definition_id' => $definition, 'version_number' => 1, 'status' => 'PUBLISHED',
            'purpose' => 'TRUNK', 'origin_node_id' => $origin, 'destination_node_id' => $destination,
            'priority' => 1, 'version' => 1, 'created_by' => $actor,
            'published_by' => $actor, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[1, $origin, $intermediate], [2, $intermediate, $destination]] as [$order, $from, $to]) {
            $index = $order - 1;
            DB::table('route_definition_legs')->insert([
                'route_definition_leg_id' => $definitionLegs[$index], 'hq_id' => $hq,
                'route_definition_id' => $definition, 'leg_order' => $order,
                'origin_node_id' => $from, 'destination_node_id' => $to,
                'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('route_definition_version_legs')->insert([
                'route_definition_version_leg_id' => $versionLegs[$index], 'hq_id' => $hq,
                'route_definition_version_id' => $definitionVersion, 'leg_order' => $order,
                'origin_node_id' => $from, 'destination_node_id' => $to,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('route_definitions')->where('route_definition_id', $definition)
            ->update(['published_version_id' => $definitionVersion]);
        DB::table('route_plans')->insert([
            'route_plan_id' => $plan, 'hq_id' => $hq, 'consignment_id' => $consignment,
            'route_definition_id' => $definition, 'route_definition_version_id' => $definitionVersion,
            'status' => 'IN_PROGRESS', 'active_slot' => hash('sha256', $hq.'|'.$consignment.'|ACTIVE'),
            'version' => 1, 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[1, $origin, $intermediate], [2, $intermediate, $destination]] as [$order, $from, $to]) {
            $index = $order - 1;
            DB::table('route_plan_legs')->insert([
                'route_plan_leg_id' => $planLegs[$index], 'hq_id' => $hq,
                'route_plan_id' => $plan, 'source_route_definition_leg_id' => $definitionLegs[$index],
                'source_route_definition_version_leg_id' => $versionLegs[$index],
                'leg_order' => $order, 'origin_node_id' => $from, 'destination_node_id' => $to,
                'status' => $order === 1 ? 'ROUTED' : 'PENDING',
                'routed_at' => $order === 1 ? now() : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return [$plan, $planLegs[0], $planLegs[1]];
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
