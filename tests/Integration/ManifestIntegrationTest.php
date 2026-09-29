<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Authorization\Application\Services\AuthorizationCacheInvalidator;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Domain\Enums\ManifestEligibilityReason;
use Modules\Manifest\Domain\Exceptions\ManifestRuleViolation;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;
use Modules\Manifest\Presentation\Http\Controllers\ManifestController;
use Modules\Manifest\Presentation\Http\Requests\ListManifestsRequest;
use RuntimeException;
use Tests\Support\ConsignmentFixtures as ConsignmentService;
use Tests\Support\DeliveryTaskFixtures as DeliveryTaskService;
use Tests\Support\ManifestFixtures as ManifestService;
use Tests\Support\RecordFixtureQuery;

final class ManifestIntegrationTest extends MySqlRedisTestCase
{
    public function test_multi_selection_manifest_list_contract_scope_and_batched_columns(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MULTI-M', 'multi-m');
        $this->consignment($tenant['hq_id'], $actor['user_id'], $node);
        $service = $this->app->make(ManifestService::class);
        foreach (['IR', 'OF', 'OD'] as $index => $target) {
            $record = $service->create($principal, $node, ['expected_version' => 0, 'manifest_status' => 'IR', 'context_key' => 'IR:PICKUP:'.$node], (string) random_int(1, 2000000000));
            RecordFixtureQuery::table('manifests')->where('manifest_id', $record['manifest_id'])->update([
                'manifest_status' => $target,
                'state' => $index === 1 ? 'OPEN' : 'DRAFT',
                'manifest_type' => ['INBOUND_RECEPTION', 'OUTBOUND_TRANSFER', 'DELIVERY_ASSIGNMENT'][$index],
                'operational_context_type' => ['PICKUP_RECEPTION', 'OUTBOUND_CONFIRMATION', 'DELIVERY_ASSIGNMENT'][$index],
            ]);
        }
        $requestList = function (array $filters) use ($node, $principal): array {
            $request = ListManifestsRequest::create('/api/v1/manifests', 'GET', $filters);
            $request->setContainer($this->app);
            $request->setRedirector($this->app->make('redirect'));
            $request->attributes->set('principal', $principal);
            $request->attributes->set('node_id', $node);
            $request->validateResolved();

            return $this->app->make(ManifestController::class)->index($request)->getData(true);
        };
        foreach ([15, 50, 100, 250] as $size) {
            $page = $requestList(['page_size' => $size]);
            self::assertSame($size, $page['meta']['pagination']['page_size']);
            self::assertCount(3, $page['data']);
        }
        try {
            $requestList(['page_size' => 251]);
            self::fail('Oversized page accepted');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('page_size', $error->errors());
        }
        $filters = ['manifest_status' => 'IR,OF,IR', 'state' => 'DRAFT,OPEN', 'page_size' => 1];
        $first = $requestList($filters);
        $second = $requestList([...$filters, 'page' => 2]);
        self::assertSame(2, $first['meta']['pagination']['total']);
        self::assertNotSame($first['data'][0]['manifest_id'], $second['data'][0]['manifest_id']);
        self::assertSame(1, $requestList(['manifest_status' => 'IR,OF', 'state' => 'OPEN'])['meta']['pagination']['total']);
        self::assertSame(1, $requestList(['manifest_status' => 'IR'])['meta']['pagination']['total']);
        self::assertSame(3, $requestList(['manifest_status' => '', 'state' => ''])['meta']['pagination']['total']);
        self::assertNotEmpty($first['data'][0]['issuing_node']['node_title']);
        self::assertSame(0, $first['data'][0]['total_count']);
        self::assertNull($first['data'][0]['context']['vehicle']);
        foreach (['INVALID', 'IR,,OF', ['IR'], implode(',', array_fill(0, 51, 'IR'))] as $invalid) {
            try {
                $requestList(['manifest_status' => $invalid]);
                self::fail('Invalid selection accepted');
            } catch (ValidationException $error) {
                self::assertNotEmpty($error->errors());
            }
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $one = $requestList(['page_size' => 1]);
        $oneQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        $all = $requestList(['page_size' => 3]);
        $allQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        self::assertLessThanOrEqual($oneQueries + 1, $allQueries, 'List references must not add per-row queries');
        [, , $otherNode, $otherPrincipal] = $this->context('MULTI-M-OTHER', 'multi-m-other');
        self::assertSame(0, $service->list($otherPrincipal, $otherNode, ['state' => ['DRAFT', 'OPEN']])->total());
        try {
            $service->list($principal, $otherNode, ['state' => ['DRAFT', 'OPEN']]);
            self::fail('Foreign selected node accepted');
        } catch (ApiException $error) {
            self::assertSame(ApiErrorCode::ScopeAccessDenied, $error->errorCode);
        }
        // More than 250 persisted rows proves each size changes actual API results.
        $template = (array) RecordFixtureQuery::table('manifests')->where('hq_id', $tenant['hq_id'])->first();
        unset($template['id']);
        for ($index = 0; $index < 260; $index++) {
            RecordFixtureQuery::table('manifests')->insert([...$template, 'manifest_id' => (string) random_int(1, 2000000000), 'manifest_number' => 'GRID-'.$index]);
        }
        foreach ([15, 50, 100, 250] as $size) {
            $first = $requestList(['search' => 'GRID-', 'page_size' => $size, 'page' => 1]);
            $second = $requestList(['search' => 'GRID-', 'page_size' => $size, 'page' => 2]);
            self::assertSame(260, $first['meta']['pagination']['total']);
            self::assertCount($size, $first['data']);
            self::assertCount(min($size, 260 - $size), $second['data']);
            self::assertSame([], array_values(array_intersect(array_column($first['data'], 'manifest_id'), array_column($second['data'], 'manifest_id'))));
        }
    }

    public function test_partial_success_confirmation_is_atomic_audited_and_retry_safe(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-A', 'manifest-manager');
        [$consignment, $eligible, $ineligible] = $this->consignment($tenant['hq_id'], $actor['user_id'], $node);
        $service = $this->app->make(ManifestService::class);
        $manifest = $service->create($principal, $node, ['expected_version' => 0, 'manifest_status' => 'IR', 'context_key' => 'IR:PICKUP:'.$node], (string) random_int(1, 2000000000));
        $this->assertSame('DRAFT', $manifest['state']);
        $this->assertMatchesRegularExpression('/^MNF-\d{4}-\d{5}$/', $manifest['manifest_number']);
        $eligibleNumber = (string) RecordFixtureQuery::table('parcels')->where('parcel_id', $eligible)->value('parcel_number');
        $ineligibleNumber = (string) RecordFixtureQuery::table('parcels')->where('parcel_id', $ineligible)->value('parcel_number');
        $added = $service->add($principal, $node, $manifest['manifest_id'], ['expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$eligibleNumber, $ineligibleNumber]], (string) random_int(1, 2000000000));
        $this->assertCount(2, $added['detail']['parcels']);
        $this->assertSame(2, $added['detail']['version']);
        $this->assertSame(1, $added['detail']['bucket_counts']['pending']);
        $this->assertSame(1, $added['detail']['bucket_counts']['failed']);
        $failedOutcome = collect($added['outcomes'])->firstWhere('result', 'FAILED');
        $this->assertSame(ManifestEligibilityReason::StatusNotAllowed->value, $failedOutcome['reason_code']);
        $this->assertNotSame('', $failedOutcome['presentation']['detail']['fa']);
        $validated = $service->validate($principal, $node, $manifest['manifest_id'], 2, (string) random_int(1, 2000000000));
        $this->assertSame('OPEN', $validated['state']);
        $this->assertSame(1, $validated['bucket_counts']['validated']);
        $this->assertSame(1, $validated['bucket_counts']['failed']);
        $closed = $service->confirm($principal, $node, $manifest['manifest_id'], 3, (string) random_int(1, 2000000000));
        $this->assertSame('CLOSED', $closed['state']);
        $this->assertSame(1, $closed['bucket_counts']['succeeded']);
        $this->assertSame(1, $closed['bucket_counts']['failed']);
        $this->assertSame('IR', RecordFixtureQuery::table('parcels')->where('parcel_id', $eligible)->value('current_status'));
        $this->assertSame('CFM', RecordFixtureQuery::table('parcels')->where('parcel_id', $ineligible)->value('current_status'));
        $this->assertDatabaseMissingPublic('manifest_parcels', ['manifest_id' => $manifest['manifest_id'], 'manifest_parcel_status' => 'PENDING']);
        $this->assertDatabaseHasPublic('manifest_parcels', [
            'manifest_id' => $manifest['manifest_id'],
            'parcel_id' => $ineligible,
            'manifest_parcel_status' => 'FAILED',
            'failure_code' => ManifestEligibilityReason::StatusNotAllowed->value,
            'active_slot' => null,
        ]);
        $this->assertSame(0, RecordFixtureQuery::table('manifest_parcels')->where('manifest_id', $manifest['manifest_id'])->whereNotNull('active_slot')->count());
        $this->assertDatabaseHasPublic('consignment_status_events', ['parcel_id' => $eligible, 'manifest_id' => $manifest['manifest_id'], 'new_status' => 'IR']);
        $this->assertDatabaseHasPublic('audit_events', ['action_key' => 'MANIFEST_CONFIRMED']);
        $this->assertDatabaseHasPublic('outbox_events', ['event_type' => 'manifest.closed']);
    }

    public function test_version_scope_entitlement_and_read_only_fail_without_mutation(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-N', 'manifest-negative');
        $service = $this->app->make(ManifestService::class);
        $manifest = $service->create($principal, $node, ['expected_version' => 0, 'manifest_status' => 'IR', 'context_key' => 'IR:PICKUP:'.$node], (string) random_int(1, 2000000000));
        try {
            $service->update($principal, $node, $manifest['manifest_id'], ['expected_version' => 99, 'context_key' => 'IR:PICKUP:'.$node], (string) random_int(1, 2000000000));
            $this->fail('Stale version must fail.');
        } catch (ApiException|ManifestRuleViolation $exception) {
            $this->assertSame(ApiErrorCode::ManifestVersionConflict, $exception->errorCode);
        }
        $otherNode = $this->node($tenant['hq_id'], 'MAN-N-OTHER');
        foreach (RecordFixtureQuery::table('user_role_assignments')->where('user_id', $actor['user_id'])->get() as $assignment) {
            RecordFixtureQuery::table('user_role_assignments')->where('assignment_id', $assignment->assignment_id)->update([
                'scope_type' => 'NODE',
                'scope_id' => $node,
                'active_slot' => hash('sha256', "{$actor['user_id']}|{$assignment->role_id}|NODE|{$node}"),
            ]);
        }
        $this->app->make(AuthorizationCacheInvalidator::class)->invalidateUser($actor['user_id']);
        try {
            $service->get($principal, $otherNode, $manifest['manifest_id']);
            $this->fail('An out-of-scope node must be denied before resource lookup.');
        } catch (ApiException|ManifestRuleViolation $exception) {
            $this->assertSame(ApiErrorCode::ScopeAccessDenied, $exception->errorCode);
        }
        $readOnly = $this->user($tenant['hq_id'], 'manifest-read-only');
        $readOnlyRole = (string) RecordFixtureQuery::table('roles')->where('role_code', 'branch_read_only')->value('role_id');
        RecordFixtureQuery::table('user_role_assignments')->insert([
            'assignment_id' => (string) random_int(1, 2000000000),
            'hq_id' => $tenant['hq_id'],
            'user_id' => $readOnly['user_id'],
            'role_id' => $readOnlyRole,
            'scope_type' => 'NODE',
            'scope_id' => $node,
            'includes_descendants' => false,
            'status' => 'ACTIVE',
            'active_slot' => hash('sha256', "{$readOnly['user_id']}|{$readOnlyRole}|NODE|{$node}"),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        try {
            $service->create(new AuthenticatedPrincipal($readOnly['user_id'], (string) random_int(1, 2000000000), $tenant['hq_id'], false), $node, ['expected_version' => 0, 'manifest_status' => 'IR', 'context_key' => 'IR:PICKUP:'.$node], (string) random_int(1, 2000000000));
            $this->fail('Read-only users must not create Manifests.');
        } catch (ApiException|ManifestRuleViolation $exception) {
            $this->assertSame(ApiErrorCode::PermissionDenied, $exception->errorCode);
        }
        [, , $foreignNode, $foreignPrincipal] = $this->context('MAN-B', 'manifest-foreign');
        try {
            $service->get($foreignPrincipal, $foreignNode, $manifest['manifest_id']);
            $this->fail('Cross-tenant guessed identifier must not resolve.');
        } catch (ApiException|ManifestRuleViolation $exception) {
            $this->assertSame(ApiErrorCode::ResourceNotFound, $exception->errorCode);
        }
        RecordFixtureQuery::table('tenant_module_entitlements')->where(['hq_id' => $tenant['hq_id'], 'module_code' => 'Manifest'])->update(['status' => 'DISABLED']);
        $this->app->make(AuthorizationCacheInvalidator::class)->invalidateUser($actor['user_id']);
        try {
            $service->get($principal, $node, $manifest['manifest_id']);
            $this->fail('Disabled entitlement must fail.');
        } catch (ApiException|ManifestRuleViolation $exception) {
            $this->assertSame(ApiErrorCode::EntitlementDisabled, $exception->errorCode);
        }
        $this->assertDatabaseCount('manifest_parcels', 0);
    }

    public function test_real_routes_use_envelopes_and_idempotent_create(): void
    {
        [$tenant, $actor, $node] = $this->context('MAN-API', 'manifest-api');
        [, $parcel] = $this->consignment($tenant['hq_id'], $actor['user_id'], $node);
        $parcelNumber = (string) RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('parcel_number');
        $login = $this->login('manifest-api');
        $this->withToken($login['token'])->withHeader('X-Node-Id', $node)->getJson('/api/v1/manifests/context-options')->assertOk()->assertJsonPath('data.current_node.node_id', $node)->assertJsonFragment(['context_key' => 'IR:PICKUP:'.$node]);
        $payload = ['expected_version' => 0, 'manifest_status' => 'IR', 'context_key' => 'IR:PICKUP:'.$node];
        $first = $this->withToken($login['token'])->withHeader('X-Node-Id', $node)->withHeader('Idempotency-Key', 'manifest-create-key-000001')->postJson('/api/v1/manifests', $payload)->assertCreated()->assertJsonStructure(['data', 'meta', 'correlation_id']);
        $this->withToken($login['token'])->withHeader('X-Node-Id', $node)->withHeader('Idempotency-Key', 'manifest-create-key-000001')->postJson('/api/v1/manifests', $payload)->assertCreated()->assertJsonPath('data.manifest_id', $first->json('data.manifest_id'));
        $this->withToken($login['token'])->withHeader('X-Node-Id', $node)->getJson('/api/v1/manifests')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $manifestId = (string) $first->json('data.manifest_id');
        $addPayload = ['expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$parcelNumber]];
        foreach ([1, 2] as $_) {
            $this->withToken($login['token'])->withHeader('X-Node-Id', $node)->withHeader('Idempotency-Key', 'manifest-add-key-000000001')->postJson("/api/v1/manifests/{$manifestId}/parcels", $addPayload)->assertOk()->assertJsonPath('data.version', 2);
        }
        $validatePayload = ['expected_version' => 2];
        foreach ([1, 2] as $_) {
            $this->withToken($login['token'])->withHeader('X-Node-Id', $node)->withHeader('Idempotency-Key', 'manifest-validate-key-00001')->postJson("/api/v1/manifests/{$manifestId}/validate", $validatePayload)->assertOk()->assertJsonPath('data.version', 3);
        }
        $confirmPayload = ['expected_version' => 3, 'acknowledge_partial_success' => true];
        foreach ([1, 2] as $_) {
            $this->withToken($login['token'])->withHeader('X-Node-Id', $node)->withHeader('Idempotency-Key', 'manifest-confirm-key-0001')->postJson("/api/v1/manifests/{$manifestId}/confirm", $confirmPayload)->assertOk()->assertJsonPath('data.state', 'CLOSED');
        }
        $this->assertSame(1, RecordFixtureQuery::table('manifest_parcels')->where('manifest_id', $manifestId)->count());
        $this->assertSame(1, RecordFixtureQuery::table('consignment_status_events')->where(['manifest_id' => $manifestId, 'parcel_id' => $parcel])->count());
        $this->assertSame(1, RecordFixtureQuery::table('outbox_events')->where(['aggregate_id' => $manifestId, 'event_type' => 'manifest.closed'])->count());
    }

    public function test_route_outbound_and_delivery_assignment_use_configured_resources(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-OPS', 'manifest-operations');
        $destination = $this->node($tenant['hq_id'], 'MAN-DEST');
        [$consignmentId, $parcelId, $parcelNumber] = $this->operationalConsignment($tenant['hq_id'], $actor['user_id'], $node, $destination, 'ROU', $node);
        [$planId, $legId] = $this->routedPlan($tenant['hq_id'], $actor['user_id'], $consignmentId, $node, $destination);
        RecordFixtureQuery::table('parcels')->where('parcel_id', $parcelId)->update(['active_route_plan_id' => $planId, 'active_route_plan_leg_id' => $legId]);
        $service = $this->app->make(ManifestService::class);
        $options = $service->contextOptions($principal, $node);
        $routeOption = collect($options['contexts'])->firstWhere('context_key', 'OF:'.$legId);
        $this->assertNotNull($routeOption);
        $this->assertSame($parcelId, $service->eligible($principal, $node, $this->createFromSelection($service, $principal, $node, $routeOption['selection'])['manifest_id'], [])->items()[0]['parcel_id']);
        $manifest = collect($service->list($principal, $node, [])->items())->first(fn ($row): bool => $row->manifest->manifest_status === 'OF')->manifest;
        $added = $service->add($principal, $node, (string) $manifest->manifest_id, ['expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$parcelNumber]], (string) random_int(1, 2000000000));
        $open = $service->validate($principal, $node, (string) $manifest->manifest_id, $added['detail']['version'], (string) random_int(1, 2000000000));
        $closed = $service->confirm($principal, $node, (string) $manifest->manifest_id, $open['version'], (string) random_int(1, 2000000000));
        $this->assertSame('CLOSED', $closed['state']);
        $this->assertSame('OF', RecordFixtureQuery::table('parcels')->where('parcel_id', $parcelId)->value('current_status'));
        $this->assertSame('OUTBOUND_CONFIRMED', RecordFixtureQuery::table('route_plan_legs')->where('route_plan_leg_id', $legId)->value('status'));
        [$deliveryConsignment, $deliveryParcel, $deliveryNumber] = $this->operationalConsignment($tenant['hq_id'], $actor['user_id'], $node, $node, 'IR', $node);
        $secondDeliveryParcel = (string) random_int(1, 2000000000);
        $secondDeliveryNumber = $deliveryNumber.'-SECOND';
        RecordFixtureQuery::table('parcels')->insert([
            'parcel_id' => $secondDeliveryParcel,
            'hq_id' => $tenant['hq_id'],
            'consignment_id' => $deliveryConsignment,
            'parcel_number' => $secondDeliveryNumber,
            'current_status' => 'IR',
            'current_node_id' => $node,
            'current_custody_type' => 'NODE',
            'current_custodian_id' => $node,
            'version' => 1,
            'weight_kg' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $driver = $this->driver($tenant['hq_id'], $node, 'DELIVERY');
        $vehicle = $this->vehicle($tenant['hq_id'], $node);
        $this->app->make(DeliveryTaskService::class)->ensurePending($principal, $node, $deliveryConsignment);
        $delivery = $service->create($principal, $node, [
            'expected_version' => 0,
            'manifest_status' => 'OD',
            'context_key' => 'OD:'.$node,
            'assigned_driver_id' => $driver,
        ], (string) random_int(1, 2000000000));
        $deliveryAdded = $service->add($principal, $node, $delivery['manifest_id'], ['expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$deliveryNumber, $secondDeliveryNumber]], (string) random_int(1, 2000000000));
        $deliveryOpen = $service->validate($principal, $node, $delivery['manifest_id'], $deliveryAdded['detail']['version'], (string) random_int(1, 2000000000));
        $deliveryClosed = $service->confirm($principal, $node, $delivery['manifest_id'], $deliveryOpen['version'], (string) random_int(1, 2000000000));
        $this->assertSame('OD', RecordFixtureQuery::table('parcels')->where('parcel_id', $deliveryParcel)->value('current_status'));
        $this->assertSame('OD', RecordFixtureQuery::table('parcels')->where('parcel_id', $secondDeliveryParcel)->value('current_status'));
        $this->assertSame('DELIVERY_DRIVER', RecordFixtureQuery::table('parcels')->where('parcel_id', $deliveryParcel)->value('current_custody_type'));
        $this->assertDatabaseHasPublic('delivery_tasks', [
            'consignment_id' => $deliveryConsignment,
            'manifest_id' => $deliveryClosed['manifest_id'],
            'assigned_driver_id' => $driver,
            'status' => 'IN_PROGRESS',
        ]);
    }

    public function test_zero_success_releases_rows_and_outbox_failure_rolls_back_confirmation(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-TX', 'manifest-transactions');
        [, $eligible, $ineligible] = $this->consignment($tenant['hq_id'], $actor['user_id'], $node);
        $service = $this->app->make(ManifestService::class);
        $failedManifest = $service->create($principal, $node, ['expected_version' => 0, 'manifest_status' => 'IR', 'context_key' => 'IR:PICKUP:'.$node], (string) random_int(1, 2000000000));
        $failedNumber = (string) RecordFixtureQuery::table('parcels')->where('parcel_id', $ineligible)->value('parcel_number');
        $failedAdd = $service->add($principal, $node, $failedManifest['manifest_id'], ['expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$failedNumber]], (string) random_int(1, 2000000000));
        $failedOpen = $service->validate($principal, $node, $failedManifest['manifest_id'], $failedAdd['detail']['version'], (string) random_int(1, 2000000000));
        try {
            $service->confirm($principal, $node, $failedManifest['manifest_id'], $failedOpen['version'], (string) random_int(1, 2000000000));
            $this->fail('A zero-success confirmation must report a domain rejection.');
        } catch (ApiException|ManifestRuleViolation $exception) {
            $this->assertSame(ApiErrorCode::ManifestNoSuccessfulParcels, $exception->errorCode);
        }
        $this->assertDatabaseHasPublic('manifests', ['manifest_id' => $failedManifest['manifest_id'], 'state' => 'OPEN', 'version' => $failedOpen['version'] + 1]);
        $this->assertDatabaseHasPublic('manifest_parcels', [
            'manifest_id' => $failedManifest['manifest_id'],
            'parcel_id' => $ineligible,
            'manifest_parcel_status' => 'FAILED',
            'active_slot' => null,
        ]);
        $this->assertDatabaseMissingPublic('outbox_events', ['aggregate_id' => $failedManifest['manifest_id'], 'event_type' => 'manifest.closed']);
        $rollbackManifest = $service->create($principal, $node, ['expected_version' => 0, 'manifest_status' => 'IR', 'context_key' => 'IR:PICKUP:'.$node], (string) random_int(1, 2000000000));
        $eligibleNumber = (string) RecordFixtureQuery::table('parcels')->where('parcel_id', $eligible)->value('parcel_number');
        $rollbackAdd = $service->add($principal, $node, $rollbackManifest['manifest_id'], ['expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$eligibleNumber]], (string) random_int(1, 2000000000));
        $rollbackOpen = $service->validate($principal, $node, $rollbackManifest['manifest_id'], $rollbackAdd['detail']['version'], (string) random_int(1, 2000000000));
        $this->app->instance(OutboxWriterInterface::class, new class implements OutboxWriterInterface
        {
            public function write(
                ?string $hqId,
                string $aggregateType,
                string $aggregateId,
                string $eventType,
                string $correlationId,
                array $payload,
                int $eventVersion = 1,
                ?string $causationId = null,
            ): void {
                throw new RuntimeException('Injected outbox failure.');
            }
        });
        try {
            $this->app->make(ManifestService::class)->confirm($principal, $node, $rollbackManifest['manifest_id'], $rollbackOpen['version'], (string) random_int(1, 2000000000));
            $this->fail('Outbox failure must roll back the whole confirmation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected outbox failure.', $exception->getMessage());
        }
        $this->assertDatabaseHasPublic('manifests', ['manifest_id' => $rollbackManifest['manifest_id'], 'state' => 'OPEN', 'version' => $rollbackOpen['version']]);
        $this->assertDatabaseHasPublic('manifest_parcels', [
            'manifest_id' => $rollbackManifest['manifest_id'],
            'parcel_id' => $eligible,
            'manifest_parcel_status' => 'VALIDATED',
        ]);
        $this->assertSame('PU', RecordFixtureQuery::table('parcels')->where('parcel_id', $eligible)->value('current_status'));
        $this->assertDatabaseMissingPublic('audit_events', ['target_id' => $rollbackManifest['manifest_id'], 'action_key' => 'MANIFEST_CONFIRMED']);
    }

    public function test_audit_failure_rolls_back_status_custody_manifest_and_outbox(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-AUDIT-TX', 'manifest-audit-rollback');
        [, $eligible] = $this->consignment($tenant['hq_id'], $actor['user_id'], $node);
        $number = (string) RecordFixtureQuery::table('parcels')->where('parcel_id', $eligible)->value('parcel_number');
        $service = $this->app->make(ManifestService::class);
        $manifest = $service->create($principal, $node, ['expected_version' => 0, 'manifest_status' => 'IR', 'context_key' => 'IR:PICKUP:'.$node], (string) random_int(1, 2000000000));
        $added = $service->add($principal, $node, $manifest['manifest_id'], ['expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$number]], (string) random_int(1, 2000000000));
        $open = $service->validate($principal, $node, $manifest['manifest_id'], $added['detail']['version'], (string) random_int(1, 2000000000));
        $this->app->instance(AuditWriterInterface::class, new class implements AuditWriterInterface
        {
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
                throw new RuntimeException('Injected audit failure.');
            }
        });
        try {
            $this->app->make(ManifestService::class)->confirm($principal, $node, $manifest['manifest_id'], $open['version'], (string) random_int(1, 2000000000));
            $this->fail('Audit failure must roll back the whole confirmation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected audit failure.', $exception->getMessage());
        }
        $this->assertDatabaseHasPublic('manifests', ['manifest_id' => $manifest['manifest_id'], 'state' => 'OPEN', 'version' => $open['version']]);
        $this->assertDatabaseHasPublic('manifest_parcels', ['manifest_id' => $manifest['manifest_id'], 'parcel_id' => $eligible, 'manifest_parcel_status' => 'VALIDATED']);
        $this->assertSame('PU', RecordFixtureQuery::table('parcels')->where('parcel_id', $eligible)->value('current_status'));
        $this->assertDatabaseMissingPublic('parcel_custody_events', ['manifest_id' => $manifest['manifest_id']]);
        $this->assertDatabaseMissingPublic('outbox_events', ['aggregate_id' => $manifest['manifest_id'], 'event_type' => 'manifest.closed']);
    }

    public function test_context_contract_exposes_all_eleven_manifest_targets_with_related_nodes_and_without_raw_operational_ids(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-MATRIX', 'manifest-matrix');
        $options = $this->app->make(ManifestService::class)->contextOptions($principal, $node);
        $this->assertSame(['PD', 'PU', 'NPU', 'IR', 'ROU', 'OF', 'OS', 'CI', 'OD', 'OK', 'NOK'], array_column($options['transition_contracts'], 'target_status'));
        foreach ($options['contexts'] as $option) {
            $this->assertSame([], array_intersect(['origin_node_id', 'destination_node_id', 'route_plan_id', 'route_plan_leg_id'], array_keys($option['selection'])));
            $this->assertSame(0, $option['selection']['expected_version']);
            $this->assertNotEmpty($option['related_node']['node_title']);
            $this->assertNotEmpty($option['target_node']['node_title']);
            $this->assertNotEmpty($option['target_node']['node_code']);
            $this->assertContains($option['related_node_role'], ['SOURCE', 'DESTINATION', 'COUNTERPARTY']);
        }
    }

    public function test_all_targets_create_empty_drafts_with_explicit_active_target_node(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-EMPTY', 'manifest-empty');
        $targetNode = $this->node($tenant['hq_id'], 'MAN-EMPTY-TARGET');
        $pickupDriver = $this->driver($tenant['hq_id'], $targetNode, 'PICKUP');
        $linehaulDriver = $this->driver($tenant['hq_id'], $targetNode, 'LINEHAUL');
        $deliveryDriver = $this->driver($tenant['hq_id'], $targetNode, 'DELIVERY');
        RecordFixtureQuery::table('drivers')->where('driver_id', $linehaulDriver)->update(['availability_status' => 'ON_MISSION']);
        RecordFixtureQuery::table('drivers')->where('driver_id', $deliveryDriver)->update(['availability_status' => 'ON_MISSION']);
        $vehicle = $this->vehicle($tenant['hq_id'], $node);
        $service = $this->app->make(ManifestService::class);
        $options = $service->contextOptions($principal, $node);
        $this->assertSame(['AVAILABLE', 'ON_MISSION'], collect($options['drivers'])->pluck('availability_status')->unique()->sort()->values()->all());
        $this->assertContains($targetNode, array_column($options['target_nodes'], 'node_id'));
        foreach (['PD', 'PU', 'NPU', 'IR', 'ROU', 'OF', 'OS', 'CI', 'OD', 'OK', 'NOK'] as $target) {
            $option = collect($options['contexts'])->first(fn (array $candidate): bool => $candidate['manifest_status'] === $target && $candidate['target_node']['node_id'] === $targetNode);
            $this->assertNotNull($option, "Missing empty-draft context for {$target}.");
            $selection = $option['selection'];
            if ($target === 'PD') {
                $selection['assigned_driver_id'] = $pickupDriver;
            }
            if ($target === 'OS') {
                $selection['assigned_driver_id'] = $linehaulDriver;
                $selection['assigned_vehicle_id'] = $vehicle;
            }
            if ($target === 'OD') {
                $selection['assigned_driver_id'] = $deliveryDriver;
            }
            $manifest = $service->create($principal, $node, $selection, (string) random_int(1, 2000000000));
            $this->assertSame('DRAFT', $manifest['state']);
            $this->assertSame(0, $manifest['total_count']);
            $this->assertSame($node, $manifest['issuing_node']['node_id']);
            $this->assertSame($targetNode, $manifest['target_node']['node_id']);
        }
    }

    public function test_manifest_list_returns_node_references_and_historical_missing_target_as_null(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-LIST-NODES', 'manifest-list-nodes');
        $service = $this->app->make(ManifestService::class);
        $manifest = $service->create($principal, $node, [
            'expected_version' => 0,
            'manifest_status' => 'IR',
            'context_key' => 'IR:PICKUP:'.$node,
            'target_node_id' => $node,
        ], (string) random_int(1, 2000000000));
        $this->assertSame('MAN-LIST-NODES', $manifest['issuing_node']['node_code']);
        $this->assertSame('MAN-LIST-NODES', $manifest['target_node']['node_title']);
        RecordFixtureQuery::table('manifests')->where('manifest_id', $manifest['manifest_id'])->update(['destination_node_id' => null]);
        $listed = $service->listItem(ManifestRecord::query()->where('manifest_id', $manifest['manifest_id'])->firstOrFail());
        $this->assertSame($node, $listed['issuing_node']['node_id']);
        $this->assertNull($listed['target_node']);
    }

    public function test_driver_references_exclude_inactive_unavailable_and_cross_hq_drivers(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-DRIVER-FILTER', 'manifest-driver-filter');
        $active = $this->driver($tenant['hq_id'], $node, 'LINEHAUL');
        $inactive = $this->driver($tenant['hq_id'], $node, 'LINEHAUL');
        $unavailable = $this->driver($tenant['hq_id'], $node, 'LINEHAUL');
        RecordFixtureQuery::table('drivers')->where('driver_id', $active)->update(['availability_status' => 'ON_MISSION']);
        RecordFixtureQuery::table('drivers')->where('driver_id', $inactive)->update(['status' => 'INACTIVE']);
        RecordFixtureQuery::table('drivers')->where('driver_id', $unavailable)->update(['availability_status' => 'TEMPORARILY_INACTIVE']);
        [$foreignTenant, $foreignActor, $foreignNode] = $this->context('MAN-DRIVER-FOREIGN', 'manifest-driver-foreign');
        $foreign = $this->driver($foreignTenant['hq_id'], $foreignNode, 'LINEHAUL');
        $service = $this->app->make(ManifestService::class);
        $options = $service->contextOptions($principal, $node);
        $ids = array_column($options['drivers'], 'driver_id');
        $this->assertContains($active, $ids);
        $this->assertNotContains($inactive, $ids);
        $this->assertNotContains($unavailable, $ids);
        $this->assertNotContains($foreign, $ids);
        $os = collect($options['contexts'])->firstWhere('manifest_status', 'OS')['selection'];
        $os['assigned_driver_id'] = $foreign;
        $os['assigned_vehicle_id'] = $this->vehicle($tenant['hq_id'], $node);
        try {
            $service->create($principal, $node, $os, (string) random_int(1, 2000000000));
            $this->fail('A cross-HQ Driver must be rejected.');
        } catch (ApiException|ManifestRuleViolation $exception) {
            $this->assertSame(ApiErrorCode::DriverOutOfScope, $exception->errorCode);
        }
    }

    public function test_pickup_driver_is_reusable_across_partial_pd_manifests_and_aggregate_reaches_full(): void
    {
        [$tenant, $actor, $node, $principal] = $this->context('MAN-PD-REUSE', 'manifest-pd-reuse');
        [$consignment, $firstParcel, $firstNumber] = $this->operationalConsignment($tenant['hq_id'], $actor['user_id'], $node, $node, 'CFM', $node);
        $numbers = [$firstNumber];
        foreach ([2, 3, 4] as $suffix) {
            $parcelId = (string) random_int(1, 2000000000);
            $parcelNumber = substr($firstNumber, 0, -2).sprintf('%02d', $suffix);
            RecordFixtureQuery::table('parcels')->insert([
                'parcel_id' => $parcelId,
                'hq_id' => $tenant['hq_id'],
                'consignment_id' => $consignment,
                'parcel_number' => $parcelNumber,
                'current_status' => 'CFM',
                'current_node_id' => $node,
                'current_custody_type' => 'NODE',
                'current_custodian_id' => $node,
                'version' => 1,
                'weight_kg' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $numbers[] = $parcelNumber;
        }
        $driver = $this->driver($tenant['hq_id'], $node, 'PICKUP');
        $service = $this->app->make(ManifestService::class);
        $first = $service->create($principal, $node, [
            'expected_version' => 0,
            'manifest_status' => 'PD',
            'context_key' => 'PD:'.$node,
            'assigned_driver_id' => $driver,
        ], (string) random_int(1, 2000000000));
        $added = $service->add($principal, $node, $first['manifest_id'], ['expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => array_slice($numbers, 0, 3)], (string) random_int(1, 2000000000));
        $open = $service->validate($principal, $node, $first['manifest_id'], $added['detail']['version'], (string) random_int(1, 2000000000));
        $service->confirm($principal, $node, $first['manifest_id'], $open['version'], (string) random_int(1, 2000000000));
        $aggregate = RecordFixtureQuery::table('consignments')->where('consignment_id', $consignment)->first();
        $this->assertSame('PD', $aggregate->current_status);
        $this->assertSame('PARTIAL', $aggregate->aggregate_mode);
        $partialCounts = json_decode((string) $aggregate->parcel_status_counts, true);
        $this->assertSame(1, $partialCounts['CFM']);
        $this->assertSame(3, $partialCounts['PD']);
        $this->assertSame('ON_MISSION', RecordFixtureQuery::table('drivers')->where('driver_id', $driver)->value('availability_status'));
        $pickupDrivers = collect($service->contextOptions($principal, $node)['drivers']);
        $visibleDriver = $pickupDrivers->firstWhere('driver_id', $driver);
        $this->assertNotNull($visibleDriver);
        $this->assertSame('ON_MISSION', $visibleDriver['availability_status']);
        $second = $this->closeSingleManifest($service, $principal, $node, [
            'expected_version' => 0,
            'manifest_status' => 'PD',
            'context_key' => 'PD:'.$node,
            'assigned_driver_id' => $driver,
        ], $numbers[3]);
        $this->assertSame('CLOSED', $second['state']);
        $aggregate = RecordFixtureQuery::table('consignments')->where('consignment_id', $consignment)->first();
        $this->assertSame('PD', $aggregate->current_status);
        $this->assertSame('FULL', $aggregate->aggregate_mode);
        $this->assertSame(['PD' => 4], json_decode((string) $aggregate->parcel_status_counts, true));
        $this->assertSame('ON_MISSION', RecordFixtureQuery::table('drivers')->where('driver_id', $driver)->value('availability_status'));
        $detail = $this->app->make(ConsignmentService::class)->get($principal, $node, $consignment);
        $this->assertSame('FULL', $detail['aggregate']['mode']);
        $this->assertSame(4, $detail['aggregate']['target_count']);
        $this->assertDatabaseHasPublic('consignment_status_events', [
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
        [$consignment, $parcel, $number] = $this->operationalConsignment($tenant['hq_id'], $actor['user_id'], $origin, $destination, 'CFM', $origin);
        $pickupDriver = $this->driver($tenant['hq_id'], $origin, 'PICKUP');
        $service = $this->app->make(ManifestService::class);
        $this->closeSingleManifest($service, $principal, $origin, [
            'expected_version' => 0,
            'manifest_status' => 'PD',
            'context_key' => 'PD:'.$origin,
            'assigned_driver_id' => $pickupDriver,
        ], $number);
        $this->assertSame('PD', RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $pickupTask = RecordFixtureQuery::table('pickup_tasks')->where('consignment_id', $consignment)->first();
        $this->closeSingleManifest($service, $principal, $origin, [
            'expected_version' => 0,
            'manifest_status' => 'PU',
            'context_key' => 'PICKUP:'.$pickupTask->pickup_task_id.':PU',
            'assigned_driver_id' => $pickupDriver,
        ], $number);
        $this->assertSame('PU', RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $this->closeSingleManifest($service, $principal, $origin, ['expected_version' => 0, 'manifest_status' => 'IR', 'context_key' => 'IR:PICKUP:'.$origin], $number);
        $this->assertSame('IR', RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        [$plan, $leg] = $this->routedPlan($tenant['hq_id'], $actor['user_id'], $consignment, $origin, $destination);
        $this->closeSingleManifest($service, $principal, $origin, ['expected_version' => 0, 'manifest_status' => 'ROU', 'context_key' => 'ROU:'.$origin], $number);
        $this->assertSame('ROU', RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $of = $this->closeSingleManifest($service, $principal, $origin, ['expected_version' => 0, 'manifest_status' => 'OF', 'context_key' => 'OF:'.$leg], $number);
        $this->assertSame('OF', RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $linehaulDriver = $this->driver($tenant['hq_id'], $origin, 'LINEHAUL');
        $vehicle = $this->vehicle($tenant['hq_id'], $origin);
        $os = $this->closeSingleManifest($service, $principal, $origin, [
            'expected_version' => 0,
            'manifest_status' => 'OS',
            'context_key' => 'OS:OF:'.$of['manifest_id'],
            'assigned_driver_id' => $linehaulDriver,
            'assigned_vehicle_id' => $vehicle,
        ], $number);
        $this->assertSame('OS', RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $this->closeSingleManifest($service, $principal, $destination, [
            'expected_version' => 0,
            'manifest_status' => 'IR',
            'context_key' => 'IR:OS:'.$os['manifest_id'],
            'assigned_driver_id' => $linehaulDriver,
            'assigned_vehicle_id' => $vehicle,
        ], $number);
        $this->assertSame('IR', RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $this->assertSame('COMPLETED', RecordFixtureQuery::table('route_plans')->where('route_plan_id', $plan)->value('status'));
        $deliveryDriver = $this->driver($tenant['hq_id'], $destination, 'DELIVERY');
        $this->closeSingleManifest($service, $principal, $destination, [
            'expected_version' => 0,
            'manifest_status' => 'OD',
            'context_key' => 'OD:'.$destination,
            'assigned_driver_id' => $deliveryDriver,
        ], $number);
        $this->assertSame('OD', RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $deliveryTask = RecordFixtureQuery::table('delivery_tasks')->where('consignment_id', $consignment)->first();
        $this->closeSingleManifest($service, $principal, $destination, [
            'expected_version' => 0,
            'manifest_status' => 'OK',
            'context_key' => 'DELIVERY:'.$deliveryTask->delivery_task_id.':OK',
            'assigned_driver_id' => $deliveryDriver,
        ], $number);
        $this->assertSame('OK', RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $this->assertSame('RECIPIENT', RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('current_custody_type'));
        [$nokConsignment, $nokParcel, $nokNumber] = $this->operationalConsignment($tenant['hq_id'], $actor['user_id'], $destination, $destination, 'OD', $destination);
        $nokDriver = $this->driver($tenant['hq_id'], $destination, 'DELIVERY');
        RecordFixtureQuery::table('parcels')->where('parcel_id', $nokParcel)->update(['current_node_id' => null, 'current_custody_type' => 'DELIVERY_DRIVER', 'current_custodian_id' => $nokDriver]);
        $nokTask = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('delivery_tasks')->insert([
            'delivery_task_id' => $nokTask,
            'hq_id' => $tenant['hq_id'],
            'consignment_id' => $nokConsignment,
            'node_id' => $destination,
            'assigned_driver_id' => $nokDriver,
            'status' => 'IN_PROGRESS',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $nok = $service->create($principal, $destination, [
            'expected_version' => 0,
            'manifest_status' => 'NOK',
            'context_key' => 'DELIVERY:'.$nokTask.':NOK',
            'assigned_driver_id' => $nokDriver,
        ], (string) random_int(1, 2000000000));
        $nokAdded = $service->add($principal, $destination, $nok['manifest_id'], ['expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$nokNumber]], (string) random_int(1, 2000000000));
        $nokOpen = $service->validate($principal, $destination, $nok['manifest_id'], $nokAdded['detail']['version'], (string) random_int(1, 2000000000));
        $service->confirm($principal, $destination, $nok['manifest_id'], $nokOpen['version'], (string) random_int(1, 2000000000), 'RECIPIENT_UNAVAILABLE', 'Recipient could not receive the Parcel.');
        $pending = $service->exception($principal, $destination, $nok['manifest_id']);
        $reviewer = $this->user($tenant['hq_id'], 'manifest-matrix-reviewer');
        $this->assignManifestRoles($tenant['hq_id'], $reviewer['user_id']);
        $reviewerPrincipal = new AuthenticatedPrincipal($reviewer['user_id'], (string) random_int(1, 2000000000), $tenant['hq_id'], false);
        $service->approveException($reviewerPrincipal, $destination, $nok['manifest_id'], $pending['manifest_version'], $pending['current_exception']['version'], null, (string) random_int(1, 2000000000));
        $this->assertSame('NOK', RecordFixtureQuery::table('parcels')->where('parcel_id', $nokParcel)->value('current_status'));
    }

    public function test_intermediate_os_to_ci_to_of_and_final_os_to_ir_follow_ordered_route_evidence(): void
    {
        [$tenant, $actor, $origin, $principal] = $this->context('MAN-CI', 'manifest-ci');
        $intermediate = $this->node($tenant['hq_id'], 'MAN-CI-MID');
        $destination = $this->node($tenant['hq_id'], 'MAN-CI-FINAL');
        [$consignment, $parcel, $number] = $this->operationalConsignment($tenant['hq_id'], $actor['user_id'], $origin, $destination, 'ROU', $origin);
        [$plan, $firstLeg, $secondLeg] = $this->routedTwoLegPlan($tenant['hq_id'], $actor['user_id'], $consignment, $origin, $intermediate, $destination);
        RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->update(['active_route_plan_id' => $plan, 'active_route_plan_leg_id' => $firstLeg]);
        $service = $this->app->make(ManifestService::class);
        $firstOf = $this->closeSingleManifest($service, $principal, $origin, ['expected_version' => 0, 'manifest_status' => 'OF', 'context_key' => 'OF:'.$firstLeg], $number);
        $firstDriver = $this->driver($tenant['hq_id'], $origin, 'LINEHAUL');
        $firstVehicle = $this->vehicle($tenant['hq_id'], $origin);
        $firstOs = $this->closeSingleManifest($service, $principal, $origin, [
            'expected_version' => 0,
            'manifest_status' => 'OS',
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
        $this->assertSame('CI', RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $this->assertSame($intermediate, RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('current_node_id'));
        $this->assertSame($secondLeg, RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('active_route_plan_leg_id'));
        $this->assertSame('RECEIVED', RecordFixtureQuery::table('route_plan_legs')->where('route_plan_leg_id', $firstLeg)->value('status'));
        $this->assertSame('TRANSIT_UNLOAD', $ci['manifest_type']);
        $secondOfOptions = $service->contextOptions($principal, $intermediate);
        $secondOfOption = collect($secondOfOptions['contexts'])->firstWhere('context_key', 'OF:'.$secondLeg);
        $this->assertNotNull($secondOfOption);
        $this->assertSame($destination, $secondOfOption['related_node']['node_id']);
        $secondOf = $this->closeSingleManifest($service, $principal, $intermediate, $secondOfOption['selection'], $number);
        $secondDriver = $this->driver($tenant['hq_id'], $intermediate, 'LINEHAUL');
        $secondVehicle = $this->vehicle($tenant['hq_id'], $intermediate);
        $secondOs = $this->closeSingleManifest($service, $principal, $intermediate, [
            'expected_version' => 0,
            'manifest_status' => 'OS',
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
        $this->assertSame('IR', RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $this->assertSame('COMPLETED', RecordFixtureQuery::table('route_plans')->where('route_plan_id', $plan)->value('status'));
        $this->assertDatabaseHasPublic('delivery_tasks', ['consignment_id' => $consignment, 'node_id' => $destination, 'status' => 'PENDING']);
        $this->assertSame(['RECEIVED', 'RECEIVED'], RecordFixtureQuery::table('route_plan_legs')->whereIn('route_plan_leg_id', [$firstLeg, $secondLeg])->orderBy('leg_order')->pluck('status')->all());
    }

    public function test_npu_exception_submit_self_review_reject_resubmit_and_approve_is_fail_closed(): void
    {
        [$tenant, $submitter, $node, $principal] = $this->context('MAN-EX', 'manifest-exception-submitter');
        [$consignment, $parcel, $number] = $this->operationalConsignment($tenant['hq_id'], $submitter['user_id'], $node, $node, 'PD', $node);
        $driver = $this->driver($tenant['hq_id'], $node, 'PICKUP');
        $task = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('pickup_tasks')->insert([
            'pickup_task_id' => $task,
            'hq_id' => $tenant['hq_id'],
            'consignment_id' => $consignment,
            'node_id' => $node,
            'assigned_driver_id' => $driver,
            'status' => 'ASSIGNED',
            'version' => 1,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->update(['current_node_id' => null, 'current_custody_type' => 'PICKUP_DRIVER', 'current_custodian_id' => $driver]);
        $service = $this->app->make(ManifestService::class);
        $manifest = $service->create($principal, $node, [
            'expected_version' => 0,
            'manifest_status' => 'NPU',
            'context_key' => "PICKUP:{$task}:NPU",
            'assigned_driver_id' => $driver,
        ], (string) random_int(1, 2000000000));
        $added = $service->add($principal, $node, $manifest['manifest_id'], ['expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$number]], (string) random_int(1, 2000000000));
        $open = $service->validate($principal, $node, $manifest['manifest_id'], $added['detail']['version'], (string) random_int(1, 2000000000));
        $pending = $service->confirm($principal, $node, $manifest['manifest_id'], $open['version'], (string) random_int(1, 2000000000), 'CUSTOMER_UNAVAILABLE', 'Customer did not attend the pickup window.');
        $this->assertSame('OPEN', $pending['state']);
        $this->assertSame('PD', RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $state = $service->exception($principal, $node, $manifest['manifest_id']);
        $this->assertSame('PENDING', $state['current_exception']['status']);
        $this->assertDatabaseMissingPublic('outbox_events', ['aggregate_id' => $manifest['manifest_id'], 'event_type' => 'manifest.closed']);
        try {
            $service->approveException($principal, $node, $manifest['manifest_id'], $state['manifest_version'], $state['current_exception']['version'], null, (string) random_int(1, 2000000000));
            $this->fail('The submitter must not approve their own Exception.');
        } catch (ApiException|ManifestRuleViolation $exception) {
            $this->assertSame(ApiErrorCode::ExceptionReviewerConflict, $exception->errorCode);
        }
        $reviewer = $this->user($tenant['hq_id'], 'manifest-exception-reviewer');
        $this->assignManifestRoles($tenant['hq_id'], $reviewer['user_id']);
        $reviewerPrincipal = new AuthenticatedPrincipal($reviewer['user_id'], (string) random_int(1, 2000000000), $tenant['hq_id'], false);
        $rejected = $service->rejectException($reviewerPrincipal, $node, $manifest['manifest_id'], $state['manifest_version'], $state['current_exception']['version'], 'Evidence needs correction.', (string) random_int(1, 2000000000));
        $rejectedState = $service->exception($principal, $node, $manifest['manifest_id']);
        $this->assertSame('REJECTED', $rejectedState['current_exception']['status']);
        $this->assertSame('OPEN', $rejected['state']);
        $resubmitted = $service->resubmitException($principal, $node, $manifest['manifest_id'], $rejectedState['manifest_version'], $rejectedState['current_exception']['version'], 'CUSTOMER_UNAVAILABLE', 'Corrected operational evidence.', (string) random_int(1, 2000000000));
        $resubmittedState = $service->exception($principal, $node, $manifest['manifest_id']);
        $this->assertSame(2, $resubmittedState['current_exception']['submission_sequence']);
        $this->assertCount(1, $resubmittedState['previous_attempts']);
        $closed = $service->approveException($reviewerPrincipal, $node, $manifest['manifest_id'], $resubmitted['version'], $resubmittedState['current_exception']['version'], 'Approved after correction.', (string) random_int(1, 2000000000));
        $this->assertSame('CLOSED', $closed['state']);
        $this->assertSame('NPU', RecordFixtureQuery::table('parcels')->where('parcel_id', $parcel)->value('current_status'));
        $this->assertDatabaseHasPublic('operational_exception_history', ['action' => 'APPROVED']);
    }

    public function test_multi_consignment_os_and_ir_share_physical_context_and_preserve_per_consignment_evidence(): void
    {
        [$tenant, $actor, $origin, $principal] = $this->context('MAN-MOVE', 'manifest-movement');
        $destination = $this->node($tenant['hq_id'], 'MAN-MOVE-DEST');
        [$first, $second, $physicalLeg] = $this->compatibleMovementConsignments($tenant['hq_id'], $actor['user_id'], $origin, $destination);
        [$incompatibleConsignment, $incompatibleParcel, $incompatibleNumber] = $this->operationalConsignment($tenant['hq_id'], $actor['user_id'], $origin, $destination, 'OF', $origin);
        [$incompatiblePlan, $incompatibleLeg] = $this->routedPlan($tenant['hq_id'], $actor['user_id'], $incompatibleConsignment, $origin, $destination);
        RecordFixtureQuery::table('route_plan_legs')->where('route_plan_leg_id', $incompatibleLeg)->update(['status' => 'OUTBOUND_CONFIRMED', 'updated_at' => now()]);
        RecordFixtureQuery::table('parcels')->where('parcel_id', $incompatibleParcel)->update(['active_route_plan_id' => $incompatiblePlan, 'active_route_plan_leg_id' => $incompatibleLeg]);
        $sourceOutboundManifest = $this->closedOutboundEvidence($tenant['hq_id'], $actor['user_id'], $origin, $destination, [$first, $second]);
        $driver = $this->driver($tenant['hq_id'], $origin, 'LINEHAUL');
        $vehicle = $this->vehicle($tenant['hq_id'], $origin);
        $service = $this->app->make(ManifestService::class);
        $key = 'OS:OF:'.$sourceOutboundManifest;
        $manifest = $service->create($principal, $origin, [
            'expected_version' => 0,
            'manifest_status' => 'OS',
            'context_key' => $key,
            'assigned_driver_id' => $driver,
            'assigned_vehicle_id' => $vehicle,
        ], (string) random_int(1, 2000000000));
        $added = $service->add($principal, $origin, $manifest['manifest_id'], [
            'expected_version' => 1,
            'input_source' => 'SCAN',
            'identifiers' => [$first['number'], $second['number'], $incompatibleNumber],
        ], (string) random_int(1, 2000000000));
        $open = $service->validate($principal, $origin, $manifest['manifest_id'], $added['detail']['version'], (string) random_int(1, 2000000000));
        $departed = $service->confirm($principal, $origin, $manifest['manifest_id'], $open['version'], (string) random_int(1, 2000000000));
        $this->assertSame(2, $departed['bucket_counts']['succeeded']);
        $this->assertSame(1, $departed['bucket_counts']['failed']);
        $this->assertDatabaseHasPublic('manifest_parcels', [
            'manifest_id' => $manifest['manifest_id'],
            'parcel_id' => $incompatibleParcel,
            'manifest_parcel_status' => 'FAILED',
            'failure_code' => ManifestEligibilityReason::PreviousMovementMismatch->value,
        ]);
        $this->assertSame(2, RecordFixtureQuery::table('manifest_parcels')->where('manifest_id', $manifest['manifest_id'])->distinct()->count('route_plan_leg_id'));
        $this->assertSame(['IN_TRANSIT'], RecordFixtureQuery::table('route_plan_legs')->whereIn('route_plan_leg_id', [$first['leg'], $second['leg']])->distinct()->pluck('status')->all());
        $this->assertSame('ON_MISSION', RecordFixtureQuery::table('drivers')->where('driver_id', $driver)->value('availability_status'));
        $this->assertSame('ON_MISSION', RecordFixtureQuery::table('vehicles')->where('vehicle_id', $vehicle)->value('availability_status'));
        $inbound = $service->create($principal, $destination, [
            'expected_version' => 0,
            'manifest_status' => 'IR',
            'context_key' => 'IR:OS:'.$manifest['manifest_id'],
            'assigned_driver_id' => $driver,
            'assigned_vehicle_id' => $vehicle,
        ], (string) random_int(1, 2000000000));
        $inboundAdded = $service->add($principal, $destination, $inbound['manifest_id'], ['expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$first['number'], $second['number']]], (string) random_int(1, 2000000000));
        $inboundOpen = $service->validate($principal, $destination, $inbound['manifest_id'], $inboundAdded['detail']['version'], (string) random_int(1, 2000000000));
        $received = $service->confirm($principal, $destination, $inbound['manifest_id'], $inboundOpen['version'], (string) random_int(1, 2000000000));
        $this->assertSame(2, $received['bucket_counts']['succeeded']);
        $this->assertSame(['RECEIVED'], RecordFixtureQuery::table('route_plan_legs')->whereIn('route_plan_leg_id', [$first['leg'], $second['leg']])->distinct()->pluck('status')->all());
        $this->assertSame('AVAILABLE', RecordFixtureQuery::table('drivers')->where('driver_id', $driver)->value('availability_status'));
        $this->assertSame('AVAILABLE', RecordFixtureQuery::table('vehicles')->where('vehicle_id', $vehicle)->value('availability_status'));
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
    }

    /** @param array<string, mixed> $selection @return array<string, mixed> */
    private function createFromSelection(ManifestService $service, AuthenticatedPrincipal $principal, string $node, array $selection): array
    {
        return $service->create($principal, $node, $selection, (string) random_int(1, 2000000000));
    }

    /** @param array<string,mixed> $selection @return array<string,mixed> */
    private function closeSingleManifest(
        ManifestService $service,
        AuthenticatedPrincipal $principal,
        string $node,
        array $selection,
        string $parcelNumber,
    ): array {
        $manifest = $service->create($principal, $node, $selection, (string) random_int(1, 2000000000));
        $added = $service->add($principal, $node, $manifest['manifest_id'], ['expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$parcelNumber]], (string) random_int(1, 2000000000));
        $open = $service->validate($principal, $node, $manifest['manifest_id'], $added['detail']['version'], (string) random_int(1, 2000000000));

        return $service->confirm($principal, $node, $manifest['manifest_id'], $open['version'], (string) random_int(1, 2000000000));
    }

    /** @return array{array<string,mixed>,array<string,mixed>,string,AuthenticatedPrincipal} */
    private function context(string $code, string $username): array
    {
        $tenant = $this->tenant($code);
        $actor = $this->user($tenant['hq_id'], $username);
        foreach (['Foundation', 'Consignment', 'Manifest', 'Parcel', 'Driver', 'LiveOperations'] as $module) {
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
        $area = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('areas')->insert([
            'area_id' => $area,
            'hq_id' => $tenant['hq_id'],
            'area_title' => $code,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $node = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('nodes')->insert([
            'node_id' => $node,
            'hq_id' => $tenant['hq_id'],
            'area_id' => $area,
            'node_code' => $code,
            'node_title' => $code,
            'node_type' => 'BRANCH',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $role = (string) RecordFixtureQuery::table('roles')->where('role_code', 'branch_manager')->value('role_id');
        $approver = (string) RecordFixtureQuery::table('roles')->where('role_code', 'manifest_approver')->value('role_id');
        $dispatcher = (string) RecordFixtureQuery::table('roles')->where('role_code', 'dispatcher')->value('role_id');
        $exceptionReviewer = (string) RecordFixtureQuery::table('roles')->where('role_code', 'exception_reviewer')->value('role_id');
        foreach ([$role, $approver, $dispatcher, $exceptionReviewer] as $roleId) {
            RecordFixtureQuery::table('user_role_assignments')->insert([
                'assignment_id' => (string) random_int(1, 2000000000),
                'hq_id' => $tenant['hq_id'],
                'user_id' => $actor['user_id'],
                'role_id' => $roleId,
                'scope_type' => 'TENANT',
                'scope_id' => null,
                'includes_descendants' => false,
                'status' => 'ACTIVE',
                'active_slot' => hash('sha256', "{$actor['user_id']}|{$roleId}|TENANT|-"),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->app->make(AuthorizationCacheInvalidator::class)->invalidateUser($actor['user_id']);

        return [
            $tenant,
            $actor,
            $node,
            new AuthenticatedPrincipal($actor['user_id'], (string) random_int(1, 2000000000), $tenant['hq_id'], false),
        ];
    }

    private function node(string $hqId, string $code): string
    {
        $area = (string) RecordFixtureQuery::table('areas')->where('hq_id', $hqId)->value('area_id');
        $node = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('nodes')->insert([
            'node_id' => $node,
            'hq_id' => $hqId,
            'area_id' => $area,
            'node_code' => $code,
            'node_title' => $code,
            'node_type' => 'BRANCH',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $node;
    }

    private function assignManifestRoles(string $hqId, string $userId): void
    {
        foreach (['branch_manager', 'manifest_approver', 'dispatcher', 'exception_reviewer'] as $roleCode) {
            $roleId = (string) RecordFixtureQuery::table('roles')->where('role_code', $roleCode)->value('role_id');
            RecordFixtureQuery::table('user_role_assignments')->insert([
                'assignment_id' => (string) random_int(1, 2000000000),
                'hq_id' => $hqId,
                'user_id' => $userId,
                'role_id' => $roleId,
                'scope_type' => 'TENANT',
                'scope_id' => null,
                'includes_descendants' => false,
                'status' => 'ACTIVE',
                'active_slot' => hash('sha256', "{$userId}|{$roleId}|TENANT|-"),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->app->make(AuthorizationCacheInvalidator::class)->invalidateUser($userId);
    }

    /** @param list<array<string,string>> $items */
    private function closedOutboundEvidence(string $hq, string $actor, string $origin, string $destination, array $items): string
    {
        $manifestId = (string) random_int(1, 2000000000);
        $firstLeg = RecordFixtureQuery::table('route_plan_legs as leg')->join('route_plans as plan', 'plan.id', '=', 'leg.route_plan_id')->where('leg.route_plan_leg_id', $items[0]['leg'])->first(['leg.source_route_definition_version_leg_id', 'plan.route_definition_version_id']);
        RecordFixtureQuery::table('manifests')->insert([
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
            $leg = RecordFixtureQuery::table('route_plan_legs as leg')->join('route_plans as plan', 'plan.id', '=', 'leg.route_plan_id')->where('leg.route_plan_leg_id', $item['leg'])->first(['leg.*', 'plan.route_definition_version_id']);
            RecordFixtureQuery::table('manifest_parcels')->insert([
                'manifest_parcel_id' => (string) random_int(1, 2000000000),
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
    private function compatibleMovementConsignments(string $hq, string $actor, string $origin, string $destination): array
    {
        [$firstConsignment, $firstParcel, $firstNumber] = $this->operationalConsignment($hq, $actor, $origin, $destination, 'OF', $origin);
        [$secondConsignment, $secondParcel, $secondNumber] = $this->operationalConsignment($hq, $actor, $origin, $destination, 'OF', $origin);
        [$firstPlan, $firstLeg] = $this->routedPlan($hq, $actor, $firstConsignment, $origin, $destination);
        RecordFixtureQuery::table('route_plan_legs')->where('route_plan_leg_id', $firstLeg)->update(['status' => 'OUTBOUND_CONFIRMED', 'updated_at' => now()]);
        $planEvidence = RecordFixtureQuery::table('route_plans')->where('route_plan_id', $firstPlan)->first();
        $legEvidence = RecordFixtureQuery::table('route_plan_legs')->where('route_plan_leg_id', $firstLeg)->first();
        $secondPlan = (string) random_int(1, 2000000000);
        $secondLeg = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('route_plans')->insert([
            'route_plan_id' => $secondPlan,
            'hq_id' => $hq,
            'consignment_id' => $secondConsignment,
            'route_definition_id' => $planEvidence->route_definition_id,
            'route_definition_version_id' => $planEvidence->route_definition_version_id,
            'status' => 'IN_PROGRESS',
            'active_slot' => hash('sha256', $hq.'|'.$secondConsignment.'|ACTIVE'),
            'version' => 1,
            'created_by' => $actor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        RecordFixtureQuery::table('route_plan_legs')->insert([
            'route_plan_leg_id' => $secondLeg,
            'hq_id' => $hq,
            'route_plan_id' => $secondPlan,
            'source_route_definition_leg_id' => $legEvidence->source_route_definition_leg_id,
            'source_route_definition_version_leg_id' => $legEvidence->source_route_definition_version_leg_id,
            'leg_order' => 1,
            'origin_node_id' => $origin,
            'destination_node_id' => $destination,
            'status' => 'OUTBOUND_CONFIRMED',
            'routed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        RecordFixtureQuery::table('parcels')->where('parcel_id', $firstParcel)->update(['active_route_plan_id' => $firstPlan, 'active_route_plan_leg_id' => $firstLeg]);
        RecordFixtureQuery::table('parcels')->where('parcel_id', $secondParcel)->update(['active_route_plan_id' => $secondPlan, 'active_route_plan_leg_id' => $secondLeg]);

        return [
            [
                'consignment' => $firstConsignment,
                'parcel' => $firstParcel,
                'number' => $firstNumber,
                'plan' => $firstPlan,
                'leg' => $firstLeg,
            ],
            [
                'consignment' => $secondConsignment,
                'parcel' => $secondParcel,
                'number' => $secondNumber,
                'plan' => $secondPlan,
                'leg' => $secondLeg,
            ],
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
        $id = (string) random_int(1, 2000000000);
        $number = 'CHB-OPS-'.Str::upper(Str::random(8));
        RecordFixtureQuery::table('consignments')->insert([
            'consignment_id' => $id,
            'hq_id' => $hq,
            'consignment_number' => $number,
            'initiator_id' => $actor,
            'pickup_node_id' => $pickupNode,
            'delivery_node_id' => $deliveryNode,
            'sender_contact_name' => 'Sender',
            'sender_mobile' => '09120000001',
            'sender_address_text' => 'Sender address',
            'sender_state' => 'Tehran',
            'sender_city' => 'Tehran',
            'receiver_contact_name' => 'Receiver',
            'receiver_mobile' => '09120000002',
            'receiver_address_text' => 'Receiver address',
            'receiver_state' => 'Tehran',
            'receiver_city' => 'Tehran',
            'service_type_id' => (string) random_int(1, 2000000000),
            'shipping_method_id' => (string) random_int(1, 2000000000),
            'weight_kg' => 1,
            'declared_value_amount' => 1000,
            'insurance_enabled' => false,
            'cod_enabled' => false,
            'payer' => 'SENDER',
            'payment_method' => 'CASH',
            'current_status' => $parcelStatus,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $parcel = (string) random_int(1, 2000000000);
        $parcelNumber = $number.'-01';
        RecordFixtureQuery::table('parcels')->insert([
            'parcel_id' => $parcel,
            'hq_id' => $hq,
            'consignment_id' => $id,
            'parcel_number' => $parcelNumber,
            'current_status' => $parcelStatus,
            'current_node_id' => $currentNode,
            'current_custody_type' => 'NODE',
            'current_custodian_id' => $currentNode,
            'version' => 1,
            'weight_kg' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$id, $parcel, $parcelNumber];
    }

    /** @return array{string,string} */
    private function routedPlan(string $hq, string $actor, string $consignment, string $origin, string $destination): array
    {
        $definition = (string) random_int(1, 2000000000);
        $definitionLeg = (string) random_int(1, 2000000000);
        $definitionVersion = (string) random_int(1, 2000000000);
        $versionLeg = (string) random_int(1, 2000000000);
        $plan = (string) random_int(1, 2000000000);
        $planLeg = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('route_definitions')->insert([
            'route_definition_id' => $definition,
            'hq_id' => $hq,
            'route_code' => 'RTE-'.Str::upper(Str::random(6)),
            'route_title' => 'Configured route',
            'status' => 'ACTIVE',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        RecordFixtureQuery::table('route_definition_legs')->insert([
            'route_definition_leg_id' => $definitionLeg,
            'hq_id' => $hq,
            'route_definition_id' => $definition,
            'leg_order' => 1,
            'origin_node_id' => $origin,
            'destination_node_id' => $destination,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        RecordFixtureQuery::table('route_definition_versions')->insert([
            'route_definition_version_id' => $definitionVersion,
            'hq_id' => $hq,
            'route_definition_id' => $definition,
            'version_number' => 1,
            'status' => 'PUBLISHED',
            'purpose' => 'TRUNK',
            'origin_node_id' => $origin,
            'destination_node_id' => $destination,
            'priority' => 1,
            'version' => 1,
            'created_by' => $actor,
            'published_by' => $actor,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        RecordFixtureQuery::table('route_definition_version_legs')->insert([
            'route_definition_version_leg_id' => $versionLeg,
            'hq_id' => $hq,
            'route_definition_version_id' => $definitionVersion,
            'leg_order' => 1,
            'origin_node_id' => $origin,
            'destination_node_id' => $destination,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        RecordFixtureQuery::table('route_definitions')->where('route_definition_id', $definition)->update(['published_version_id' => $definitionVersion]);
        RecordFixtureQuery::table('route_plans')->insert([
            'route_plan_id' => $plan,
            'hq_id' => $hq,
            'consignment_id' => $consignment,
            'route_definition_id' => $definition,
            'route_definition_version_id' => $definitionVersion,
            'status' => 'IN_PROGRESS',
            'active_slot' => hash('sha256', $hq.'|'.$consignment),
            'version' => 1,
            'created_by' => $actor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        RecordFixtureQuery::table('route_plan_legs')->insert([
            'route_plan_leg_id' => $planLeg,
            'hq_id' => $hq,
            'route_plan_id' => $plan,
            'source_route_definition_leg_id' => $definitionLeg,
            'source_route_definition_version_leg_id' => $versionLeg,
            'leg_order' => 1,
            'origin_node_id' => $origin,
            'destination_node_id' => $destination,
            'status' => 'ROUTED',
            'routed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
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
        $definition = (string) random_int(1, 2000000000);
        $definitionVersion = (string) random_int(1, 2000000000);
        $plan = (string) random_int(1, 2000000000);
        $definitionLegs = [(string) random_int(1, 2000000000), (string) random_int(1, 2000000000)];
        $versionLegs = [(string) random_int(1, 2000000000), (string) random_int(1, 2000000000)];
        $planLegs = [(string) random_int(1, 2000000000), (string) random_int(1, 2000000000)];
        RecordFixtureQuery::table('route_definitions')->insert([
            'route_definition_id' => $definition,
            'hq_id' => $hq,
            'route_code' => 'RTE-'.Str::upper(Str::random(6)),
            'route_title' => 'Two-leg configured route',
            'status' => 'ACTIVE',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        RecordFixtureQuery::table('route_definition_versions')->insert([
            'route_definition_version_id' => $definitionVersion,
            'hq_id' => $hq,
            'route_definition_id' => $definition,
            'version_number' => 1,
            'status' => 'PUBLISHED',
            'purpose' => 'TRUNK',
            'origin_node_id' => $origin,
            'destination_node_id' => $destination,
            'priority' => 1,
            'version' => 1,
            'created_by' => $actor,
            'published_by' => $actor,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ([[1, $origin, $intermediate], [2, $intermediate, $destination]] as [$order, $from, $to]) {
            $index = $order - 1;
            RecordFixtureQuery::table('route_definition_legs')->insert([
                'route_definition_leg_id' => $definitionLegs[$index],
                'hq_id' => $hq,
                'route_definition_id' => $definition,
                'leg_order' => $order,
                'origin_node_id' => $from,
                'destination_node_id' => $to,
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            RecordFixtureQuery::table('route_definition_version_legs')->insert([
                'route_definition_version_leg_id' => $versionLegs[$index],
                'hq_id' => $hq,
                'route_definition_version_id' => $definitionVersion,
                'leg_order' => $order,
                'origin_node_id' => $from,
                'destination_node_id' => $to,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        RecordFixtureQuery::table('route_definitions')->where('route_definition_id', $definition)->update(['published_version_id' => $definitionVersion]);
        RecordFixtureQuery::table('route_plans')->insert([
            'route_plan_id' => $plan,
            'hq_id' => $hq,
            'consignment_id' => $consignment,
            'route_definition_id' => $definition,
            'route_definition_version_id' => $definitionVersion,
            'status' => 'IN_PROGRESS',
            'active_slot' => hash('sha256', $hq.'|'.$consignment.'|ACTIVE'),
            'version' => 1,
            'created_by' => $actor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ([[1, $origin, $intermediate], [2, $intermediate, $destination]] as [$order, $from, $to]) {
            $index = $order - 1;
            RecordFixtureQuery::table('route_plan_legs')->insert([
                'route_plan_leg_id' => $planLegs[$index],
                'hq_id' => $hq,
                'route_plan_id' => $plan,
                'source_route_definition_leg_id' => $definitionLegs[$index],
                'source_route_definition_version_leg_id' => $versionLegs[$index],
                'leg_order' => $order,
                'origin_node_id' => $from,
                'destination_node_id' => $to,
                'status' => $order === 1 ? 'ROUTED' : 'PENDING',
                'routed_at' => $order === 1 ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [$plan, $planLegs[0], $planLegs[1]];
    }

    /** @return array{string,string,string} */
    private function consignment(string $hq, string $actor, string $node): array
    {
        $id = (string) random_int(1, 2000000000);
        $number = 'CHB-TEST-'.Str::upper(Str::random(8));
        $driver = $this->driver($hq, $node, 'PICKUP');
        RecordFixtureQuery::table('consignments')->insert([
            'consignment_id' => $id,
            'hq_id' => $hq,
            'consignment_number' => $number,
            'initiator_id' => $actor,
            'pickup_node_id' => $node,
            'sender_contact_name' => 'Sender',
            'sender_mobile' => '09120000001',
            'sender_address_text' => 'Sender address',
            'sender_state' => 'Tehran',
            'sender_city' => 'Tehran',
            'receiver_contact_name' => 'Receiver',
            'receiver_mobile' => '09120000002',
            'receiver_address_text' => 'Receiver address',
            'receiver_state' => 'Tehran',
            'receiver_city' => 'Tehran',
            'service_type_id' => (string) random_int(1, 2000000000),
            'shipping_method_id' => (string) random_int(1, 2000000000),
            'weight_kg' => 2,
            'declared_value_amount' => 1000,
            'insurance_enabled' => false,
            'cod_enabled' => false,
            'payer' => 'SENDER',
            'payment_method' => 'CASH',
            'current_status' => 'PU',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $eligible = (string) random_int(1, 2000000000);
        $ineligible = (string) random_int(1, 2000000000);
        foreach ([[$eligible, 'PU', '01'], [$ineligible, 'CFM', '02']] as [$parcel, $status, $suffix]) {
            RecordFixtureQuery::table('parcels')->insert([
                'parcel_id' => $parcel,
                'hq_id' => $hq,
                'consignment_id' => $id,
                'parcel_number' => "{$number}-{$suffix}",
                'current_status' => $status,
                'current_node_id' => null,
                'current_custody_type' => 'PICKUP_DRIVER',
                'current_custodian_id' => $driver,
                'version' => 1,
                'weight_kg' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        RecordFixtureQuery::table('pickup_tasks')->insert([
            'pickup_task_id' => (string) random_int(1, 2000000000),
            'hq_id' => $hq,
            'consignment_id' => $id,
            'node_id' => $node,
            'assigned_driver_id' => $driver,
            'status' => 'COMPLETED',
            'version' => 1,
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$number, $eligible, $ineligible];
    }

    private function driver(string $hq, string $node, string $capability): string
    {
        $id = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('drivers')->insert([
            'driver_id' => $id,
            'hq_id' => $hq,
            'driver_code' => 'DRV-'.Str::upper(Str::random(8)),
            'display_name' => 'Operational Driver',
            'home_node_id' => $node,
            'operational_type' => $capability,
            'status' => 'ACTIVE',
            'availability_status' => 'AVAILABLE',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        RecordFixtureQuery::table('driver_capabilities')->insert([
            'driver_capability_id' => (string) random_int(1, 2000000000),
            'hq_id' => $hq,
            'driver_id' => $id,
            'capability' => $capability,
            'created_at' => now(),
        ]);

        return $id;
    }

    private function vehicle(string $hq, string $node): string
    {
        $id = (string) random_int(1, 2000000000);
        $registration = 'IR-'.random_int(100000, 999999);
        RecordFixtureQuery::table('vehicles')->insert([
            'vehicle_id' => $id,
            'hq_id' => $hq,
            'vehicle_code' => 'VEH-'.Str::upper(Str::random(8)),
            'registration_number' => $registration,
            'plate_number' => $registration,
            'vehicle_type' => 'VAN',
            'home_node_id' => $node,
            'status' => 'ACTIVE',
            'availability_status' => 'AVAILABLE',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
