<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\NodeAccessValidator;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Operations\Application\DeliveryTaskService;
use Modules\Operations\Application\TransportRunService;

final class TransportDeliveryProductIntegrationTest extends MySqlRedisTestCase
{
    /** @var list<string> */
    private array $nodes = [];
    /** @var list<string> */
    private array $permissions = ['live_operations.view', 'live_operations.intervene'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(AuthorizationContextResolver::class, new class($this) implements AuthorizationContextResolver {
            public function __construct(private TransportDeliveryProductIntegrationTest $test) {}
            public function resolve(AuthenticatedPrincipal $principal): array
            {
                return ['module_entitlements' => [['module_code' => 'LiveOperations', 'status' => 'ENABLED']], 'permissions' => $this->test->permissions(), 'accessible_node_ids' => $this->test->nodes()];
            }
        });
        $this->app->instance(NodeAccessValidator::class, new class($this) implements NodeAccessValidator {
            public function __construct(private TransportDeliveryProductIntegrationTest $test) {}
            public function assertAccessible(AuthenticatedPrincipal $principal, string $nodeId): void
            {
                if (! in_array($nodeId, $this->test->nodes(), true)) throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
            }
        });
    }

    /** @return list<string> */ public function nodes(): array { return $this->nodes; }
    /** @return list<string> */ public function permissions(): array { return $this->permissions; }

