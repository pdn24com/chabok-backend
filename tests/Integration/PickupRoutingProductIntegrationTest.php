<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Operations\Application\CoveragePolicyService;
use Modules\Operations\Application\MovementService;
use Modules\Operations\Application\PickupTaskService;
use Modules\Operations\Application\RouteDefinitionService;

final class PickupRoutingProductIntegrationTest extends MySqlRedisTestCase
{
    private MutableOperationsAuthorization $authorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorization = new MutableOperationsAuthorization();
        $this->app->instance(AuthorizationContextResolver::class, $this->authorization);
    }

    public function test_route_plan_uses_only_published_coverage_and_connected_route_and_persists_immutable_evidence(): void
    {
        $fixture = $this->fixture('RESOLUTION');
        $this->authorization->accessibleNodeIds = $fixture['nodes'];
        $published = $this->publishConfiguration($fixture);
        $consignmentId = $this->consignment($fixture, 'ROUTE-OK');

        $service = $this->app->make(MovementService::class);
        $plan = $service->plan($fixture['actor'], $fixture['nodes'][0], $consignmentId, $this->cid('route-plan'));

        self::assertNull(DB::table('consignments')->where('consignment_id', $consignmentId)->value('delivery_node_id'));
        self::assertSame($published['route_version_id'], $plan['route_definition_version_id']);
        self::assertSame($published['gateway_id'], $plan['resolution_evidence']['destination_gateway']['node_id']);
        self::assertSame('CITY', $plan['resolution_evidence']['coverage_criterion_type']);
        self::assertSame('AVAILABLE', $plan['configuration_state']);
        self::assertCount(2, $plan['legs']);
        self::assertCount(2, $plan['resolution_evidence']['ordered_route_legs']);
        self::assertDatabaseHas('route_plan_resolution_evidence', [
            'route_plan_id' => $plan['route_plan_id'],
            'coverage_policy_version_id' => $published['coverage_version_id'],
            'coverage_rule_id' => $published['coverage_rule_id'],
            'destination_gateway_node_id' => $published['gateway_id'],
            'route_definition_version_id' => $published['route_version_id'],
        ]);
        self::assertNotNull(DB::table('route_plan_resolution_evidence')->where('route_plan_id', $plan['route_plan_id'])->value('matched_geography_evidence'));
        self::assertCount(2, json_decode((string) DB::table('route_plan_resolution_evidence')->where('route_plan_id', $plan['route_plan_id'])->value('ordered_route_legs'), true, 512, JSON_THROW_ON_ERROR));

        try {
            DB::table('route_plan_resolution_evidence')->where('route_plan_id', $plan['route_plan_id'])->update(['coverage_priority' => 999]);
            self::fail('Resolution evidence must be immutable.');
        } catch (\Throwable $exception) {
            self::assertStringContainsString('immutable route resolution evidence', $exception->getMessage());
        }

        $missing = $this->fixture('MISSING');
        $this->authorization->accessibleNodeIds = $missing['nodes'];
        $missingConsignment = $this->consignment($missing, 'NO-COVERAGE');
        $this->expectApi(ApiErrorCode::CoverageNotFound, fn () => $service->plan($missing['actor'], $missing['nodes'][0], $missingConsignment, $this->cid('no-coverage')));
        self::assertDatabaseMissing('route_plans', ['consignment_id' => $missingConsignment]);
    }

    public function test_coverage_and_route_ambiguity_fail_closed_without_partial_plans(): void
    {
        $fixture = $this->fixture('AMBIGUITY');
        $this->authorization->accessibleNodeIds = $fixture['nodes'];
        $published = $this->publishConfiguration($fixture);
        $coverage = $this->app->make(CoveragePolicyService::class);
        $policy = $coverage->create($fixture['actor'], ['policy_code' => 'AMB-COVERAGE', 'policy_title' => 'پوشش هم‌رتبه'], $this->cid('amb-policy'));
        $version = $coverage->createVersion($fixture['actor'], $policy['coverage_policy_id'], ['rules' => [[
            'target' => 'DESTINATION_GATEWAY', 'target_node_id' => $fixture['nodes'][1], 'priority' => 50,
            'criterion' => ['criterion_type' => 'CITY', 'city_id' => $fixture['city_id']],
        ]]], $this->cid('amb-version'));
        foreach (['validate', 'approve', 'publish'] as $index => $action) {
            $version = $coverage->transition($fixture['actor'], $policy['coverage_policy_id'], $version['coverage_policy_version_id'], $action, $index + 1, null, $this->cid("amb-{$action}"));
        }
        $consignment = $this->consignment($fixture, 'AMB-COVERAGE');
        $movement = $this->app->make(MovementService::class);
        $this->expectApi(ApiErrorCode::CoverageAmbiguous, fn () => $movement->plan($fixture['actor'], $fixture['nodes'][0], $consignment, $this->cid('coverage-ambiguous')));
        self::assertDatabaseMissing('route_plans', ['consignment_id' => $consignment]);

        $coverage->transition($fixture['actor'], $policy['coverage_policy_id'], $version['coverage_policy_version_id'], 'supersede', 4, null, $this->cid('amb-supersede'));
        $routes = $this->app->make(RouteDefinitionService::class);
        $definition = $routes->create($fixture['actor'], ['route_code' => 'AMB-ROUTE', 'route_title' => 'مسیر هم‌اولویت'], $this->cid('amb-route'));
        $routeVersion = $routes->createVersion($fixture['actor'], $definition['route_definition_id'], [
            'purpose' => 'TRUNK', 'origin_node_id' => $fixture['nodes'][0], 'destination_node_id' => $published['gateway_id'], 'priority' => 100,
            'legs' => [
                ['leg_order' => 1, 'origin_node_id' => $fixture['nodes'][0], 'destination_node_id' => $fixture['nodes'][1]],
                ['leg_order' => 2, 'origin_node_id' => $fixture['nodes'][1], 'destination_node_id' => $published['gateway_id']],
            ],
        ], $this->cid('amb-route-version'));
        foreach (['validate', 'approve', 'publish'] as $index => $action) {
            $routeVersion = $routes->transition($fixture['actor'], $definition['route_definition_id'], $routeVersion['route_definition_version_id'], $action, $index + 1, null, $this->cid("amb-route-{$action}"));
        }
        $second = $this->consignment($fixture, 'AMB-ROUTE');
        $this->expectApi(ApiErrorCode::RouteAmbiguous, fn () => $movement->plan($fixture['actor'], $fixture['nodes'][0], $second, $this->cid('route-ambiguous')));
        self::assertDatabaseMissing('route_plans', ['consignment_id' => $second]);
    }

    public function test_pickup_assignment_completion_concurrency_and_access_fail_closed(): void
    {
        $fixture = $this->fixture('PICKUP');
        $this->authorization->accessibleNodeIds = $fixture['nodes'];
        $driverId = $this->driver($fixture);
        $consignmentId = $this->consignment($fixture, 'PICKUP-FLOW');
        $policy=\Modules\ServiceCatalog\Application\SchedulePolicy::fromBinding(['pickup_mode'=>'NONE','delivery_mode'=>'COMPUTED','duration_value'=>24,'duration_unit'=>'HOUR','duration_anchor'=>'PICKUP_COMPLETED']);
        $frozen=['policy'=>$policy,'effective_delivery_policy'=>$policy['delivery'],'timezone'=>'Asia/Tehran','delivery'=>['awaiting_operation'=>true],'windows_snapshot'=>[],'accepted_at'=>now()->toISOString()];
        DB::table('consignments')->where('consignment_id',$consignmentId)->update(['commitment_snapshot'=>json_encode($frozen)]);
        $originalSnapshot=DB::table('consignments')->where('consignment_id',$consignmentId)->value('commitment_snapshot');
        $service = $this->app->make(PickupTaskService::class);

        $task = $service->create($fixture['actor'], $fixture['nodes'][0], $consignmentId, $this->cid('pickup-create'));
        self::assertSame('PENDING', $task['status']);
        self::assertSame('PICKUP-FLOW', $task['consignment_number']);
        self::assertSame('فرستنده', $task['sender_name']);
        self::assertSame('09120000001', $task['sender_mobile']);
        $task = $service->assign($fixture['actor'], $fixture['nodes'][0], $task['pickup_task_id'], $driverId, 1, $this->cid('pickup-assign'));
        self::assertSame('ASSIGNED', $task['status']);
        self::assertNotNull($task['assigned_at']);
        $this->expectApi(ApiErrorCode::VersionConflict, fn () => $service->complete($fixture['actor'], $fixture['nodes'][0], $task['pickup_task_id'], 1, $this->cid('pickup-stale')));
        $task = $service->complete($fixture['actor'], $fixture['nodes'][0], $task['pickup_task_id'], 2, $this->cid('pickup-complete'));
        self::assertSame('COMPLETED', $task['status']);
        self::assertSame('PU', DB::table('consignments')->where('consignment_id', $consignmentId)->value('current_status'));
        $stored=DB::table('consignments')->where('consignment_id',$consignmentId)->first();
        $resolution=json_decode($stored->delivery_commitment_resolution,true);
        self::assertSame($originalSnapshot,$stored->commitment_snapshot);
        self::assertSame(\Carbon\CarbonImmutable::parse($resolution['pickup_completed_at'])->addHours(24)->toISOString(),$resolution['result']['computed_at']);
        self::assertSame($resolution['result']['computed_at'],\Carbon\CarbonImmutable::parse($stored->delivery_commitment_at,'UTC')->toISOString());
        self::assertTrue(DB::table('audit_events')->where('action_key','CONSIGNMENT_COMMITMENT_RESOLVED')->where('target_id',$consignmentId)->exists());

        $this->authorization->entitlements = ['LiveOperations'];
        $this->expectApi(ApiErrorCode::EntitlementDisabled, fn () => $service->list($fixture['actor'], $fixture['nodes'][0]));
        $this->authorization->entitlements = ['Pickup', 'LiveOperations'];
        $this->authorization->permissions = [];
        $this->expectApi(ApiErrorCode::PermissionDenied, fn () => $service->list($fixture['actor'], $fixture['nodes'][0]));
        $this->authorization->permissions = MutableOperationsAuthorization::PERMISSIONS;
        $this->authorization->accessibleNodeIds = [];
        $this->expectApi(ApiErrorCode::ScopeAccessDenied, fn () => $service->list($fixture['actor'], $fixture['nodes'][0]));

        $foreign = $this->fixture('CROSS-HQ');
        $this->authorization->accessibleNodeIds = [$fixture['nodes'][0]];
        // Tenant-qualified scope resolution denies the foreign node before loading its task.
        $this->expectApi(ApiErrorCode::ScopeAccessDenied, fn () => $service->get($foreign['actor'], $fixture['nodes'][0], $task['pickup_task_id']));
    }

    public function test_pickup_audit_and_outbox_roll_back_atomically_when_event_write_fails(): void
    {
        $fixture = $this->fixture('ROLLBACK');
        $this->authorization->accessibleNodeIds = $fixture['nodes'];
        $consignmentId = $this->consignment($fixture, 'ROLLBACK-PICKUP');
        $correlationId = $this->cid('rollback-correlation');
        $realOutbox = $this->app->make(OutboxWriter::class);
        $this->app->instance(OutboxWriter::class, new class($realOutbox) implements OutboxWriter {
            public function __construct(private OutboxWriter $real) {}
            public function write(?string $hqId, string $aggregateType, string $aggregateId, string $eventType, string $correlationId, array $payload, int $eventVersion = 1, ?string $causationId = null): void
            {
                $this->real->write($hqId, $aggregateType, $aggregateId, $eventType, $correlationId, $payload, $eventVersion, $causationId);
                throw new \RuntimeException('Injected outbox failure.');
            }
        });

        try {
            $this->app->make(PickupTaskService::class)->create($fixture['actor'], $fixture['nodes'][0], $consignmentId, $correlationId);
            self::fail('The injected outbox failure must escape the transaction.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Injected outbox failure.', $exception->getMessage());
        }

        self::assertDatabaseMissing('pickup_tasks', ['consignment_id' => $consignmentId]);
        self::assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        self::assertDatabaseMissing('outbox_events', ['correlation_id' => $correlationId]);
    }

    /** @return array<string,mixed> */
    private function fixture(string $code): array
    {
        $tenant = $this->tenant('HQ-'.$code);
        $user = $this->user($tenant['hq_id'], strtolower($code).'-operator');
        $actor = new AuthenticatedPrincipal($user['user_id'], $this->cid("session-{$code}"), $tenant['hq_id'], false);
        $provinceId = (string) Str::uuid();
        $cityId = (string) Str::uuid();
        DB::table('provinces')->insert(['province_id' => $provinceId, 'legacy_province_code' => substr(hash('sha256', $code), 0, 4), 'name_fa' => 'استان '.$code, 'normalized_name' => strtolower($code), 'latitude' => 35.0, 'longitude' => 51.0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('cities')->insert(['city_id' => $cityId, 'province_id' => $provinceId, 'legacy_city_code' => substr(hash('sha256', 'city-'.$code), 0, 12), 'name_fa' => 'شهر '.$code, 'normalized_name' => strtolower($code), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $areaId = (string) Str::uuid();
        DB::table('areas')->insert(['area_id' => $areaId, 'hq_id' => $tenant['hq_id'], 'area_code' => $code, 'area_title' => $code, 'status' => 'ACTIVE', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $nodes = [];
        foreach (['ORIGIN', 'HUB', 'GATEWAY'] as $index => $suffix) {
            $nodes[] = $nodeId = (string) Str::uuid();
            DB::table('nodes')->insert(['node_id' => $nodeId, 'hq_id' => $tenant['hq_id'], 'area_id' => $areaId, 'node_code' => "{$code}-{$suffix}", 'node_title' => $suffix, 'node_type' => $index === 0 ? 'BRANCH' : ($index === 1 ? 'HUB' : 'GATEWAY'), 'capabilities' => json_encode(['PICKUP', 'LINEHAUL'], JSON_THROW_ON_ERROR), 'country_code' => 'IR', 'status' => 'ACTIVE', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        return ['tenant' => $tenant, 'actor' => $actor, 'user' => $user, 'province_id' => $provinceId, 'city_id' => $cityId, 'nodes' => $nodes];
    }

    /** @param array<string,mixed> $fixture @return array<string,string> */
    private function publishConfiguration(array $fixture): array
    {
        $coverage = $this->app->make(CoveragePolicyService::class);
        $policy = $coverage->create($fixture['actor'], ['policy_code' => 'COVER-'.$fixture['tenant']['hq_code'], 'policy_title' => 'پوشش مقصد'], $this->cid('coverage-'.$fixture['tenant']['hq_id']));
        $coverageVersion = $coverage->createVersion($fixture['actor'], $policy['coverage_policy_id'], ['rules' => [[
            'target' => 'DESTINATION_GATEWAY', 'target_node_id' => $fixture['nodes'][2], 'priority' => 50,
            'criterion' => ['criterion_type' => 'CITY', 'city_id' => $fixture['city_id']],
        ]]], $this->cid('coverage-version-'.$fixture['tenant']['hq_id']));
        foreach (['validate', 'approve', 'publish'] as $index => $action) {
            $coverageVersion = $coverage->transition($fixture['actor'], $policy['coverage_policy_id'], $coverageVersion['coverage_policy_version_id'], $action, $index + 1, null, $this->cid("coverage-{$action}-".$fixture['tenant']['hq_id']));
        }
        $coverageRuleId = (string) DB::table('coverage_rules')->where('coverage_policy_version_id', $coverageVersion['coverage_policy_version_id'])->value('coverage_rule_id');

        $routes = $this->app->make(RouteDefinitionService::class);
        $definition = $routes->create($fixture['actor'], ['route_code' => 'ROUTE-'.$fixture['tenant']['hq_code'], 'route_title' => 'مسیر منتشرشده'], $this->cid('route-'.$fixture['tenant']['hq_id']));
        $routeVersion = $routes->createVersion($fixture['actor'], $definition['route_definition_id'], [
            'purpose' => 'TRUNK', 'origin_node_id' => $fixture['nodes'][0], 'destination_node_id' => $fixture['nodes'][2], 'priority' => 100,
            'legs' => [
                ['leg_order' => 1, 'origin_node_id' => $fixture['nodes'][0], 'destination_node_id' => $fixture['nodes'][1]],
                ['leg_order' => 2, 'origin_node_id' => $fixture['nodes'][1], 'destination_node_id' => $fixture['nodes'][2]],
            ],
        ], $this->cid('route-version-'.$fixture['tenant']['hq_id']));
        foreach (['validate', 'approve', 'publish'] as $index => $action) {
            $routeVersion = $routes->transition($fixture['actor'], $definition['route_definition_id'], $routeVersion['route_definition_version_id'], $action, $index + 1, null, $this->cid("route-{$action}-".$fixture['tenant']['hq_id']));
        }

        return ['gateway_id' => $fixture['nodes'][2], 'coverage_version_id' => $coverageVersion['coverage_policy_version_id'], 'coverage_rule_id' => $coverageRuleId, 'route_version_id' => $routeVersion['route_definition_version_id']];
    }

    /** @param array<string,mixed> $fixture */
    private function consignment(array $fixture, string $number): string
    {
        $id = (string) Str::uuid();
        DB::table('consignments')->insert([
            'consignment_id' => $id, 'hq_id' => $fixture['tenant']['hq_id'], 'consignment_number' => $number,
            'initiator_id' => $fixture['user']['user_id'], 'pickup_node_id' => $fixture['nodes'][0], 'delivery_node_id' => null,
            'sender_contact_name' => 'فرستنده', 'sender_mobile' => '09120000001', 'sender_address_text' => 'نشانی فرستنده', 'sender_state' => 'مبدأ', 'sender_city' => 'مبدأ', 'sender_city_id' => $fixture['city_id'],
            'receiver_contact_name' => 'گیرنده', 'receiver_mobile' => '09120000002', 'receiver_address_text' => 'نشانی گیرنده', 'receiver_state' => 'مقصد', 'receiver_city' => 'مقصد', 'receiver_city_id' => $fixture['city_id'], 'receiver_postal_code' => '1234567890',
            'service_type_id' => (string) Str::uuid(), 'shipping_method_id' => (string) Str::uuid(), 'weight_kg' => 1,
            'declared_value_amount' => 1000, 'insurance_enabled' => true, 'insurance_value_amount' => 1000, 'cod_enabled' => false, 'payer' => 'SENDER', 'payment_method' => 'CASH',
            'current_status' => 'CFM', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('parcels')->insert(['parcel_id' => (string) Str::uuid(), 'hq_id' => $fixture['tenant']['hq_id'], 'consignment_id' => $id, 'parcel_number' => $number.'-01', 'current_status' => 'CFM', 'current_node_id' => $fixture['nodes'][0], 'current_custody_type' => 'NODE', 'current_custodian_id' => $fixture['nodes'][0], 'version' => 1, 'content_description' => 'بسته آزمون', 'weight_kg' => 1, 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }

    /** @param array<string,mixed> $fixture */
    private function driver(array $fixture): string
    {
        $id = (string) Str::uuid();
        DB::table('drivers')->insert(['driver_id' => $id, 'hq_id' => $fixture['tenant']['hq_id'], 'user_id' => null, 'driver_code' => 'DRV-'.$fixture['tenant']['hq_code'], 'display_name' => 'راننده جمع‌آوری', 'home_node_id' => $fixture['nodes'][0], 'operational_type' => 'PICKUP', 'status' => 'ACTIVE', 'availability_status' => 'AVAILABLE', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('driver_capabilities')->insert(['driver_capability_id' => (string) Str::uuid(), 'hq_id' => $fixture['tenant']['hq_id'], 'driver_id' => $id, 'capability' => 'PICKUP', 'created_at' => now()]);
        return $id;
    }

    private function cid(string $key): string
    {
        $hex = substr(hash('sha256', $key), 0, 32);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-4'.substr($hex, 13, 3).'-8'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }

    private function expectApi(ApiErrorCode $code, callable $callback): void
    {
        try { $callback(); self::fail("Expected {$code->value}."); }
        catch (ApiException $exception) { self::assertSame($code, $exception->errorCode); }
    }
}

final class MutableOperationsAuthorization implements AuthorizationContextResolver
{
    public const PERMISSIONS = [
        'network.coverage.view', 'network.coverage.manage_draft', 'network.coverage.validate', 'network.coverage.approve', 'network.coverage.publish',
        'network.route.view', 'network.route.manage_draft', 'network.route.validate', 'network.route.approve', 'network.route.publish',
        'pickup_request.view', 'pickup_request.create', 'pickup_request.assign', 'live_operations.view', 'live_operations.intervene',
    ];

    /** @var list<string> */ public array $permissions = self::PERMISSIONS;
    /** @var list<string> */ public array $entitlements = ['Pickup', 'LiveOperations'];
    /** @var list<string> */ public array $accessibleNodeIds = [];

    public function resolve(AuthenticatedPrincipal $principal): array
    {
        return [
            'module_entitlements' => array_map(fn (string $code): array => ['module_code' => $code, 'status' => 'ENABLED'], $this->entitlements),
            'permissions' => $this->permissions,
            'hq_id' => $principal->hqId,
            'permission_scopes' => array_fill_keys($this->permissions, array_map(fn ($id) => ['scope_type' => 'NODE', 'scope_id' => $id, 'includes_descendants' => false], $this->accessibleNodeIds)),
            'accessible_node_ids' => $this->accessibleNodeIds,
        ];
    }
}
