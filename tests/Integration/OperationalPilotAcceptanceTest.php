<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Modules\Consignment\Application\ConsignmentService;
use Modules\Consignment\Application\PricingService as ConsignmentPricingService;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Geography\Domain\GeographyIds;
use Modules\Manifest\Application\ManifestService;
use Modules\Operations\Application\DeliveryTaskService;
use Modules\Operations\Application\MovementService;
use Modules\Operations\Application\PickupTaskService;
use Modules\Operations\Infrastructure\Database\Seeders\OperationalPilotSeeder as Pilot;

final class OperationalPilotAcceptanceTest extends MySqlRedisTestCase
{
    /** @var array<string,string> */
    private array $pilotTokens = [];

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('chabok.pricing.provider', 'internal');
        $this->app->make(Pilot::class)->run();
    }

    public function test_tabriz_to_tehran_pilot_executes_the_complete_owned_command_journey(): void
    {
        $seedCounts = collect(['hq_tenants', 'nodes', 'users', 'drivers', 'driver_capabilities', 'vehicles', 'route_definitions', 'route_definition_legs', 'tariff_families'])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();
        $this->app->make(Pilot::class)->run(); $this->assertSame($seedCounts, collect(array_keys($seedCounts))->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all());
        $operator = $this->actor('pilot.tbz.branch.operator');
        $pickupDispatcher = $this->actor('pilot.pickup.dispatcher');
        $draft = $this->draft();
        $quote = $this->app->make(ConsignmentPricingService::class)->calculate($operator, Pilot::TBZ_BRANCH_ID, 'CREATE', $draft, null, null);
        $option = $quote['options'][0];
        $this->assertSame(1259000, $option['total_amount']);
        $this->assertSame(['BASE_FREIGHT' => 1100000, 'INSURANCE' => 60000, 'TAX' => 99000], collect($option['charge_lines'])->mapWithKeys(fn ($line) => [$line['charge_code'] => $line['amount']])->all());
        $internal = DB::table('pricing_quotes')->where('quote_id', $option['internal_quote_id'])->first();
        $evidence = json_decode((string) $internal->resolution_evidence, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('ZONE_TBZ_CITY', DB::table('pricing_zones')->where('pricing_zone_id', $internal->origin_zone_id)->value('code'));
        $this->assertSame('ZONE_THR_CITY', DB::table('pricing_zones')->where('pricing_zone_id', $internal->destination_zone_id)->value('code'));
        $this->assertSame(5.0, (float) $evidence['weight']['billable_weight_kg']);
        $this->assertSame(58000, collect($option['charge_lines'])->firstWhere('charge_code', 'INSURANCE')['explanation']['raw_amount']);
        $this->assertNotEmpty($internal->zone_set_version_id); $this->assertNotEmpty($internal->tariff_version_id);
        $this->assertNotEmpty($internal->service_offering_id); $this->assertNotEmpty($internal->service_offering_version_id);
        $this->assertNotEmpty($evidence['service']['commitment']['schedule_version_id']);

        $consignment = $this->app->make(ConsignmentService::class)->create($operator, Pilot::TBZ_BRANCH_ID, [...$draft, 'accepted_quote' => ['quote_id' => $quote['quote_id'], 'quote_version' => 1, 'option_id' => $option['option_id']]], $this->correlation('consignment-create'));
        $consignmentId = $consignment['consignment_id']; $parcelId = $consignment['parcels'][0]['parcel_id']; $parcelNumber = $consignment['parcels'][0]['parcel_number'];
        $this->assertSame('CFM', $consignment['current_status']);
        $this->assertSame(Pilot::TAJRISH_BRANCH_ID, $consignment['delivery_node_id']);

        $pickups = $this->app->make(PickupTaskService::class);
        $pickup = $pickups->create($pickupDispatcher, Pilot::TBZ_BRANCH_ID, $consignmentId, $this->correlation('pickup-create'));
        $pickup = $pickups->assign($pickupDispatcher, Pilot::TBZ_BRANCH_ID, $pickup['pickup_task_id'], Pilot::PICKUP_DRIVER_ID, 1, $this->correlation('pickup-assign'));
        $this->assertStatus($consignmentId, 'PD', 'PICKUP_DRIVER', null);
        $pickup = $pickups->complete($pickupDispatcher, Pilot::TBZ_BRANCH_ID, $pickup['pickup_task_id'], 2, $this->correlation('pickup-complete'));
        $this->assertSame('COMPLETED', $pickup['status']); $this->assertStatus($consignmentId, 'PU', 'PICKUP_DRIVER', null);

        $tbzBranch = $this->actor('pilot.tbz.branch.operator');
        $this->confirmManifest($tbzBranch, Pilot::TBZ_BRANCH_ID, $parcelNumber, ['manifest_status' => 'IR', 'destination_node_id' => Pilot::TBZ_BRANCH_ID], 'pickup-reception');
        $this->assertStatus($consignmentId, 'IR', 'NODE', Pilot::TBZ_BRANCH_ID);

        $movement = $this->app->make(MovementService::class);
        $plan = $movement->plan($tbzBranch, Pilot::TBZ_BRANCH_ID, $consignmentId, Pilot::ROUTE_ID, $this->correlation('route-plan'));
        $runs = []; $manifestIds = [];
        $legs = [
            ['pilot.tbz.branch.operator', Pilot::TBZ_BRANCH_ID, 'pilot.tbz.hub.operator', Pilot::TBZ_HUB_ID],
            ['pilot.tbz.hub.operator', Pilot::TBZ_HUB_ID, 'pilot.thr.hub.operator', Pilot::THR_HUB_ID],
            ['pilot.thr.hub.operator', Pilot::THR_HUB_ID, 'pilot.tajrish.operator', Pilot::TAJRISH_BRANCH_ID],
        ];
        foreach ($legs as $index => [$originActorName, $originNode, $destinationActorName, $destinationNode]) {
            $originActor = $this->actor($originActorName); $destinationActor = $this->actor($destinationActorName);
            $plan = $movement->cluster($originActor, $originNode, $consignmentId, $plan['version'], $this->correlation("cluster-{$index}"));
            $leg = $plan['legs'][$index]; $this->assertSame('ROUTED', $leg['status']); $this->assertStatus($consignmentId, 'ROU', 'NODE', $originNode);
            $run = $movement->createRun($originActor, $originNode, $leg['route_plan_leg_id'], Pilot::LINEHAUL_DRIVER_ID, Pilot::VEHICLE_ID, $this->correlation("run-create-{$index}"));
            $outbound = $this->confirmManifest($originActor, $originNode, $parcelNumber, ['manifest_status' => 'OF', 'origin_node_id' => $originNode, 'destination_node_id' => $destinationNode, 'route_plan_id' => $plan['route_plan_id'], 'route_plan_leg_id' => $leg['route_plan_leg_id'], 'transport_run_id' => $run['transport_run_id'], 'assigned_driver_id' => Pilot::LINEHAUL_DRIVER_ID, 'assigned_vehicle_id' => Pilot::VEHICLE_ID], "outbound-{$index}");
            $manifestIds[] = $outbound['manifest_id']; $this->assertStatus($consignmentId, 'OF', 'NODE', $originNode);
            $run = $movement->load($originActor, $originNode, $run['transport_run_id'], [$parcelId], 1, $this->correlation("run-load-{$index}"));
            $run = $movement->depart($originActor, $originNode, $run['transport_run_id'], 2, $this->correlation("run-depart-{$index}"));
            $this->assertStatus($consignmentId, 'OS', 'TRANSPORT_RUN', null);
            $this->assertSame($consignmentId, $this->app->make(ConsignmentService::class)->get($destinationActor, $destinationNode, $consignmentId)['consignment_id']);
            if ($index === 0) $this->expectApi(ApiErrorCode::ResourceNotFound, fn () => $this->app->make(ConsignmentService::class)->get($this->actor('pilot.thr.hub.operator'), Pilot::THR_HUB_ID, $consignmentId));
            $run = $movement->arrive($destinationActor, $destinationNode, $run['transport_run_id'], 3, $this->correlation("run-arrive-{$index}"));
            $this->assertStatus($consignmentId, 'OS', 'TRANSPORT_RUN', null);
            $inbound = $this->confirmManifest($destinationActor, $destinationNode, $parcelNumber, ['manifest_status' => 'IR', 'origin_node_id' => $originNode, 'destination_node_id' => $destinationNode, 'route_plan_id' => $plan['route_plan_id'], 'route_plan_leg_id' => $leg['route_plan_leg_id'], 'transport_run_id' => $run['transport_run_id'], 'assigned_driver_id' => Pilot::LINEHAUL_DRIVER_ID, 'assigned_vehicle_id' => Pilot::VEHICLE_ID], "inbound-{$index}");
            $manifestIds[] = $inbound['manifest_id']; $this->assertStatus($consignmentId, 'IR', 'NODE', $destinationNode);
            $run = $movement->close($destinationActor, $destinationNode, $run['transport_run_id'], 4, $this->correlation("run-close-{$index}"));
            $this->assertSame('CLOSED', $run['status']); $runs[] = $run;
            if ($index < 2) $plan = $movement->routePlan($destinationActor, $destinationNode, $plan['route_plan_id']);
        }

        $deliveryDispatcher = $this->actor('pilot.delivery.dispatcher');
        $deliveries = $this->app->make(DeliveryTaskService::class);
        $delivery = $deliveries->list($deliveryDispatcher, Pilot::TAJRISH_BRANCH_ID)[0];
        $this->assertSame('PENDING', $delivery['status']);
        $delivery = $deliveries->assign($deliveryDispatcher, Pilot::TAJRISH_BRANCH_ID, $delivery['delivery_task_id'], Pilot::DELIVERY_DRIVER_ID, 1, $this->correlation('delivery-assign'));
        $od = $this->confirmManifest($this->actor('pilot.tajrish.operator'), Pilot::TAJRISH_BRANCH_ID, $parcelNumber, ['manifest_status' => 'OD', 'origin_node_id' => Pilot::TAJRISH_BRANCH_ID, 'assigned_driver_id' => Pilot::DELIVERY_DRIVER_ID], 'delivery-manifest');
        $manifestIds[] = $od['manifest_id']; $this->assertStatus($consignmentId, 'OD', 'DELIVERY_DRIVER', null);
        $delivery = $deliveries->complete($deliveryDispatcher, Pilot::TAJRISH_BRANCH_ID, $delivery['delivery_task_id'], 2, 'گیرنده پایلوت', now()->utc()->toISOString(), 'تحویل دستی تأیید شد', $this->correlation('delivery-complete'));
        $this->assertSame('COMPLETED', $delivery['status']); $this->assertSame('MANUAL_CONFIRMATION', $delivery['proof_type']);

        $detail = $this->app->make(ConsignmentService::class)->get($deliveryDispatcher, Pilot::TAJRISH_BRANCH_ID, $consignmentId);
        $this->assertSame('OK', $detail['current_status']); $this->assertSame('OK', $detail['parcels'][0]['current_status']);
        $this->assertSame(['state' => 'IN_CUSTODY', 'node' => null, 'custody_type' => 'RECIPIENT', 'custodian_id' => null], $detail['current_location']);
        $this->assertSame(['RECEIVED', 'RECEIVED', 'RECEIVED'], array_column($detail['journey']['route_legs'], 'status'));
        $this->assertSame(['CLOSED', 'CLOSED', 'CLOSED'], array_column($detail['journey']['transport_runs'], 'status'));
        $this->assertCount(8, $detail['related_manifests']);
        $this->assertSame(['CFM','PD','PU','IR','ROU','OF','OS','IR','ROU','OF','OS','IR','ROU','OF','OS','IR','OD','OK'], DB::table('consignment_status_events')->where(['consignment_id' => $consignmentId, 'parcel_id' => $parcelId])->orderBy('event_sequence')->pluck('new_status')->all());
        $materialAudit = DB::table('audit_events')->where('hq_id', Pilot::HQ_ID)->whereIn('target_id', [$consignmentId, ...$manifestIds, ...array_column($runs, 'transport_run_id')])->count();
        $materialOutbox = DB::table('outbox_events')->where('hq_id', Pilot::HQ_ID)->whereIn('aggregate_id', [$consignmentId, ...$manifestIds, ...array_column($runs, 'transport_run_id')])->count();
        $this->assertGreaterThanOrEqual(26, $materialAudit); $this->assertGreaterThanOrEqual(26, $materialOutbox);
        $this->assertSame(count($detail['status_timeline']), DB::table('consignment_status_events')->where('consignment_id', $consignmentId)->count());
    }

    public function test_negative_security_exception_and_transaction_guards_fail_closed(): void
    {
        $operator = $this->actor('pilot.tbz.branch.operator'); $dispatcher = $this->actor('pilot.pickup.dispatcher');
        $created = $this->createConsignment($operator, 'negative'); $id = $created['consignment_id'];
        $movement = $this->app->make(MovementService::class);
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $movement->plan($operator, Pilot::TBZ_BRANCH_ID, $id, '20000000-0000-4000-8000-000000000099', $this->correlation('invalid-route')));
        $pickup = $this->app->make(PickupTaskService::class)->create($dispatcher, Pilot::TBZ_BRANCH_ID, $id, $this->correlation('negative-pickup'));
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $this->app->make(PickupTaskService::class)->assign($dispatcher, Pilot::TBZ_BRANCH_ID, $pickup['pickup_task_id'], Pilot::DELIVERY_DRIVER_ID, 1, $this->correlation('wrong-capability')));
        DB::table('drivers')->where('driver_id', Pilot::PICKUP_DRIVER_ID)->update(['status' => 'INACTIVE']);
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $this->app->make(PickupTaskService::class)->assign($dispatcher, Pilot::TBZ_BRANCH_ID, $pickup['pickup_task_id'], Pilot::PICKUP_DRIVER_ID, 1, $this->correlation('inactive-driver')));
        DB::table('drivers')->where('driver_id', Pilot::PICKUP_DRIVER_ID)->update(['status' => 'ACTIVE']);
        $assigned = $this->app->make(PickupTaskService::class)->assign($dispatcher, Pilot::TBZ_BRANCH_ID, $pickup['pickup_task_id'], Pilot::PICKUP_DRIVER_ID, 1, $this->correlation('valid-assign'));
        $this->expectApi(ApiErrorCode::VersionConflict, fn () => $this->app->make(PickupTaskService::class)->complete($dispatcher, Pilot::TBZ_BRANCH_ID, $pickup['pickup_task_id'], 1, $this->correlation('stale')));
        $this->assertStatus($id, 'PD', 'PICKUP_DRIVER', null); $this->assertSame('ASSIGNED', $assigned['status']);

        $readOnly = $this->readOnlyActor(Pilot::TBZ_BRANCH_ID);
        $this->expectApi(ApiErrorCode::PermissionDenied, fn () => $this->app->make(PickupTaskService::class)->complete($readOnly, Pilot::TBZ_BRANCH_ID, $pickup['pickup_task_id'], 2, $this->correlation('readonly')));
        DB::table('tenant_module_entitlements')->where(['hq_id' => Pilot::HQ_ID, 'module_code' => 'Pickup'])->update(['status' => 'DISABLED']);
        Redis::connection('cache')->flushdb();
        $this->expectApi(ApiErrorCode::EntitlementDisabled, fn () => $this->app->make(PickupTaskService::class)->list($dispatcher, Pilot::TBZ_BRANCH_ID));
        DB::table('tenant_module_entitlements')->where(['hq_id' => Pilot::HQ_ID, 'module_code' => 'Pickup'])->update(['status' => 'ENABLED']);
        Redis::connection('cache')->flushdb();

        $failed = $this->app->make(PickupTaskService::class)->fail($dispatcher, Pilot::TBZ_BRANCH_ID, $pickup['pickup_task_id'], 2, 'CUSTOMER_UNAVAILABLE', 'فرستنده در محل حاضر نبود', $this->correlation('pickup-fail'));
        $this->assertSame('FAILED', $failed['status']); $this->assertStatus($id, 'NPU', 'PICKUP_DRIVER', null);
        $this->assertDatabaseHas('operational_exception_cases', ['consignment_id' => $id, 'exception_type' => 'NPU', 'reason_code' => 'CUSTOMER_UNAVAILABLE']);

        $splitDraft = $this->draft(); $splitDraft['parcels'] = [['content_description' => 'بسته اول', 'weight_kg' => 2, 'width_cm' => 10, 'length_cm' => 10, 'height_cm' => 10], ['content_description' => 'بسته دوم', 'weight_kg' => 3, 'width_cm' => 10, 'length_cm' => 10, 'height_cm' => 10]];
        $splitQuote = $this->app->make(ConsignmentPricingService::class)->calculate($operator, Pilot::TBZ_BRANCH_ID, 'CREATE', $splitDraft, null, null); $splitOption = $splitQuote['options'][0];
        $split = $this->app->make(ConsignmentService::class)->create($operator, Pilot::TBZ_BRANCH_ID, [...$splitDraft, 'accepted_quote' => ['quote_id' => $splitQuote['quote_id'], 'quote_version' => 1, 'option_id' => $splitOption['option_id']]], $this->correlation('split-create'));
        $splitPickup = $this->app->make(PickupTaskService::class)->create($dispatcher, Pilot::TBZ_BRANCH_ID, $split['consignment_id'], $this->correlation('split-pickup')); $splitPickup = $this->app->make(PickupTaskService::class)->assign($dispatcher, Pilot::TBZ_BRANCH_ID, $splitPickup['pickup_task_id'], Pilot::PICKUP_DRIVER_ID, 1, $this->correlation('split-assign')); $this->app->make(PickupTaskService::class)->complete($dispatcher, Pilot::TBZ_BRANCH_ID, $splitPickup['pickup_task_id'], 2, $this->correlation('split-complete'));
        $this->confirmManifest($operator, Pilot::TBZ_BRANCH_ID, $split['parcels'][0]['parcel_number'], ['manifest_status' => 'IR', 'destination_node_id' => Pilot::TBZ_BRANCH_ID], 'split-reception');
        $splitDetail = $this->app->make(ConsignmentService::class)->get($operator, Pilot::TBZ_BRANCH_ID, $split['consignment_id']);
        $this->assertSame('MIXED', $splitDetail['current_location']['state']); $this->assertSame('MIXED', $splitDetail['current_location']['custody_type']); $this->assertNull($splitDetail['current_location']['node']);

        $foreign = $this->tenant('FOREIGN-PILOT'); $foreignUser = $this->user($foreign['hq_id'], 'foreign-pilot');
        $foreignActor = new AuthenticatedPrincipal($foreignUser['user_id'], $this->correlation('foreign-session'), $foreign['hq_id'], false);
        $this->expectApi(ApiErrorCode::EntitlementDisabled, fn () => $this->app->make(ConsignmentService::class)->get($foreignActor, Pilot::TBZ_BRANCH_ID, $id));
    }

    public function test_route_transition_node_driver_vehicle_and_concurrency_guards_fail_closed(): void
    {
        $operator = $this->actor('pilot.tbz.branch.operator');
        $dispatcher = $this->actor('pilot.pickup.dispatcher');
        $created = $this->createConsignment($operator, 'movement-guards');
        $id = $created['consignment_id']; $parcelId = $created['parcels'][0]['parcel_id']; $parcelNumber = $created['parcels'][0]['parcel_number'];
        $pickups = $this->app->make(PickupTaskService::class);
        $pickup = $pickups->create($dispatcher, Pilot::TBZ_BRANCH_ID, $id, $this->correlation('guards-pickup-create'));
        $pickup = $pickups->assign($dispatcher, Pilot::TBZ_BRANCH_ID, $pickup['pickup_task_id'], Pilot::PICKUP_DRIVER_ID, 1, $this->correlation('guards-pickup-assign'));
        $pickups->complete($dispatcher, Pilot::TBZ_BRANCH_ID, $pickup['pickup_task_id'], 2, $this->correlation('guards-pickup-complete'));

        $movement = $this->app->make(MovementService::class);
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $movement->cluster($operator, Pilot::TBZ_BRANCH_ID, $id, 1, $this->correlation('skip-reception')));
        $this->confirmManifest($operator, Pilot::TBZ_BRANCH_ID, $parcelNumber, ['manifest_status' => 'IR', 'destination_node_id' => Pilot::TBZ_BRANCH_ID], 'guards-reception');
        $plan = $movement->plan($operator, Pilot::TBZ_BRANCH_ID, $id, Pilot::ROUTE_ID, $this->correlation('guards-plan'));
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $movement->cluster($this->actor('pilot.tbz.hub.operator'), Pilot::TBZ_HUB_ID, $id, 1, $this->correlation('later-leg-too-soon')));
        $plan = $movement->cluster($operator, Pilot::TBZ_BRANCH_ID, $id, 1, $this->correlation('guards-cluster'));
        $leg = $plan['legs'][0];
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $movement->createRun($operator, Pilot::TBZ_BRANCH_ID, $leg['route_plan_leg_id'], Pilot::DELIVERY_DRIVER_ID, Pilot::VEHICLE_ID, $this->correlation('wrong-linehaul-capability')));
        DB::table('vehicles')->where('vehicle_id', Pilot::VEHICLE_ID)->update(['status' => 'INACTIVE']);
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $movement->createRun($operator, Pilot::TBZ_BRANCH_ID, $leg['route_plan_leg_id'], Pilot::LINEHAUL_DRIVER_ID, Pilot::VEHICLE_ID, $this->correlation('inactive-vehicle')));
        DB::table('vehicles')->where('vehicle_id', Pilot::VEHICLE_ID)->update(['status' => 'ACTIVE']);
        $run = $movement->createRun($operator, Pilot::TBZ_BRANCH_ID, $leg['route_plan_leg_id'], Pilot::LINEHAUL_DRIVER_ID, Pilot::VEHICLE_ID, $this->correlation('guards-run'));
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $movement->depart($operator, Pilot::TBZ_BRANCH_ID, $run['transport_run_id'], 1, $this->correlation('depart-before-load')));
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $movement->load($operator, Pilot::TBZ_BRANCH_ID, $run['transport_run_id'], [$parcelId], 1, $this->correlation('load-before-outbound')));
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $this->app->make(ManifestService::class)->create($operator, Pilot::TBZ_BRANCH_ID, ['manifest_status' => 'OF', 'origin_node_id' => Pilot::TBZ_BRANCH_ID, 'destination_node_id' => Pilot::TBZ_HUB_ID, 'route_plan_id' => $plan['route_plan_id'], 'route_plan_leg_id' => '30000000-0000-4000-8000-000000000099', 'transport_run_id' => $run['transport_run_id'], 'assigned_driver_id' => Pilot::LINEHAUL_DRIVER_ID, 'assigned_vehicle_id' => Pilot::VEHICLE_ID], $this->correlation('foreign-route-leg')));
        $this->confirmManifest($operator, Pilot::TBZ_BRANCH_ID, $parcelNumber, ['manifest_status' => 'OF', 'origin_node_id' => Pilot::TBZ_BRANCH_ID, 'destination_node_id' => Pilot::TBZ_HUB_ID, 'route_plan_id' => $plan['route_plan_id'], 'route_plan_leg_id' => $leg['route_plan_leg_id'], 'transport_run_id' => $run['transport_run_id'], 'assigned_driver_id' => Pilot::LINEHAUL_DRIVER_ID, 'assigned_vehicle_id' => Pilot::VEHICLE_ID], 'guards-outbound');
        $run = $movement->load($operator, Pilot::TBZ_BRANCH_ID, $run['transport_run_id'], [$parcelId], 1, $this->correlation('guards-load'));
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $movement->arrive($this->actor('pilot.tbz.hub.operator'), Pilot::TBZ_HUB_ID, $run['transport_run_id'], 2, $this->correlation('arrive-before-depart')));
        $run = $movement->depart($operator, Pilot::TBZ_BRANCH_ID, $run['transport_run_id'], 2, $this->correlation('guards-depart'));
        $this->expectApi(ApiErrorCode::ResourceNotFound, fn () => $movement->arrive($this->actor('pilot.thr.hub.operator'), Pilot::THR_HUB_ID, $run['transport_run_id'], 3, $this->correlation('wrong-destination')));
        $run = $movement->arrive($this->actor('pilot.tbz.hub.operator'), Pilot::TBZ_HUB_ID, $run['transport_run_id'], 3, $this->correlation('guards-arrive'));
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $movement->close($this->actor('pilot.tbz.hub.operator'), Pilot::TBZ_HUB_ID, $run['transport_run_id'], 4, $this->correlation('close-before-reception')));
        $this->confirmManifest($this->actor('pilot.tbz.hub.operator'), Pilot::TBZ_HUB_ID, $parcelNumber, ['manifest_status' => 'IR', 'origin_node_id' => Pilot::TBZ_BRANCH_ID, 'destination_node_id' => Pilot::TBZ_HUB_ID, 'route_plan_id' => $plan['route_plan_id'], 'route_plan_leg_id' => $leg['route_plan_leg_id'], 'transport_run_id' => $run['transport_run_id'], 'assigned_driver_id' => Pilot::LINEHAUL_DRIVER_ID, 'assigned_vehicle_id' => Pilot::VEHICLE_ID], 'guards-inbound');
        $this->expectApi(ApiErrorCode::VersionConflict, fn () => $movement->close($this->actor('pilot.tbz.hub.operator'), Pilot::TBZ_HUB_ID, $run['transport_run_id'], 3, $this->correlation('stale-close')));
        $closed = $movement->close($this->actor('pilot.tbz.hub.operator'), Pilot::TBZ_HUB_ID, $run['transport_run_id'], 4, $this->correlation('guards-close'));
        $this->assertSame('CLOSED', $closed['status']); $this->assertStatus($id, 'IR', 'NODE', Pilot::TBZ_HUB_ID);
    }

    public function test_failed_delivery_is_persisted_and_another_driver_cannot_execute_it(): void
    {
        ['consignment_id' => $id, 'delivery_task' => $task] = $this->advanceToAssignedDelivery('delivery-failure');
        $service = $this->app->make(DeliveryTaskService::class); $dispatcher = $this->actor('pilot.delivery.dispatcher');
        $this->expectApi(ApiErrorCode::PermissionDenied, fn () => $service->complete($this->actor('pilot.pickup.driver'), Pilot::TAJRISH_BRANCH_ID, $task['delivery_task_id'], 2, 'گیرنده', now()->toISOString(), null, $this->correlation('wrong-delivery-driver')));
        $failed = $service->fail($dispatcher, Pilot::TAJRISH_BRANCH_ID, $task['delivery_task_id'], 2, 'RECIPIENT_UNAVAILABLE', 'گیرنده در محل حاضر نبود', $this->correlation('nok-fail'));
        $this->assertSame('FAILED', $failed['status']); $this->assertStatus($id, 'NOK', 'DELIVERY_DRIVER', null);
        $this->assertDatabaseHas('operational_exception_cases', ['consignment_id' => $id, 'delivery_task_id' => $task['delivery_task_id'], 'exception_type' => 'NOK', 'reason_code' => 'RECIPIENT_UNAVAILABLE']);
        $this->assertDatabaseHas('operational_exception_history', ['action' => 'APPROVED_AND_APPLIED']);
    }

    public function test_outbox_failure_rolls_back_delivery_status_custody_task_and_evidence_together(): void
    {
        ['consignment_id' => $id, 'delivery_task' => $task] = $this->advanceToAssignedDelivery('rollback');
        $parcel = DB::table('parcels')->where('consignment_id', $id)->first(); $manifestId = (string) DB::table('delivery_tasks')->where('delivery_task_id', $task['delivery_task_id'])->value('manifest_id'); $manifest = DB::table('manifests')->where('manifest_id', $manifestId)->first();
        $before = ['status' => DB::table('consignment_status_events')->where('consignment_id', $id)->count(), 'custody' => DB::table('parcel_custody_events')->where('consignment_id', $id)->count(), 'audit' => DB::table('audit_events')->count(), 'outbox' => DB::table('outbox_events')->count()];
        $this->app->instance(OutboxWriter::class, new class implements OutboxWriter { public function write(?string $hqId, string $aggregateType, string $aggregateId, string $eventType, string $correlationId, array $payload, int $eventVersion = 1, ?string $causationId = null): void { throw new \RuntimeException('Injected outbox failure.'); } });
        try { $this->app->make(DeliveryTaskService::class)->complete($this->actor('pilot.delivery.dispatcher'), Pilot::TAJRISH_BRANCH_ID, $task['delivery_task_id'], 2, 'گیرنده', now()->utc()->toISOString(), 'نباید ثبت شود', $this->correlation('rollback-delivery')); $this->fail('Expected the injected outbox failure.'); } catch (\RuntimeException $e) { $this->assertSame('Injected outbox failure.', $e->getMessage()); }
        $this->assertSame($before['status'], DB::table('consignment_status_events')->where('consignment_id', $id)->count()); $this->assertSame($before['custody'], DB::table('parcel_custody_events')->where('consignment_id', $id)->count()); $this->assertSame($before['audit'], DB::table('audit_events')->count()); $this->assertSame($before['outbox'], DB::table('outbox_events')->count());
        $this->assertDatabaseHas('delivery_tasks', ['delivery_task_id' => $task['delivery_task_id'], 'status' => 'ASSIGNED', 'version' => 2, 'recipient_name' => null, 'delivered_at' => null]); $this->assertDatabaseHas('manifests', ['manifest_id' => $manifest->manifest_id, 'state' => 'CLOSED', 'version' => $manifest->version]);
        $this->assertDatabaseHas('parcels', ['parcel_id' => $parcel->parcel_id, 'current_status' => 'OD', 'current_custody_type' => 'DELIVERY_DRIVER', 'current_custodian_id' => Pilot::DELIVERY_DRIVER_ID]); $this->assertDatabaseHas('consignments', ['consignment_id' => $id, 'current_status' => 'OD']);
    }

    public function test_every_retry_sensitive_pilot_command_replays_without_duplicate_state_or_evidence(): void
    {
        $created = $this->createConsignment($this->actor('pilot.tbz.branch.operator'), 'http-replay');
        $id = $created['consignment_id']; $parcelId = $created['parcels'][0]['parcel_id']; $number = $created['parcels'][0]['parcel_number'];
        $pickupToken = $this->pilotToken('pilot.pickup.dispatcher'); $tbzToken = $this->pilotToken('pilot.tbz.branch.operator');
        $pickup = $this->replayPost($pickupToken, Pilot::TBZ_BRANCH_ID, '/api/v1/pickup-tasks', ['consignment_id' => $id], 'pickup-create', 201);
        $pickup = $this->replayPost($pickupToken, Pilot::TBZ_BRANCH_ID, "/api/v1/pickup-tasks/{$pickup['pickup_task_id']}/assign", ['expected_version' => 1, 'driver_id' => Pilot::PICKUP_DRIVER_ID], 'pickup-assign');
        $this->replayPost($pickupToken, Pilot::TBZ_BRANCH_ID, "/api/v1/pickup-tasks/{$pickup['pickup_task_id']}/complete", ['expected_version' => 2], 'pickup-complete');
        $this->replayManifest($tbzToken, Pilot::TBZ_BRANCH_ID, $number, ['manifest_status' => 'IR', 'destination_node_id' => Pilot::TBZ_BRANCH_ID], 'pickup-reception');

        $plan = $this->replayPost($tbzToken, Pilot::TBZ_BRANCH_ID, "/api/v1/consignments/{$id}/route-plan", ['route_definition_id' => Pilot::ROUTE_ID], 'route-plan', 201);
        $legs = [
            ['pilot.tbz.branch.operator', Pilot::TBZ_BRANCH_ID, 'pilot.tbz.hub.operator', Pilot::TBZ_HUB_ID],
            ['pilot.tbz.hub.operator', Pilot::TBZ_HUB_ID, 'pilot.thr.hub.operator', Pilot::THR_HUB_ID],
            ['pilot.thr.hub.operator', Pilot::THR_HUB_ID, 'pilot.tajrish.operator', Pilot::TAJRISH_BRANCH_ID],
        ];
        foreach ($legs as $index => [$originName, $origin, $destinationName, $destination]) {
            $originToken = $this->pilotToken($originName); $destinationToken = $this->pilotToken($destinationName);
            $plan = $this->replayPost($originToken, $origin, "/api/v1/consignments/{$id}/cluster", ['expected_route_plan_version' => $plan['version']], "cluster-{$index}"); $leg = $plan['legs'][$index];
            $run = $this->replayPost($originToken, $origin, '/api/v1/transport-runs', ['route_plan_leg_id' => $leg['route_plan_leg_id'], 'driver_id' => Pilot::LINEHAUL_DRIVER_ID, 'vehicle_id' => Pilot::VEHICLE_ID], "run-create-{$index}", 201);
            $context = ['origin_node_id' => $origin, 'destination_node_id' => $destination, 'route_plan_id' => $plan['route_plan_id'], 'route_plan_leg_id' => $leg['route_plan_leg_id'], 'transport_run_id' => $run['transport_run_id'], 'assigned_driver_id' => Pilot::LINEHAUL_DRIVER_ID, 'assigned_vehicle_id' => Pilot::VEHICLE_ID];
            $this->replayManifest($originToken, $origin, $number, ['manifest_status' => 'OF', ...$context], "outbound-{$index}");
            $run = $this->replayPost($originToken, $origin, "/api/v1/transport-runs/{$run['transport_run_id']}/load", ['expected_version' => 1, 'parcel_ids' => [$parcelId]], "run-load-{$index}");
            $run = $this->replayPost($originToken, $origin, "/api/v1/transport-runs/{$run['transport_run_id']}/depart", ['expected_version' => 2], "run-depart-{$index}");
            $run = $this->replayPost($destinationToken, $destination, "/api/v1/transport-runs/{$run['transport_run_id']}/arrive", ['expected_version' => 3], "run-arrive-{$index}");
            $this->replayManifest($destinationToken, $destination, $number, ['manifest_status' => 'IR', ...$context], "inbound-{$index}");
            $this->replayPost($destinationToken, $destination, "/api/v1/transport-runs/{$run['transport_run_id']}/close", ['expected_version' => 4], "run-close-{$index}");
            if ($index < 2) $plan = $this->app->make(MovementService::class)->routePlan($this->actor($destinationName), $destination, $plan['route_plan_id']);
        }
        $deliveryToken = $this->pilotToken('pilot.delivery.dispatcher'); $delivery = $this->app->make(DeliveryTaskService::class)->list($this->actor('pilot.delivery.dispatcher'), Pilot::TAJRISH_BRANCH_ID)[0];
        $delivery = $this->replayPost($deliveryToken, Pilot::TAJRISH_BRANCH_ID, "/api/v1/delivery-tasks/{$delivery['delivery_task_id']}/assign", ['expected_version' => 1, 'driver_id' => Pilot::DELIVERY_DRIVER_ID], 'delivery-assign');
        $this->replayManifest($this->pilotToken('pilot.tajrish.operator'), Pilot::TAJRISH_BRANCH_ID, $number, ['manifest_status' => 'OD', 'origin_node_id' => Pilot::TAJRISH_BRANCH_ID, 'assigned_driver_id' => Pilot::DELIVERY_DRIVER_ID], 'delivery-manifest');
        $this->replayPost($deliveryToken, Pilot::TAJRISH_BRANCH_ID, "/api/v1/delivery-tasks/{$delivery['delivery_task_id']}/complete", ['expected_version' => 2, 'recipient_name' => 'گیرنده پایلوت', 'delivered_at' => now()->utc()->toISOString(), 'proof_type' => 'MANUAL_CONFIRMATION', 'note' => 'تحویل دستی تأیید شد'], 'delivery-complete');
        $this->assertStatus($id, 'OK', 'RECIPIENT', null);
        $this->assertSame(1, DB::table('pickup_tasks')->where('consignment_id', $id)->count()); $this->assertSame(1, DB::table('delivery_tasks')->where('consignment_id', $id)->count()); $this->assertSame(1, DB::table('route_plans')->where('consignment_id', $id)->count()); $this->assertSame(8, DB::table('manifests as m')->join('manifest_parcels as mp', 'mp.manifest_id', '=', 'm.manifest_id')->join('parcels as p', 'p.parcel_id', '=', 'mp.parcel_id')->where('p.consignment_id', $id)->distinct()->count('m.manifest_id'));
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function confirmManifest(AuthenticatedPrincipal $actor, string $nodeId, string $parcelNumber, array $context, string $key): array
    {
        $service = $this->app->make(ManifestService::class);
        $manifest = $service->create($actor, $nodeId, $context, $this->correlation("{$key}:create"));
        $manifest = $service->add($actor, $nodeId, $manifest['manifest_id'], ['expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$parcelNumber]], $this->correlation("{$key}:add"))['detail'];
        $manifest = $service->validate($actor, $nodeId, $manifest['manifest_id'], 2, $this->correlation("{$key}:validate"));
        return $service->confirm($actor, $nodeId, $manifest['manifest_id'], 3, $this->correlation("{$key}:confirm"));
    }

    /** @return array<string,mixed> */
    private function createConsignment(AuthenticatedPrincipal $actor, string $key): array
    {
        $draft = $this->draft(); $quote = $this->app->make(ConsignmentPricingService::class)->calculate($actor, Pilot::TBZ_BRANCH_ID, 'CREATE', $draft, null, null); $option = $quote['options'][0];
        return $this->app->make(ConsignmentService::class)->create($actor, Pilot::TBZ_BRANCH_ID, [...$draft, 'accepted_quote' => ['quote_id' => $quote['quote_id'], 'quote_version' => 1, 'option_id' => $option['option_id']]], $this->correlation("create:{$key}"));
    }

    /** @return array{consignment_id:string,delivery_task:array<string,mixed>} */
    private function advanceToAssignedDelivery(string $key): array
    {
        $tbz = $this->actor('pilot.tbz.branch.operator'); $dispatcher = $this->actor('pilot.pickup.dispatcher');
        $created = $this->createConsignment($tbz, $key); $id = $created['consignment_id']; $parcelId = $created['parcels'][0]['parcel_id']; $number = $created['parcels'][0]['parcel_number'];
        $pickups = $this->app->make(PickupTaskService::class); $pickup = $pickups->create($dispatcher, Pilot::TBZ_BRANCH_ID, $id, $this->correlation("{$key}:pickup")); $pickup = $pickups->assign($dispatcher, Pilot::TBZ_BRANCH_ID, $pickup['pickup_task_id'], Pilot::PICKUP_DRIVER_ID, 1, $this->correlation("{$key}:assign-pickup")); $pickups->complete($dispatcher, Pilot::TBZ_BRANCH_ID, $pickup['pickup_task_id'], 2, $this->correlation("{$key}:complete-pickup"));
        $this->confirmManifest($tbz, Pilot::TBZ_BRANCH_ID, $number, ['manifest_status' => 'IR', 'destination_node_id' => Pilot::TBZ_BRANCH_ID], "{$key}:pickup-receive");
        $movement = $this->app->make(MovementService::class); $plan = $movement->plan($tbz, Pilot::TBZ_BRANCH_ID, $id, Pilot::ROUTE_ID, $this->correlation("{$key}:plan"));
        $legs = [['pilot.tbz.branch.operator', Pilot::TBZ_BRANCH_ID, 'pilot.tbz.hub.operator', Pilot::TBZ_HUB_ID], ['pilot.tbz.hub.operator', Pilot::TBZ_HUB_ID, 'pilot.thr.hub.operator', Pilot::THR_HUB_ID], ['pilot.thr.hub.operator', Pilot::THR_HUB_ID, 'pilot.tajrish.operator', Pilot::TAJRISH_BRANCH_ID]];
        foreach ($legs as $index => [$originName, $origin, $destinationName, $destination]) {
            $originActor = $this->actor($originName); $destinationActor = $this->actor($destinationName); $plan = $movement->cluster($originActor, $origin, $id, $plan['version'], $this->correlation("{$key}:cluster:{$index}")); $leg = $plan['legs'][$index];
            $run = $movement->createRun($originActor, $origin, $leg['route_plan_leg_id'], Pilot::LINEHAUL_DRIVER_ID, Pilot::VEHICLE_ID, $this->correlation("{$key}:run:{$index}")); $context = ['origin_node_id' => $origin, 'destination_node_id' => $destination, 'route_plan_id' => $plan['route_plan_id'], 'route_plan_leg_id' => $leg['route_plan_leg_id'], 'transport_run_id' => $run['transport_run_id'], 'assigned_driver_id' => Pilot::LINEHAUL_DRIVER_ID, 'assigned_vehicle_id' => Pilot::VEHICLE_ID];
            $this->confirmManifest($originActor, $origin, $number, ['manifest_status' => 'OF', ...$context], "{$key}:outbound:{$index}"); $run = $movement->load($originActor, $origin, $run['transport_run_id'], [$parcelId], 1, $this->correlation("{$key}:load:{$index}")); $run = $movement->depart($originActor, $origin, $run['transport_run_id'], 2, $this->correlation("{$key}:depart:{$index}")); $run = $movement->arrive($destinationActor, $destination, $run['transport_run_id'], 3, $this->correlation("{$key}:arrive:{$index}")); $this->confirmManifest($destinationActor, $destination, $number, ['manifest_status' => 'IR', ...$context], "{$key}:inbound:{$index}"); $movement->close($destinationActor, $destination, $run['transport_run_id'], 4, $this->correlation("{$key}:close:{$index}"));
            if ($index < 2) $plan = $movement->routePlan($destinationActor, $destination, $plan['route_plan_id']);
        }
        $deliveryActor = $this->actor('pilot.delivery.dispatcher'); $deliveries = $this->app->make(DeliveryTaskService::class); $delivery = $deliveries->list($deliveryActor, Pilot::TAJRISH_BRANCH_ID)[0]; $delivery = $deliveries->assign($deliveryActor, Pilot::TAJRISH_BRANCH_ID, $delivery['delivery_task_id'], Pilot::DELIVERY_DRIVER_ID, 1, $this->correlation("{$key}:delivery-assign")); $this->confirmManifest($this->actor('pilot.tajrish.operator'), Pilot::TAJRISH_BRANCH_ID, $number, ['manifest_status' => 'OD', 'origin_node_id' => Pilot::TAJRISH_BRANCH_ID, 'assigned_driver_id' => Pilot::DELIVERY_DRIVER_ID], "{$key}:delivery-manifest");
        return ['consignment_id' => $id, 'delivery_task' => $delivery];
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function replayManifest(string $token, string $nodeId, string $parcelNumber, array $context, string $key): array
    {
        $manifest = $this->replayPost($token, $nodeId, '/api/v1/manifests', $context, "{$key}:create", 201);
        $path = "/api/v1/manifests/{$manifest['manifest_id']}/parcels";
        $headers = ['X-Node-Id' => $nodeId, 'X-Correlation-ID' => $this->correlation("{$key}:scan")];
        $this->withToken($token)->withHeaders($headers)->postJson($path, ['expected_version' => 1, 'input_source' => 'SCAN', 'identifiers' => [$parcelNumber]])->assertOk();
        $this->assertSame(1, DB::table('manifest_parcels')->where('manifest_id', $manifest['manifest_id'])->count());
        $beforeDuplicate = $this->materialCounts();
        $duplicate = $this->withToken($token)->withHeaders($headers)->postJson($path, ['expected_version' => 2, 'input_source' => 'SCAN', 'identifiers' => [$parcelNumber]]);
        $duplicate->assertOk()->assertJsonPath('meta.input_outcomes.0.result', 'DUPLICATE');
        $this->assertSame($beforeDuplicate, $this->materialCounts());
        $this->withToken($token)->withHeaders(['X-Node-Id' => $nodeId, 'X-Correlation-ID' => $this->correlation("{$key}:validate")])->postJson("/api/v1/manifests/{$manifest['manifest_id']}/validate", ['expected_version' => 2])->assertOk();
        return $this->replayPost($token, $nodeId, "/api/v1/manifests/{$manifest['manifest_id']}/confirm", ['expected_version' => 3, 'acknowledge_partial_success' => true], "{$key}:confirm");
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function replayPost(string $token, string $nodeId, string $path, array $payload, string $key, int $status = 200): array
    {
        $headers = ['X-Node-Id' => $nodeId, 'X-Correlation-ID' => $this->correlation("http:{$key}"), 'Idempotency-Key' => 'pilot-replay-'.hash('sha256', $key)];
        $first = $this->withToken($token)->withHeaders($headers)->postJson($path, $payload); $first->assertStatus($status);
        $data = $first->json('data'); $this->assertIsArray($data); $afterFirst = $this->materialCounts();
        $second = $this->withToken($token)->withHeaders($headers)->postJson($path, $payload); $second->assertStatus($status);
        $this->assertEquals($data, $second->json('data')); $this->assertSame($afterFirst, $this->materialCounts(), "Replay changed material state for {$key}.");
        return $data;
    }

    /** @return array<string,int> */
    private function materialCounts(): array
    {
        $counts = collect(['pickup_tasks', 'delivery_tasks', 'route_plans', 'route_plan_legs', 'transport_runs', 'transport_run_parcels', 'manifests', 'manifest_parcels', 'consignment_status_events', 'parcel_custody_events', 'operational_exception_cases', 'operational_exception_history'])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();
        $operationalTypes = ['PICKUP_TASK', 'DELIVERY_TASK', 'ROUTE_PLAN', 'TRANSPORT_RUN', 'MANIFEST', 'CONSIGNMENT', 'PARCEL'];
        $counts['audit_events'] = DB::table('audit_events')->whereIn('target_type', $operationalTypes)->count();
        $counts['outbox_events'] = DB::table('outbox_events')->whereIn('aggregate_type', $operationalTypes)->count();
        return $counts;
    }

    private function pilotToken(string $username): string
    {
        if (isset($this->pilotTokens[$username])) return $this->pilotTokens[$username];
        $password = bin2hex(random_bytes(24)).'Aa1!'; $userId = Pilot::ACTORS[$username][0];
        DB::table('authentication_credentials')->updateOrInsert(['user_id' => $userId], ['credential_id' => $this->correlation("credential:{$username}"), 'password_hash' => password_hash($password, PASSWORD_ARGON2ID), 'algorithm' => 'argon2id', 'algorithm_version' => 1, 'password_changed_at' => now(), 'failed_attempt_count' => 0, 'created_at' => now(), 'updated_at' => now()]);
        return $this->pilotTokens[$username] = $this->login($username, $password)['token'];
    }

    /** @return array<string,mixed> */
    private function draft(): array
    {
        $offering = DB::table('service_offerings as o')->join('service_offering_versions as v', 'v.service_offering_id', '=', 'o.service_offering_id')->where(['o.hq_id' => Pilot::HQ_ID, 'o.code' => 'PILOT_TBZ_THR_72H', 'v.status' => 'PUBLISHED'])->first();
        $type = DB::table('service_types as i')->join('service_type_versions as v', 'v.service_type_id', '=', 'i.service_type_id')->where(['i.hq_id' => Pilot::HQ_ID, 'i.code' => 'PILOT_INTERCITY', 'v.status' => 'PUBLISHED'])->first();
        $method = DB::table('shipping_methods as i')->join('shipping_method_versions as v', 'v.shipping_method_id', '=', 'i.shipping_method_id')->where(['i.hq_id' => Pilot::HQ_ID, 'i.code' => 'PILOT_GROUND', 'v.status' => 'PUBLISHED'])->first();
        return ['sender' => ['contact_name' => 'فرستنده پایلوت', 'mobile' => '09120000001', 'address_text' => 'تبریز، آدرس پایلوت', 'city_id' => GeographyIds::city('10712'), 'state' => 'آذربایجان شرقی', 'city' => 'تبریز'], 'receiver' => ['contact_name' => 'گیرنده پایلوت', 'mobile' => '09120000002', 'address_text' => 'تهران، تجریش، آدرس پایلوت', 'city_id' => GeographyIds::city('10866'), 'state' => 'تهران', 'city' => 'تهران'], 'delivery_node_id' => Pilot::TAJRISH_BRANCH_ID, 'service_type_id' => $type->service_type_id, 'shipping_method_id' => $method->shipping_method_id, 'service_offering_id' => $offering->service_offering_id, 'service_offering_version_id' => $offering->service_offering_version_id, 'selected_option_version_ids' => [], 'pickup_service_date' => now('Asia/Tehran')->toDateString(), 'pickup_window_code' => 'PILOT_MORNING', 'delivery_window_code' => null, 'pickup_commitment_at' => now()->addHour()->utc()->toISOString(), 'delivery_commitment_at' => now()->addHours(73)->utc()->toISOString(), 'weight_kg' => 5, 'width_cm' => 20, 'length_cm' => 20, 'height_cm' => 20, 'declared_value_amount' => 290000000, 'insurance_enabled' => true, 'insurance_value_amount' => 290000000, 'cod_enabled' => false, 'cod_amount' => null, 'payer' => 'SENDER', 'payment_method' => 'CASH', 'parcels' => [['content_description' => 'کالای پایلوت غیرخطرناک', 'weight_kg' => 5, 'width_cm' => 20, 'length_cm' => 20, 'height_cm' => 20]]];
    }

    private function actor(string $username): AuthenticatedPrincipal { return new AuthenticatedPrincipal(Pilot::ACTORS[$username][0], $this->correlation("session:{$username}"), Pilot::HQ_ID, false); }
    private function correlation(string $key): string { $hex = substr(hash('sha256', 'pilot-test:'.$key), 0, 32); return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-8'.substr($hex,17,3).'-'.substr($hex,20,12); }
    private function assertStatus(string $consignmentId, string $status, string $custody, ?string $node): void { $this->assertDatabaseHas('consignments', ['consignment_id' => $consignmentId, 'current_status' => $status]); $this->assertDatabaseHas('parcels', ['consignment_id' => $consignmentId, 'current_status' => $status, 'current_custody_type' => $custody, 'current_node_id' => $node]); }
    private function expectApi(ApiErrorCode $code, callable $callback): void { try { $callback(); $this->fail("Expected {$code->value}."); } catch (ApiException $e) { $this->assertSame($code, $e->errorCode); } }
    private function readOnlyActor(string $nodeId): AuthenticatedPrincipal { $user = $this->user(Pilot::HQ_ID, 'pilot-readonly'); $role = (string) DB::table('roles')->where('role_code', 'branch_read_only')->value('role_id'); DB::table('user_role_assignments')->insert(['assignment_id' => $this->correlation('readonly-assignment'), 'hq_id' => Pilot::HQ_ID, 'user_id' => $user['user_id'], 'role_id' => $role, 'scope_type' => 'NODE', 'scope_id' => $nodeId, 'includes_descendants' => false, 'status' => 'ACTIVE', 'active_slot' => hash('sha256', "{$user['user_id']}|{$role}|NODE|{$nodeId}"), 'created_at' => now(), 'updated_at' => now()]); return new AuthenticatedPrincipal($user['user_id'], $this->correlation('readonly-session'), Pilot::HQ_ID, false); }
}