    public function test_transport_run_enforces_order_fleet_scope_arrival_reception_concurrency_and_history(): void
    {
        $fixture = $this->fixture('TRANSPORT'); $this->nodes = $fixture['nodes'];
        $service = $this->app->make(TransportRunService::class);
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $service->create($fixture['actor'], $fixture['hub'], $fixture['second_leg'], $fixture['linehaul_driver'], $fixture['vehicle'], $this->cid('skip-leg')));
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $service->create($fixture['actor'], $fixture['origin'], $fixture['first_leg'], $fixture['delivery_driver'], $fixture['vehicle'], $this->cid('wrong-capability')));
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $service->create($fixture['actor'], $fixture['origin'], $fixture['first_leg'], $fixture['linehaul_driver'], $fixture['foreign_vehicle'], $this->cid('cross-hq-vehicle')));

        $run = $service->create($fixture['actor'], $fixture['origin'], $fixture['first_leg'], $fixture['linehaul_driver'], $fixture['vehicle'], $this->cid('create'));
        self::assertSame($run['transport_run_id'], $service->create($fixture['actor'], $fixture['origin'], $fixture['first_leg'], $fixture['linehaul_driver'], $fixture['vehicle'], $this->cid('create-retry'))['transport_run_id']);
        self::assertSame('ON_MISSION', DB::table('drivers')->where('driver_id', $fixture['linehaul_driver'])->value('availability_status'));
        $this->expectApi(ApiErrorCode::VersionConflict, fn () => $service->load($fixture['actor'], $fixture['origin'], $run['transport_run_id'], [$fixture['parcel']], 99, $this->cid('stale')));
        $run = $service->load($fixture['actor'], $fixture['origin'], $run['transport_run_id'], [$fixture['parcel']], 1, $this->cid('load'));
        $run = $service->depart($fixture['actor'], $fixture['origin'], $run['transport_run_id'], 2, $this->cid('depart'));
        self::assertSame('OS', DB::table('parcels')->where('parcel_id', $fixture['parcel'])->value('current_status'));
        $run = $service->arrive($fixture['actor'], $fixture['hub'], $run['transport_run_id'], 3, $this->cid('arrive'));
        self::assertSame('ARRIVED', $run['status']); self::assertFalse($run['reception']['completed']);
        self::assertSame('OS', DB::table('parcels')->where('parcel_id', $fixture['parcel'])->value('current_status'), 'Arrival must not mutate status or custody.');
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $service->close($fixture['actor'], $fixture['hub'], $run['transport_run_id'], 4, $this->cid('close-before-reception')));
        DB::table('route_plan_legs')->where('route_plan_leg_id', $fixture['first_leg'])->update(['status' => 'RECEIVED', 'received_at' => now()]);
        $run = $service->close($fixture['actor'], $fixture['hub'], $run['transport_run_id'], 4, $this->cid('close'));
        self::assertSame('CLOSED', $run['status']); self::assertTrue($run['reception']['completed']);
        self::assertSame(['CREATED', 'LOADED', 'DEPARTED', 'ARRIVED', 'CLOSED'], DB::table('transport_run_history')->where('transport_run_id', $run['transport_run_id'])->orderBy('event_sequence')->pluck('event_type')->all());
        $this->assertImmutable('transport_run_history', 'transport_run_id', $run['transport_run_id']);
        self::assertGreaterThanOrEqual(5, DB::table('audit_events')->where('target_id', $run['transport_run_id'])->count());
        self::assertGreaterThanOrEqual(5, DB::table('outbox_events')->where('aggregate_id', $run['transport_run_id'])->count());

        $this->permissions = ['live_operations.view'];
        $this->expectApi(ApiErrorCode::PermissionDenied, fn () => $service->create($fixture['actor'], $fixture['origin'], $fixture['second_leg'], $fixture['linehaul_driver'], $fixture['vehicle'], $this->cid('negative-permission')));
        $this->permissions = ['live_operations.view', 'live_operations.intervene']; $this->nodes = [$fixture['origin']];
        $this->expectApi(ApiErrorCode::ScopeAccessDenied, fn () => $service->get($fixture['actor'], $fixture['hub'], $run['transport_run_id']));
    }

    public function test_last_mile_resolution_delivery_nok_retry_reassignment_and_ok_are_fail_closed(): void
    {
        $fixture = $this->fixture('DELIVERY'); $this->nodes = $fixture['nodes'];
        $service = $this->app->make(DeliveryTaskService::class);
        self::assertSame('', $service->ensurePending($fixture['actor'], $fixture['gateway'], $fixture['consignment']));
        $evidence = DB::table('last_mile_resolution_evidence')->where('consignment_id', $fixture['consignment'])->first();
        self::assertNotNull($evidence); self::assertSame($fixture['last_mile'], (string) $evidence->last_mile_node_id);
        self::assertSame($fixture['last_mile'], DB::table('consignments')->where('consignment_id', $fixture['consignment'])->value('delivery_node_id'));
        self::assertSame(3, DB::table('route_plan_legs')->where('route_plan_id', $fixture['plan'])->count(), 'Published Last-mile Route legs are appended as execution evidence.');

        $lastLeg = (string) DB::table('route_plan_legs')->where('route_plan_id', $fixture['plan'])->orderByDesc('leg_order')->value('route_plan_leg_id');
        DB::table('route_plan_legs')->where('route_plan_id', $fixture['plan'])->update(['status' => 'RECEIVED', 'received_at' => now()]);
        DB::table('parcels')->where('parcel_id', $fixture['parcel'])->update(['current_status' => 'IR', 'current_node_id' => $fixture['last_mile'], 'current_custody_type' => 'NODE', 'current_custodian_id' => $fixture['last_mile'], 'active_route_plan_leg_id' => $lastLeg]);
        DB::table('consignments')->where('consignment_id', $fixture['consignment'])->update(['current_status' => 'IR']);
        $taskId = $service->ensurePending($fixture['actor'], $fixture['last_mile'], $fixture['consignment']);
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $service->assign($fixture['actor'], $fixture['last_mile'], $taskId, $fixture['foreign_delivery_driver'], 1, $this->cid('cross-hq-driver')));
        $task = $service->assign($fixture['actor'], $fixture['last_mile'], $taskId, $fixture['delivery_driver'], 1, $this->cid('assign'));
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $service->complete($fixture['actor'], $fixture['last_mile'], $taskId, 2, 'گیرنده', now()->toISOString(), null, $this->cid('complete-before-activation')));
        $service->activateFromManifest($fixture['actor'], $fixture['last_mile'], $fixture['consignment'], $fixture['delivery_driver'], $fixture['manifest']);
        DB::table('parcels')->where('parcel_id', $fixture['parcel'])->update(['current_status' => 'OD', 'current_node_id' => null, 'current_custody_type' => 'DELIVERY_DRIVER', 'current_custodian_id' => $fixture['delivery_driver']]);
        DB::table('consignments')->where('consignment_id', $fixture['consignment'])->update(['current_status' => 'OD']);
        $failed = $service->fail($fixture['actor'], $fixture['last_mile'], $taskId, 3, 'RECIPIENT_UNAVAILABLE', 'گیرنده در دسترس نبود', $this->cid('fail'));
        self::assertSame('FAILED', $failed['status']); self::assertSame('NOK', DB::table('parcels')->where('parcel_id', $fixture['parcel'])->value('current_status'));
        $retried = $service->retry($fixture['actor'], $fixture['last_mile'], $taskId, 4, 'تلاش مجدد تأیید شد', $this->cid('retry'));
        self::assertSame('PENDING', $retried['status']); self::assertSame(2, $retried['attempt_number']); self::assertSame('IR', DB::table('parcels')->where('parcel_id', $fixture['parcel'])->value('current_status'));
        $assigned = $service->assign($fixture['actor'], $fixture['last_mile'], $taskId, $fixture['delivery_driver'], 5, $this->cid('second-assign'));
        $assigned = $service->assign($fixture['actor'], $fixture['last_mile'], $taskId, $fixture['second_delivery_driver'], 6, $this->cid('reassign'));
        self::assertSame($fixture['second_delivery_driver'], $assigned['assigned_driver_id']);
        $service->activateFromManifest($fixture['actor'], $fixture['last_mile'], $fixture['consignment'], $fixture['second_delivery_driver'], $fixture['manifest']);
        DB::table('parcels')->where('parcel_id', $fixture['parcel'])->update(['current_status' => 'OD', 'current_node_id' => null, 'current_custody_type' => 'DELIVERY_DRIVER', 'current_custodian_id' => $fixture['second_delivery_driver']]);
        DB::table('consignments')->where('consignment_id', $fixture['consignment'])->update(['current_status' => 'OD']);
        $completed = $service->complete($fixture['actor'], $fixture['last_mile'], $taskId, 8, 'گیرنده نهایی', now()->subMinute()->toISOString(), 'تحویل دستی تأیید شد', $this->cid('complete'));
        self::assertSame('COMPLETED', $completed['status']); self::assertSame('OK', DB::table('parcels')->where('parcel_id', $fixture['parcel'])->value('current_status'));
        self::assertSame(['CREATED', 'ASSIGNED', 'ACTIVATED', 'FAILED', 'RETRY_REQUESTED', 'ASSIGNED', 'REASSIGNED', 'ACTIVATED', 'COMPLETED'], DB::table('delivery_task_history')->where('delivery_task_id', $taskId)->orderBy('event_sequence')->pluck('event_type')->all());
        $this->assertImmutable('delivery_task_history', 'delivery_task_id', $taskId);
        $this->assertImmutable('last_mile_resolution_evidence', 'consignment_id', $fixture['consignment']);
        self::assertDatabaseHas('operational_exception_cases', ['delivery_task_id' => $taskId, 'exception_type' => 'NOK', 'resolution_action' => 'RETRY']);
    }

    public function test_transport_create_rolls_back_resource_reservation_audit_and_run_when_outbox_fails(): void
    {
        $fixture = $this->fixture('ROLLBACK'); $this->nodes = $fixture['nodes'];
        $this->app->instance(OutboxWriter::class, new class implements OutboxWriter {
            public function write(?string $hqId, string $aggregateType, string $aggregateId, string $eventType, string $correlationId, array $payload, int $eventVersion = 1, ?string $causationId = null): void { throw new \RuntimeException('forced outbox failure'); }
        });
        $beforeAudit = DB::table('audit_events')->count();
        try {
            $this->app->make(TransportRunService::class)->create($fixture['actor'], $fixture['origin'], $fixture['first_leg'], $fixture['linehaul_driver'], $fixture['vehicle'], $this->cid('rollback'));
            self::fail('The forced Outbox failure must escape the transaction.');
        } catch (\RuntimeException $exception) { self::assertSame('forced outbox failure', $exception->getMessage()); }
        self::assertDatabaseCount('transport_runs', 0); self::assertDatabaseCount('transport_run_history', 0);
        self::assertSame('AVAILABLE', DB::table('drivers')->where('driver_id', $fixture['linehaul_driver'])->value('availability_status'));
        self::assertSame('AVAILABLE', DB::table('vehicles')->where('vehicle_id', $fixture['vehicle'])->value('availability_status'));
        self::assertSame($beforeAudit, DB::table('audit_events')->count());
    }

    public function test_last_mile_resolution_fails_closed_for_missing_and_ambiguous_published_coverage(): void
    {
        $fixture = $this->fixture('CONFIG'); $this->nodes = $fixture['nodes'];
        $service = $this->app->make(DeliveryTaskService::class);
        $versionId = (string) DB::table('coverage_policy_versions')->where('hq_id', $fixture['actor']->hqId)->value('coverage_policy_version_id');
        DB::table('coverage_policy_versions')->where('coverage_policy_version_id', $versionId)->update(['status' => 'SUPERSEDED']);
        $this->expectApi(ApiErrorCode::CoverageNotFound, fn () => $service->ensurePending($fixture['actor'], $fixture['gateway'], $fixture['consignment']));

        DB::table('coverage_policy_versions')->where('coverage_policy_version_id', $versionId)->update(['status' => 'PUBLISHED']);
        $duplicate = (array) DB::table('coverage_rules')->where('coverage_policy_version_id', $versionId)->first();
        $duplicate['coverage_rule_id'] = (string) Str::uuid(); $duplicate['created_at'] = now(); $duplicate['updated_at'] = now();
        DB::table('coverage_rules')->insert($duplicate);
        $this->expectApi(ApiErrorCode::CoverageAmbiguous, fn () => $service->ensurePending($fixture['actor'], $fixture['gateway'], $fixture['consignment']));

        self::assertSame(0, DB::table('last_mile_resolution_evidence')->where('consignment_id', $fixture['consignment'])->count());
        self::assertSame(0, DB::table('delivery_tasks')->where('consignment_id', $fixture['consignment'])->count());
        self::assertSame(2, DB::table('route_plan_legs')->where('route_plan_id', $fixture['plan'])->count());
        self::assertSame($fixture['gateway'], DB::table('consignments')->where('consignment_id', $fixture['consignment'])->value('delivery_node_id'));
    }

    public function test_transport_create_route_is_enveloped_and_idempotent(): void
    {
        $fixture = $this->fixture('IDEMPOTENCY'); $this->nodes = $fixture['nodes'];
        $token = $this->login('idempotency-operator')['token'];
        $headers = ['X-Node-Id' => $fixture['origin'], 'X-Correlation-ID' => $this->cid('idempotent-http'), 'Idempotency-Key' => 'transport-create-focused-000001'];
        $payload = ['route_plan_leg_id' => $fixture['first_leg'], 'driver_id' => $fixture['linehaul_driver'], 'vehicle_id' => $fixture['vehicle']];

        $first = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/transport-runs', $payload)
            ->assertCreated()->assertJsonStructure(['data', 'meta', 'correlation_id']);
        $runId = (string) $first->json('data.transport_run_id');
        $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/transport-runs', $payload)
            ->assertCreated()->assertJsonPath('data.transport_run_id', $runId);

        self::assertSame(1, DB::table('transport_runs')->where('transport_run_id', $runId)->count());
        self::assertSame(1, DB::table('transport_run_history')->where('transport_run_id', $runId)->count());
        self::assertSame(1, DB::table('audit_events')->where(['target_type' => 'TRANSPORT_RUN', 'target_id' => $runId])->count());
        self::assertSame(1, DB::table('outbox_events')->where(['aggregate_type' => 'TRANSPORT_RUN', 'aggregate_id' => $runId])->count());
    }

    /** @return array<string,mixed> */
    private function fixture(string $code): array
    {
        $tenant = $this->tenant('HQ-'.$code); $user = $this->user($tenant['hq_id'], strtolower($code).'-operator'); $actor = new AuthenticatedPrincipal($user['user_id'], $this->cid($code.'-session'), $tenant['hq_id'], false);
        $area = (string) Str::uuid(); DB::table('areas')->insert(['area_id' => $area, 'hq_id' => $tenant['hq_id'], 'area_code' => $code, 'area_title' => $code, 'status' => 'ACTIVE', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $origin = $this->node($tenant['hq_id'], $area, $code.'-ORIGIN', 'BRANCH', ['PICKUP', 'LINEHAUL']);
        $hub = $this->node($tenant['hq_id'], $area, $code.'-HUB', 'HUB', ['CONSOLIDATION', 'LINEHAUL']);
        $gateway = $this->node($tenant['hq_id'], $area, $code.'-GATEWAY', 'GATEWAY', ['GATEWAY', 'LINEHAUL']);
        $lastMile = $this->node($tenant['hq_id'], $area, $code.'-LAST-MILE', 'BRANCH', ['LINEHAUL', 'DELIVERY']);
        $nodes = [$origin, $hub, $gateway, $lastMile];
        $province = (string) Str::uuid(); $city = (string) Str::uuid();
        DB::table('provinces')->insert(['province_id' => $province, 'legacy_province_code' => substr(hash('sha256', $code), 0, 4), 'name_fa' => 'استان '.$code, 'normalized_name' => strtolower($code), 'latitude' => 35, 'longitude' => 51, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('cities')->insert(['city_id' => $city, 'province_id' => $province, 'legacy_city_code' => substr(hash('sha256', 'city-'.$code), 0, 12), 'name_fa' => 'شهر '.$code, 'normalized_name' => strtolower($code), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        [$serviceType, $shipping] = $this->catalog($tenant['hq_id'], $user['user_id'], $code);
        $consignment = (string) Str::uuid(); $parcel = (string) Str::uuid();
        DB::table('consignments')->insert(['consignment_id' => $consignment, 'hq_id' => $tenant['hq_id'], 'consignment_number' => 'CN-'.$code, 'initiator_id' => $user['user_id'], 'pickup_node_id' => $origin, 'delivery_node_id' => $gateway, 'sender_contact_name' => 'فرستنده', 'sender_mobile' => '09120000000', 'sender_address_text' => 'نشانی مبدا', 'sender_state' => 'استان', 'sender_city' => 'شهر', 'receiver_contact_name' => 'گیرنده', 'receiver_mobile' => '09121111111', 'receiver_address_text' => 'نشانی مقصد', 'receiver_state' => 'استان', 'receiver_city' => 'شهر', 'receiver_city_id' => $city, 'receiver_postal_code' => '1111111111', 'receiver_latitude' => 35.7, 'receiver_longitude' => 51.4, 'service_type_id' => $serviceType, 'shipping_method_id' => $shipping, 'weight_kg' => 2, 'declared_value_amount' => 100000, 'insurance_enabled' => false, 'cod_enabled' => false, 'payer' => 'SENDER', 'payment_method' => 'CREDIT', 'current_status' => 'OF', 'commercial_pricing_state' => 'UNPRICED', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('parcels')->insert(['parcel_id' => $parcel, 'hq_id' => $tenant['hq_id'], 'consignment_id' => $consignment, 'parcel_number' => 'PN-'.$code, 'current_status' => 'OF', 'content_description' => 'کالای آزمون', 'weight_kg' => 2, 'width_cm' => 20, 'length_cm' => 30, 'height_cm' => 10, 'current_node_id' => $origin, 'current_custody_type' => 'NODE', 'current_custodian_id' => $origin, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        [$definition, $version, $versionLegs] = $this->publishedRoute($tenant['hq_id'], $user['user_id'], $code.'-TRUNK', 'TRUNK', [[$origin, $hub], [$hub, $gateway]]);
        $plan = (string) Str::uuid(); DB::table('route_plans')->insert(['route_plan_id' => $plan, 'hq_id' => $tenant['hq_id'], 'consignment_id' => $consignment, 'route_definition_id' => $definition, 'route_definition_version_id' => $version, 'status' => 'IN_PROGRESS', 'active_slot' => hash('sha256', $tenant['hq_id'].'|'.$consignment.'|ACTIVE'), 'version' => 1, 'created_by' => $user['user_id'], 'created_at' => now(), 'updated_at' => now()]);
        $firstLeg = $this->planLeg($tenant['hq_id'], $plan, $versionLegs[0], 1, $origin, $hub, 'ROUTED'); $secondLeg = $this->planLeg($tenant['hq_id'], $plan, $versionLegs[1], 2, $hub, $gateway, 'ROUTED');
        DB::table('parcels')->where('parcel_id', $parcel)->update(['active_route_plan_id' => $plan, 'active_route_plan_leg_id' => $firstLeg]);
        [$linehaulDriver] = $this->driver($tenant['hq_id'], $origin, $code.'-LINEHAUL', 'LINEHAUL'); [$deliveryDriver] = $this->driver($tenant['hq_id'], $lastMile, $code.'-DELIVERY-1', 'DELIVERY'); [$secondDeliveryDriver] = $this->driver($tenant['hq_id'], $lastMile, $code.'-DELIVERY-2', 'DELIVERY');
        $vehicle = $this->vehicle($tenant['hq_id'], $origin, $code.'-VEHICLE');
        $foreign = $this->tenant('FOREIGN-'.$code); $foreignArea = (string) Str::uuid(); DB::table('areas')->insert(['area_id' => $foreignArea, 'hq_id' => $foreign['hq_id'], 'area_code' => 'FOREIGN-'.$code, 'area_title' => 'Foreign', 'status' => 'ACTIVE', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]); $foreignNode = $this->node($foreign['hq_id'], $foreignArea, 'FOREIGN-'.$code, 'BRANCH', ['LINEHAUL', 'DELIVERY']); $foreignVehicle = $this->vehicle($foreign['hq_id'], $foreignNode, 'FOREIGN-'.$code); [$foreignDeliveryDriver] = $this->driver($foreign['hq_id'], $foreignNode, 'FOREIGN-'.$code.'-DELIVERY', 'DELIVERY');
        $this->publishedCoverage($tenant['hq_id'], $user['user_id'], $city, $lastMile, $code);
        $this->publishedRoute($tenant['hq_id'], $user['user_id'], $code.'-LAST-MILE', 'LAST_MILE', [[$gateway, $lastMile]]);
        $manifest = (string) Str::uuid(); DB::table('manifests')->insert(['manifest_id' => $manifest, 'hq_id' => $tenant['hq_id'], 'manifest_number' => 'MF-'.$code, 'node_id' => $lastMile, 'manifest_status' => 'OD', 'state' => 'CLOSED', 'version' => 1, 'created_by' => $user['user_id'], 'approved_by' => $user['user_id'], 'closed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        return compact('actor', 'origin', 'hub', 'gateway', 'lastMile', 'nodes', 'consignment', 'parcel', 'plan', 'firstLeg', 'secondLeg', 'linehaulDriver', 'deliveryDriver', 'secondDeliveryDriver', 'vehicle', 'foreignVehicle', 'foreignDeliveryDriver', 'manifest') + ['last_mile' => $lastMile, 'first_leg' => $firstLeg, 'second_leg' => $secondLeg, 'linehaul_driver' => $linehaulDriver, 'delivery_driver' => $deliveryDriver, 'second_delivery_driver' => $secondDeliveryDriver, 'foreign_vehicle' => $foreignVehicle, 'foreign_delivery_driver' => $foreignDeliveryDriver];
    }

    private function node(string $hq, string $area, string $code, string $type, array $capabilities): string { $id=(string)Str::uuid();DB::table('nodes')->insert(['node_id'=>$id,'hq_id'=>$hq,'area_id'=>$area,'node_code'=>$code,'node_title'=>$code,'node_type'=>$type,'capabilities'=>json_encode($capabilities,JSON_THROW_ON_ERROR),'country_code'=>'IR','status'=>'ACTIVE','version'=>1,'created_at'=>now(),'updated_at'=>now()]);return$id; }
    /** @return array{string,string} */ private function catalog(string $hq,string $user,string $code): array { $type=(string)Str::uuid();$shipping=(string)Str::uuid();foreach([['service_types','service_type_id',$type,'TYPE'],['shipping_methods','shipping_method_id',$shipping,'SHIP']]as[$table,$key,$id,$suffix])DB::table($table)->insert([$key=>$id,'hq_id'=>$hq,'owner_key'=>$hq,'code'=>$code.'-'.$suffix,'status'=>'ACTIVE','created_by'=>$user,'created_at'=>now(),'updated_at'=>now()]);return[$type,$shipping]; }
    /** @return array{string,string,list<string>} */ private function publishedRoute(string $hq,string $user,string $code,string $purpose,array $legs): array { $definition=(string)Str::uuid();$version=(string)Str::uuid();DB::table('route_definitions')->insert(['route_definition_id'=>$definition,'hq_id'=>$hq,'route_code'=>$code,'route_title'=>$code,'status'=>'ACTIVE','published_version_id'=>null,'version'=>1,'created_at'=>now(),'updated_at'=>now()]);DB::table('route_definition_versions')->insert(['route_definition_version_id'=>$version,'hq_id'=>$hq,'route_definition_id'=>$definition,'version_number'=>1,'status'=>'PUBLISHED','purpose'=>$purpose,'origin_node_id'=>$legs[0][0],'destination_node_id'=>$legs[count($legs)-1][1],'priority'=>100,'version'=>1,'created_by'=>$user,'published_by'=>$user,'published_at'=>now(),'content_digest'=>hash('sha256',$code),'created_at'=>now(),'updated_at'=>now()]);$ids=[];foreach($legs as$index=>$leg){$ids[]=$id=(string)Str::uuid();DB::table('route_definition_version_legs')->insert(['route_definition_version_leg_id'=>$id,'hq_id'=>$hq,'route_definition_version_id'=>$version,'leg_order'=>$index+1,'origin_node_id'=>$leg[0],'destination_node_id'=>$leg[1],'created_at'=>now(),'updated_at'=>now()]);}DB::table('route_definitions')->where('route_definition_id',$definition)->update(['published_version_id'=>$version]);return[$definition,$version,$ids]; }
    private function planLeg(string $hq,string $plan,string $source,int $order,string $origin,string $destination,string $status): string { $id=(string)Str::uuid();DB::table('route_plan_legs')->insert(['route_plan_leg_id'=>$id,'hq_id'=>$hq,'route_plan_id'=>$plan,'source_route_definition_leg_id'=>$source,'source_route_definition_version_leg_id'=>$source,'leg_order'=>$order,'origin_node_id'=>$origin,'destination_node_id'=>$destination,'status'=>$status,'routed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);return$id; }
    /** @return array{string} */ private function driver(string $hq,string $node,string $code,string $capability): array { $id=(string)Str::uuid();DB::table('drivers')->insert(['driver_id'=>$id,'hq_id'=>$hq,'driver_code'=>$code,'display_name'=>$code,'home_node_id'=>$node,'operational_type'=>$capability,'status'=>'ACTIVE','availability_status'=>'AVAILABLE','version'=>1,'created_at'=>now(),'updated_at'=>now()]);DB::table('driver_capabilities')->insert(['driver_capability_id'=>(string)Str::uuid(),'hq_id'=>$hq,'driver_id'=>$id,'capability'=>$capability,'created_at'=>now()]);return[$id]; }
    private function vehicle(string $hq,string $node,string $code): string { $id=(string)Str::uuid();DB::table('vehicles')->insert(['vehicle_id'=>$id,'hq_id'=>$hq,'vehicle_code'=>$code,'registration_number'=>$code,'plate_number'=>$code,'vehicle_type'=>'VAN','home_node_id'=>$node,'capacity_weight_grams'=>100000,'capacity_volume_cm3'=>1000000,'status'=>'ACTIVE','availability_status'=>'AVAILABLE','version'=>1,'created_at'=>now(),'updated_at'=>now()]);return$id; }
    private function publishedCoverage(string $hq,string $user,string $city,string $target,string $code): void { $policy=(string)Str::uuid();$version=(string)Str::uuid();DB::table('coverage_policies')->insert(['coverage_policy_id'=>$policy,'hq_id'=>$hq,'policy_code'=>$code.'-COVERAGE','policy_title'=>$code,'published_version_id'=>null,'created_at'=>now(),'updated_at'=>now()]);DB::table('coverage_policy_versions')->insert(['coverage_policy_version_id'=>$version,'hq_id'=>$hq,'coverage_policy_id'=>$policy,'version_number'=>1,'status'=>'PUBLISHED','version'=>1,'created_by'=>$user,'published_by'=>$user,'published_at'=>now(),'content_digest'=>hash('sha256',$code),'created_at'=>now(),'updated_at'=>now()]);DB::table('coverage_rules')->insert(['coverage_rule_id'=>(string)Str::uuid(),'hq_id'=>$hq,'coverage_policy_version_id'=>$version,'target'=>'LAST_MILE_NODE','target_node_id'=>$target,'priority'=>100,'criterion_type'=>'CITY','city_id'=>$city,'created_at'=>now(),'updated_at'=>now()]);DB::table('coverage_policies')->where('coverage_policy_id',$policy)->update(['published_version_id'=>$version]); }
    private function cid(string $key): string { $hex=substr(hash('sha256',$key),0,32);return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-8'.substr($hex,17,3).'-'.substr($hex,20,12); }
    private function expectApi(ApiErrorCode $code, callable $callback): void { try{$callback();self::fail("Expected {$code->value}.");}catch(ApiException $exception){self::assertSame($code,$exception->errorCode);} }
    private function assertImmutable(string $table, string $column, string $value): void { try{DB::table($table)->where($column,$value)->update(['occurred_at'=>now()->addMinute()]);self::fail("{$table} must reject updates.");}catch(QueryException){self::assertTrue(true);} }
}
