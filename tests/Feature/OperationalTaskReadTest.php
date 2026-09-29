<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Dto\ModuleEntitlementDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\EntitlementStatus;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\DeliveryTaskFiltersDto;
use Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskCommand;
use Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskHandler;
use Modules\Operations\Application\UseCases\ListDeliveryTasks\ListDeliveryTasksCommand;
use Modules\Operations\Application\UseCases\ListDeliveryTasks\ListDeliveryTasksHandler;
use Modules\Operations\Application\UseCases\ListPickupTasks\ListPickupTasksCommand;
use Modules\Operations\Application\UseCases\ListPickupTasks\ListPickupTasksHandler;
use Modules\Operations\Domain\Enums\DeliveryTaskStatus;
use Modules\Operations\Presentation\Http\Resources\DeliveryTaskDetailResource;
use Modules\Operations\Presentation\Http\Resources\DeliveryTaskResource;
use Modules\Operations\Presentation\Http\Resources\PickupTaskResource;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class OperationalTaskReadTest extends TestCase
{
    public function test_pickup_and_delivery_lists_load_relations_in_constant_queries(): void
    {
        $pickupCounts = $deliveryCounts = [];
        $actor = new AuthenticatedPrincipal('5212567', 'session', '245213294', false);
        for ($index = 1; $index <= 40; $index++) {
            RecordFixtureQuery::table('consignments')->insert(['consignment_id' => \Tests\Support\FixtureId::from('c'.$index), 'hq_id' => '245213294', 'consignment_number' => 'CN'.$index, 'sender_contact_name' => 'Sender', 'receiver_contact_name' => 'Recipient']);
            RecordFixtureQuery::table('pickup_tasks')->insert(['pickup_task_id' => \Tests\Support\FixtureId::from('pickup-'.$index), 'hq_id' => '245213294', 'consignment_id' => \Tests\Support\FixtureId::from('c'.$index), 'node_id' => '88468052', 'assigned_driver_id' => '189656963', 'status' => 'ASSIGNED']);
            RecordFixtureQuery::table('delivery_tasks')->insert(['delivery_task_id' => \Tests\Support\FixtureId::from('delivery-'.$index), 'hq_id' => '245213294', 'consignment_id' => \Tests\Support\FixtureId::from('c'.$index), 'node_id' => '88468052', 'assigned_driver_id' => '189656963', 'status' => 'IN_PROGRESS']);
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $pickups = $this->app->make(ListPickupTasksHandler::class)->handle(new ListPickupTasksCommand($actor, '88468052'));
                $pickupData = PickupTaskResource::collection($pickups)->resolve();
                $pickupCounts[] = count(DB::connection()->getQueryLog());
                DB::connection()->flushQueryLog();
                $deliveries = $this->app->make(ListDeliveryTasksHandler::class)->handle(new ListDeliveryTasksCommand($actor, '88468052'));
                $deliveryData = DeliveryTaskResource::collection($deliveries)->resolve();
                $deliveryCounts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($index, $pickupData);
            self::assertCount($index, $deliveryData);
            self::assertSame('ASSIGNED', $pickupData[0]['status']);
            self::assertSame('Driver', $pickupData[0]['assigned_driver']['display_name']);
            self::assertSame('Recipient', $deliveryData[0]['recipient']['name']);
        }
        self::assertSame([4, 4], $pickupCounts);
        self::assertSame([2, 2], $deliveryCounts);
        $filtered = $this->app->make(ListDeliveryTasksHandler::class)->handle(new ListDeliveryTasksCommand($actor, '88468052', new DeliveryTaskFiltersDto(DeliveryTaskStatus::InProgress, 'CN40')));
        self::assertCount(1, $filtered);
        self::assertSame('190672696', $filtered->first()->delivery_task_id);
        self::assertCount(0, $this->app->make(ListDeliveryTasksHandler::class)->handle(new ListDeliveryTasksCommand(new AuthenticatedPrincipal('5212567', 'session', '106329882', false), '88468052')));

        RecordFixtureQuery::table('delivery_task_history')->insert(['delivery_task_history_id' => '39430799', 'hq_id' => '245213294', 'delivery_task_id' => '11673824', 'consignment_id' => '219112221', 'event_sequence' => 1,
            'event_type' => 'ACTIVATED', 'from_status' => 'ASSIGNED', 'to_status' => 'IN_PROGRESS', 'attempt_number' => 1, 'actor_id' => '5212567', 'metadata' => '[]', 'occurred_at' => '2026-09-23 10:00:00']);
        $task = $this->app->make(GetDeliveryTaskHandler::class)->handle(new GetDeliveryTaskCommand($actor, '88468052', '11673824'));
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        try {
            $detail = (new DeliveryTaskDetailResource($task))->resolve();
            self::assertCount(0, DB::connection()->getQueryLog());
        } finally {
            DB::connection()->disableQueryLog();
        }
        self::assertSame([], $detail['history'][0]['metadata']);
        self::assertSame(['COMPLETE', 'FAIL'], $detail['permitted_actions']);
        self::assertSame('Node', $detail['node']['node_title']);
        self::assertNull($detail['last_mile_resolution']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.task_read_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('task_read_test');
        Schema::create('consignments', function (Blueprint $table): void {
            $table->increments('id');
            foreach (['consignment_id', 'hq_id', 'consignment_number', 'sender_contact_name', 'sender_mobile', 'sender_address_text', 'pickup_commitment_at', 'pickup_window_code', 'receiver_contact_name', 'receiver_mobile', 'receiver_address_text'] as $field) {
                $table->string($field)->nullable();
            }
        });
        foreach (['pickup_tasks', 'delivery_tasks', 'delivery_task_history', 'last_mile_resolution_evidence', 'drivers', 'route_plans', 'route_plan_legs'] as $table) {
            (require glob(base_path('Modules/Operations/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        (require glob(base_path('Modules/Consignment/database/migrations/*_create_parcels.php'))[0])->up();
        (require glob(base_path('Modules/Organization/database/migrations/*_create_nodes.php'))[0])->up();
        RecordFixtureQuery::table('nodes')->insert(['node_id' => '88468052', 'hq_id' => '245213294', 'area_id' => '78192358', 'node_code' => '88468052', 'node_title' => 'Node', 'node_type' => 'HUB']);
        RecordFixtureQuery::table('drivers')->insert(['driver_id' => '189656963', 'hq_id' => '245213294', 'home_node_id' => '88468052', 'driver_code' => '189656963', 'display_name' => 'Driver', 'operational_type' => 'MULTI', 'status' => 'ACTIVE', 'availability_status' => 'AVAILABLE']);
        $resolver = Mockery::mock(AccessContextResolverInterface::class);
        $resolver->shouldReceive('resolve')->andReturn(new AccessContextDto(
            hqId: '245213294', permissions: ['pickup_request.view', 'live_operations.view'], moduleEntitlements: [new ModuleEntitlementDto('Pickup', EntitlementStatus::ENABLED), new ModuleEntitlementDto('LiveOperations', EntitlementStatus::ENABLED)],
        ));
        $this->app->instance(AccessContextResolverInterface::class, $resolver);
        $scopes = Mockery::mock(ScopedAccessInterface::class);
        $scopes->shouldReceive('nodes')->andReturn(['88468052']);
        $this->app->instance(ScopedAccessInterface::class, $scopes);

    }
}
